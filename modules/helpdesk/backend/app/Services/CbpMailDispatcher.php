<?php

namespace App\Services;

use App\Support\StaffApiBaseUrl;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Testing\Fakes\MailFake;
use Staff\Shared\StaffPortalMailClient;

/**
 * Helpdesk bridge: prefer Share mail hub, fall back to Laravel Mail.
 */
class CbpMailDispatcher
{
    public function sendMailable(string|array $to, Mailable $mailable): void
    {
        if (Mail::getFacadeRoot() instanceof MailFake) {
            Mail::to($to)->send($mailable);

            return;
        }

        $mode = strtolower(trim((string) env('STAFF_MAIL_DISPATCH', 'auto')));
        if ($mode === 'local') {
            Mail::to($to)->send($mailable);

            return;
        }

        $html = $mailable->render();
        $subject = (string) ($mailable->envelope()->subject ?? $mailable->subject ?? 'Notification');
        $attachments = $this->extractMailableAttachments($mailable);

        $client = $this->makeClient(
            $mode,
            function (string|array $recipients, string $subj, string $body, array $options = []) use ($mailable): void {
                Mail::html($body, function ($message) use ($recipients, $subj, $mailable, $options): void {
                    $message->to($recipients)->subject($subj);
                    $from = $mailable->envelope()->from;
                    if ($from) {
                        $message->from($from->address, $from->name);
                    }
                    foreach ($options['attachments'] ?? [] as $attachment) {
                        if (! is_array($attachment)) {
                            continue;
                        }
                        $bytes = $attachment['content'] ?? null;
                        if ((! is_string($bytes) || $bytes === '') && isset($attachment['content_base64'])) {
                            $decoded = base64_decode((string) $attachment['content_base64'], true);
                            $bytes = $decoded !== false ? $decoded : null;
                        }
                        if (! is_string($bytes) || $bytes === '') {
                            continue;
                        }
                        $message->attachData(
                            $bytes,
                            (string) ($attachment['name'] ?? $attachment['filename'] ?? 'attachment'),
                            ['mime' => (string) ($attachment['content_type'] ?? 'application/octet-stream')],
                        );
                    }
                });
            }
        );

        $options = [];
        if ($attachments !== []) {
            $options['attachments'] = $attachments;
        }
        $client->send($to, $subject, $html, $options);
    }

    /**
     * @return list<array{name: string, content: string, content_type: string}>
     */
    private function extractMailableAttachments(Mailable $mailable): array
    {
        $out = [];

        foreach ($mailable->rawAttachments ?? [] as $raw) {
            if (! is_array($raw) || empty($raw['data'])) {
                continue;
            }
            $out[] = [
                'name' => (string) ($raw['name'] ?? 'attachment'),
                'content' => (string) $raw['data'],
                'content_type' => (string) ($raw['options']['mime'] ?? 'application/octet-stream'),
            ];
        }

        foreach ($mailable->diskAttachments ?? [] as $disk) {
            if (! is_array($disk) || empty($disk['path'])) {
                continue;
            }
            $diskName = (string) ($disk['disk'] ?? 'local');
            $path = (string) $disk['path'];
            try {
                $bytes = \Illuminate\Support\Facades\Storage::disk($diskName)->get($path);
            } catch (\Throwable) {
                continue;
            }
            if (! is_string($bytes) || $bytes === '') {
                continue;
            }
            $out[] = [
                'name' => (string) ($disk['name'] ?? basename($path)),
                'content' => $bytes,
                'content_type' => (string) ($disk['options']['mime'] ?? 'application/octet-stream'),
            ];
        }

        return $out;
    }

    /**
     * @param  callable(string|array, string, string, array): void  $localSender
     */
    private function makeClient(string $mode, callable $localSender): StaffPortalMailClient
    {
        $cfg = config('helpdesk.staff_api', []);
        $base = StaffApiBaseUrl::resolve((string) ($cfg['base_url'] ?? 'http://127.0.0.1/staff/backend'));

        return new StaffPortalMailClient(
            baseUrl: $base,
            token: isset($cfg['token']) ? (string) $cfg['token'] : null,
            username: isset($cfg['username']) ? (string) $cfg['username'] : null,
            password: isset($cfg['password']) ? (string) $cfg['password'] : null,
            dispatch: $mode === 'portal' ? 'portal' : 'auto',
            configKey: env('STAFF_MAIL_CONFIG_KEY') ?: (string) config('app.key'),
            localSender: $localSender,
        );
    }
}
