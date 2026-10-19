<?php

namespace App\Services;

use App\Enums\EmailDriver;
use App\Exceptions\PermanentEmailDeliveryException;
use App\Jobs\SendEmailJob;
use App\Models\EmailLog;
use App\Models\EmailProvider;
use App\Models\ExternalIntegration;
use App\Support\MailHeaderSanitizer;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class EmailDispatchService
{
    public function __construct(
        private readonly DynamicMailConfigService $mailConfig,
        private readonly EmailBrandingService $branding,
        private readonly PhpMailerSmtpMailer $phpMailerSmtp,
        private readonly EmailAttachmentService $attachments,
    ) {}

    /**
     * Queue an email for async delivery (non-blocking HTTP response).
     *
     * @param  array<int, string>  $cc
     * @param  array<int, string>  $bcc
     * @param  list<array{filename: string, content: string, content_type: string, size: int}>  $attachmentPayload
     */
    public function queue(
        string $to,
        string $subject,
        string $body,
        bool $isHtml = true,
        ?int $providerId = null,
        ?ExternalIntegration $integration = null,
        array $cc = [],
        array $bcc = [],
        ?string $source = null,
        ?string $senderIp = null,
        array $attachmentPayload = [],
    ): EmailLog {
        $provider = $this->mailConfig->resolveProvider($providerId);

        $meta = [
            'body' => $body,
            'is_html' => $isHtml,
            'cc' => $cc,
            'bcc' => $bcc,
        ];

        if ($source !== null) {
            $meta['source'] = $source;
        }

        if ($senderIp !== null && $senderIp !== '') {
            $meta['sender_ip'] = $senderIp;
        }

        $log = EmailLog::query()->create([
            'email_provider_id' => $provider->id,
            'external_integration_id' => $integration?->id,
            'to' => $to,
            'subject' => MailHeaderSanitizer::line($subject, 500),
            'status' => 'pending',
            'driver' => $provider->driver->value,
            'meta' => $meta,
        ]);

        if ($attachmentPayload !== []) {
            $stored = $this->attachments->store($attachmentPayload, $log->id);
            $meta['attachments'] = $stored;
            $meta['attachment_count'] = count($stored);
            $log->update(['meta' => $meta]);
        }

        SendEmailJob::dispatch($log->id);

        return $log->fresh() ?? $log;
    }

    /**
     * Deliver a queued email (runs inside queue worker).
     */
    public function deliver(int $emailLogId): EmailLog
    {
        $log = EmailLog::query()->findOrFail($emailLogId);

        if ($log->status === 'sent') {
            return $log;
        }

        $meta = $log->meta ?? [];
        $body = (string) ($meta['body'] ?? '');
        $isHtml = (bool) ($meta['is_html'] ?? true);
        $cc = $meta['cc'] ?? [];
        $bcc = $meta['bcc'] ?? [];
        $attachmentMeta = is_array($meta['attachments'] ?? null) ? $meta['attachments'] : [];

        if ($body === '') {
            $log->update([
                'status' => 'failed',
                'error_message' => 'Missing email body in queue payload.',
            ]);

            throw new PermanentEmailDeliveryException('Missing email body in queue payload.');
        }

        $log->loadMissing('externalIntegration');

        if ($isHtml) {
            $body = $this->branding->wrapHtml($body, $log->externalIntegration);
        } else {
            $body = $this->branding->wrapPlainText($body, $log->externalIntegration);
        }

        $loadedAttachments = $attachmentMeta === []
            ? []
            : $this->attachments->load($attachmentMeta);

        return $this->transmit(
            log: $log,
            to: $log->to,
            subject: $log->subject,
            body: $body,
            isHtml: $isHtml,
            providerId: $log->email_provider_id,
            cc: is_array($cc) ? $cc : [],
            bcc: is_array($bcc) ? $bcc : [],
            fromName: $this->branding->resolveFromName(
                $log->externalIntegration,
                $this->mailConfig->resolveFromIdentity(
                    $this->mailConfig->resolveProvider($log->email_provider_id),
                )['name'],
            ),
            markFailedOnError: false,
            attachmentPayload: $loadedAttachments,
            cleanupAttachmentMeta: $attachmentMeta,
        );
    }

    /**
     * Send immediately (admin tests / sync queue).
     *
     * @param  array<int, string>  $cc
     * @param  array<int, string>  $bcc
     * @param  list<array{filename: string, content: string, content_type: string, size: int}>  $attachmentPayload
     */
    public function send(
        string $to,
        string $subject,
        string $body,
        bool $isHtml = true,
        ?int $providerId = null,
        ?ExternalIntegration $integration = null,
        array $cc = [],
        array $bcc = [],
        ?string $source = null,
        ?string $senderIp = null,
        array $attachmentPayload = [],
    ): EmailLog {
        $provider = $this->mailConfig->resolveProvider($providerId);

        $meta = [
            'body' => $body,
            'is_html' => $isHtml,
            'cc' => $cc,
            'bcc' => $bcc,
        ];

        if ($source !== null) {
            $meta['source'] = $source;
        }

        if ($senderIp !== null && $senderIp !== '') {
            $meta['sender_ip'] = $senderIp;
        }

        $log = EmailLog::query()->create([
            'email_provider_id' => $provider->id,
            'external_integration_id' => $integration?->id,
            'to' => $to,
            'subject' => MailHeaderSanitizer::line($subject, 500),
            'status' => 'pending',
            'driver' => $provider->driver->value,
            'meta' => $meta,
        ]);

        $stored = [];
        if ($attachmentPayload !== []) {
            $stored = $this->attachments->store($attachmentPayload, $log->id);
            $meta['attachments'] = $stored;
            $meta['attachment_count'] = count($stored);
            $log->update(['meta' => $meta]);
        }

        return $this->transmit(
            log: $log,
            to: $to,
            subject: $subject,
            body: $isHtml
                ? $this->branding->wrapHtml($body, $integration)
                : $this->branding->wrapPlainText($body, $integration),
            isHtml: $isHtml,
            providerId: $provider->id,
            cc: $cc,
            bcc: $bcc,
            fromName: $this->branding->resolveFromName(
                $integration,
                $this->mailConfig->resolveFromIdentity($provider)['name'],
            ),
            attachmentPayload: $attachmentPayload,
            cleanupAttachmentMeta: $stored,
        );
    }

    /**
     * Re-queue a failed (or pending) email that still has its body stored in meta.
     */
    public function retry(EmailLog $log): EmailLog
    {
        if ($log->status === 'sent') {
            throw new RuntimeException('This email was already sent successfully.');
        }

        $meta = $log->meta ?? [];
        $body = (string) ($meta['body'] ?? '');

        if ($body === '') {
            throw new RuntimeException(
                'This email cannot be resent because its body was not stored. Only queued deliveries can be retried.'
            );
        }

        $log->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        SendEmailJob::dispatch($log->id);

        return $log->fresh() ?? $log;
    }

    /**
     * Re-queue all failed emails that still have a stored body.
     * Optional client filter: external_integration_id or "none" for admin/internal.
     *
     * @return array{queued: int, skipped: int}
     */
    public function retryAllFailed(?string $externalIntegrationId = null): array
    {
        $query = EmailLog::query()
            ->where('status', 'failed')
            ->orderBy('id');

        if ($externalIntegrationId !== null && $externalIntegrationId !== '') {
            if ($externalIntegrationId === 'none' || $externalIntegrationId === '0') {
                $query->whereNull('external_integration_id');
            } else {
                $query->where('external_integration_id', (int) $externalIntegrationId);
            }
        }

        $queued = 0;
        $skipped = 0;

        $query->chunkById(100, function ($logs) use (&$queued, &$skipped) {
            foreach ($logs as $log) {
                try {
                    $this->retry($log);
                    $queued++;
                } catch (RuntimeException) {
                    $skipped++;
                }
            }
        });

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    public function testProvider(EmailProvider $provider, string $to, ?string $senderIp = null): EmailLog
    {
        $subject = 'Email Server test — '.$provider->name.' — '.now()->toDateTimeString();
        $body = '<p>This is a test email from the <strong>Email Server</strong> admin panel.</p>'
            .'<p>Provider: <code>'.e($provider->name).'</code> ('.e($provider->driver->value).')</p>';

        return $this->send(
            to: $to,
            subject: $subject,
            body: $body,
            isHtml: true,
            providerId: $provider->id,
            integration: null,
            cc: [],
            bcc: [],
            source: 'admin_test',
            senderIp: $senderIp,
        );
    }

    /**
     * @param  array<int, string>  $cc
     * @param  array<int, string>  $bcc
     * @param  list<array{filename: string, content: string, content_type: string, size?: int}>  $attachmentPayload
     * @param  list<array{path?: string}>  $cleanupAttachmentMeta
     */
    private function transmit(
        EmailLog $log,
        string $to,
        string $subject,
        string $body,
        bool $isHtml,
        ?int $providerId,
        array $cc,
        array $bcc,
        ?string $fromName = null,
        bool $markFailedOnError = true,
        array $attachmentPayload = [],
        array $cleanupAttachmentMeta = [],
    ): EmailLog {
        $provider = $this->mailConfig->resolveProvider($providerId);
        $this->mailConfig->purgeExchangeClient();
        $from = $this->mailConfig->resolveFromIdentity($provider);
        $subject = MailHeaderSanitizer::line($subject, 500);
        $fromName = MailHeaderSanitizer::line((string) ($fromName ?: ($from['name'] ?? '')), 255);

        try {
            if ($provider->driver === EmailDriver::Smtp) {
                $this->phpMailerSmtp->send(
                    provider: $provider,
                    to: $to,
                    subject: $subject,
                    body: $body,
                    isHtml: $isHtml,
                    fromAddress: (string) ($from['address'] ?? ''),
                    fromName: $fromName,
                    cc: $cc,
                    bcc: $bcc,
                    attachments: $attachmentPayload,
                );
            } else {
                $mailer = $this->mailConfig->applyProvider($provider);

                Mail::mailer($mailer)->send([], [], function (Message $message) use ($to, $subject, $body, $isHtml, $from, $cc, $bcc, $fromName, $attachmentPayload) {
                    $message->to($to)->subject($subject);

                    if (! empty($from['address'])) {
                        $message->from($from['address'], $fromName ?: $from['name']);
                    }

                    foreach ($cc as $address) {
                        $message->cc($address);
                    }

                    foreach ($bcc as $address) {
                        $message->bcc($address);
                    }

                    if ($isHtml) {
                        $message->html($body);
                    } else {
                        $message->text($body);
                    }

                    foreach ($attachmentPayload as $attachment) {
                        $message->attachData(
                            $attachment['content'],
                            $attachment['filename'],
                            ['mime' => $attachment['content_type'] ?? 'application/octet-stream'],
                        );
                    }
                });
            }

            $log->update(['status' => 'sent', 'error_message' => null]);

            if ($cleanupAttachmentMeta !== []) {
                $this->attachments->deleteStored($cleanupAttachmentMeta);
                $meta = $log->meta ?? [];
                unset($meta['attachments']);
                $meta['attachment_count'] = count($cleanupAttachmentMeta);
                $meta['attachments_delivered'] = true;
                $log->update(['meta' => $meta]);
            }
        } catch (Throwable $e) {
            if ($markFailedOnError) {
                $log->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            throw $e;
        }

        return $log->fresh();
    }
}
