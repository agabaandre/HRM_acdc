<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Settings\Models\PortalEmailProvider;
use Modules\Settings\Services\EmailProvidersService;
use RuntimeException;

class PortalMailer
{
    public function __construct(
        private EmailProvidersService $providers,
    ) {}

    /**
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     * @param  list<string>  $bcc
     */
    public function send(
        string|array $to,
        string $subject,
        string $htmlBody,
        array $attachments = [],
        ?PortalEmailProvider $provider = null,
        array $bcc = [],
    ): void {
        $resolved = $this->providers->resolveForSend($provider);
        $driver = $resolved['provider']->driver;
        $config = $resolved['config'];
        $fromAddress = $resolved['from_address'];
        $fromName = $resolved['from_name'];
        $recipients = is_array($to) ? $to : [$to];
        $bcc = array_values(array_unique(array_filter(array_map('trim', $bcc))));

        match ($driver) {
            'exchange' => $this->sendExchange($recipients, $subject, $htmlBody, $fromAddress, $fromName, $attachments, $config, $bcc),
            'smtp', 'zoho' => $this->sendSmtp($recipients, $subject, $htmlBody, $fromAddress, $fromName, $attachments, $config, $driver, $bcc),
            'log' => $this->sendLog($recipients, $subject, $htmlBody, $attachments, $bcc),
            'sendgrid' => $this->sendSendgrid($recipients, $subject, $htmlBody, $fromAddress, $fromName, $attachments, $config, $bcc),
            'mailgun' => $this->sendMailgun($recipients, $subject, $htmlBody, $fromAddress, $fromName, $attachments, $config, $bcc),
            'postmark' => $this->sendPostmark($recipients, $subject, $htmlBody, $fromAddress, $fromName, $attachments, $config, $bcc),
            'mailjet' => $this->sendMailjet($recipients, $subject, $htmlBody, $fromAddress, $fromName, $attachments, $config, $bcc),
            'ses' => $this->sendViaLaravelMailer('ses', $recipients, $subject, $htmlBody, $fromAddress, $fromName, $attachments, $bcc),
            'api' => $this->sendCustomApi($recipients, $subject, $htmlBody, $fromAddress, $fromName, $attachments, $config, $bcc),
            'azure', 'google' => throw new RuntimeException(
                ucfirst($driver).' send is configured for storage; use Exchange/SMTP for payslip delivery, or set a working default provider.'
            ),
            default => throw new RuntimeException("Unsupported mail driver: {$driver}"),
        };
    }

    /**
     * @param  list<string>  $to
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     * @param  array<string, mixed>  $config
     */
    /**
     * @param  list<string>  $to
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     * @param  array<string, mixed>  $config
     * @param  list<string>  $bcc
     */
    private function sendExchange(
        array $to,
        string $subject,
        string $htmlBody,
        string $fromAddress,
        string $fromName,
        array $attachments,
        array $config,
        array $bcc = [],
    ): void {
        $client = new ExchangeGraphMailClient($config);
        $client->send(
            count($to) === 1 ? $to[0] : $to,
            $subject,
            $htmlBody,
            $fromAddress,
            $fromName,
            [],
            $bcc,
            $attachments,
        );
    }

    /**
     * @param  list<string>  $to
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     * @param  array<string, mixed>  $config
     */
    /**
     * @param  list<string>  $to
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     * @param  array<string, mixed>  $config
     * @param  list<string>  $bcc
     */
    private function sendSmtp(
        array $to,
        string $subject,
        string $htmlBody,
        string $fromAddress,
        string $fromName,
        array $attachments,
        array $config,
        string $driver,
        array $bcc = [],
    ): void {
        if ($driver === 'zoho' && ($config['mode'] ?? 'smtp') === 'api') {
            throw new RuntimeException('Zoho API mode is not implemented for payslip attachments; switch mode to smtp.');
        }

        $encryption = (string) ($config['encryption'] ?? 'tls');
        config([
            'mail.mailers.portal_smtp' => [
                'transport' => 'smtp',
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => (int) ($config['port'] ?? 587),
                'encryption' => $encryption === 'none' ? null : $encryption,
                'username' => $config['username'] ?? null,
                'password' => $config['password'] ?? null,
                'timeout' => null,
            ],
        ]);

        $this->sendViaLaravelMailer('portal_smtp', $to, $subject, $htmlBody, $fromAddress, $fromName, $attachments, $bcc);
    }

    /**
     * @param  list<string>  $to
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     * @param  list<string>  $bcc
     */
    private function sendViaLaravelMailer(
        string $mailer,
        array $to,
        string $subject,
        string $htmlBody,
        string $fromAddress,
        string $fromName,
        array $attachments,
        array $bcc = [],
    ): void {
        Mail::mailer($mailer)->html($htmlBody, function ($message) use ($to, $subject, $fromAddress, $fromName, $attachments, $bcc) {
            $message->to($to)->subject($subject)->from($fromAddress, $fromName);
            if ($bcc !== []) {
                $message->bcc($bcc);
            }
            foreach ($attachments as $attachment) {
                $message->attachData(
                    $attachment['content'],
                    $attachment['name'],
                    ['mime' => $attachment['content_type'] ?? 'application/octet-stream'],
                );
            }
        });
    }

    /**
     * @param  list<string>  $to
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     * @param  list<string>  $bcc
     */
    private function sendLog(array $to, string $subject, string $htmlBody, array $attachments, array $bcc = []): void
    {
        Log::info('PortalMailer log transport', [
            'to' => $to,
            'bcc' => $bcc,
            'subject' => $subject,
            'html_length' => strlen($htmlBody),
            'attachments' => array_map(fn ($a) => $a['name'], $attachments),
        ]);
    }

    /**
     * @param  list<string>  $to
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     * @param  array<string, mixed>  $config
     * @param  list<string>  $bcc
     */
    private function sendSendgrid(array $to, string $subject, string $htmlBody, string $fromAddress, string $fromName, array $attachments, array $config, array $bcc = []): void
    {
        $personalization = ['to' => array_map(fn ($e) => ['email' => $e], $to)];
        if ($bcc !== []) {
            $personalization['bcc'] = array_map(fn ($e) => ['email' => $e], $bcc);
        }
        $payload = [
            'personalizations' => [$personalization],
            'from' => ['email' => $fromAddress, 'name' => $fromName],
            'subject' => $subject,
            'content' => [['type' => 'text/html', 'value' => $htmlBody]],
        ];
        if ($attachments !== []) {
            $payload['attachments'] = array_map(fn ($a) => [
                'content' => base64_encode($a['content']),
                'filename' => $a['name'],
                'type' => $a['content_type'] ?? 'application/octet-stream',
                'disposition' => 'attachment',
            ], $attachments);
        }

        $base = rtrim((string) ($config['base_url'] ?? 'https://api.sendgrid.com/v3'), '/');
        $res = Http::withToken((string) ($config['api_key'] ?? ''))
            ->post($base.'/mail/send', $payload);

        if (! $res->successful()) {
            throw new RuntimeException('SendGrid send failed: '.$res->body());
        }
    }

    /**
     * @param  list<string>  $to
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     * @param  array<string, mixed>  $config
     * @param  list<string>  $bcc
     */
    private function sendMailgun(array $to, string $subject, string $htmlBody, string $fromAddress, string $fromName, array $attachments, array $config, array $bcc = []): void
    {
        $domain = (string) ($config['domain'] ?? '');
        $region = ($config['region'] ?? 'us') === 'eu' ? 'api.eu.mailgun.net' : 'api.mailgun.net';
        $request = Http::withBasicAuth('api', (string) ($config['api_key'] ?? ''))
            ->asMultipart()
            ->attach('from', "{$fromName} <{$fromAddress}>")
            ->attach('to', implode(',', $to))
            ->attach('subject', $subject)
            ->attach('html', $htmlBody);
        if ($bcc !== []) {
            $request = $request->attach('bcc', implode(',', $bcc));
        }

        foreach ($attachments as $i => $attachment) {
            $request = $request->attach(
                "attachment[{$i}]",
                $attachment['content'],
                $attachment['name'],
            );
        }

        $res = $request->post("https://{$region}/v3/{$domain}/messages");
        if (! $res->successful()) {
            throw new RuntimeException('Mailgun send failed: '.$res->body());
        }
    }

    /**
     * @param  list<string>  $to
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     * @param  array<string, mixed>  $config
     * @param  list<string>  $bcc
     */
    private function sendPostmark(array $to, string $subject, string $htmlBody, string $fromAddress, string $fromName, array $attachments, array $config, array $bcc = []): void
    {
        $payload = [
            'From' => "{$fromName} <{$fromAddress}>",
            'To' => implode(',', $to),
            'Subject' => $subject,
            'HtmlBody' => $htmlBody,
            'MessageStream' => $config['message_stream'] ?? 'outbound',
        ];
        if ($bcc !== []) {
            $payload['Bcc'] = implode(',', $bcc);
        }
        if ($attachments !== []) {
            $payload['Attachments'] = array_map(fn ($a) => [
                'Name' => $a['name'],
                'Content' => base64_encode($a['content']),
                'ContentType' => $a['content_type'] ?? 'application/octet-stream',
            ], $attachments);
        }

        $res = Http::withHeaders([
            'X-Postmark-Server-Token' => (string) ($config['server_token'] ?? ''),
            'Accept' => 'application/json',
        ])->post('https://api.postmarkapp.com/email', $payload);

        if (! $res->successful()) {
            throw new RuntimeException('Postmark send failed: '.$res->body());
        }
    }

    /**
     * @param  list<string>  $to
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     * @param  array<string, mixed>  $config
     * @param  list<string>  $bcc
     */
    private function sendMailjet(array $to, string $subject, string $htmlBody, string $fromAddress, string $fromName, array $attachments, array $config, array $bcc = []): void
    {
        $message = [
            'From' => ['Email' => $fromAddress, 'Name' => $fromName],
            'To' => array_map(fn ($e) => ['Email' => $e], $to),
            'Subject' => $subject,
            'HTMLPart' => $htmlBody,
            'Attachments' => array_map(fn ($a) => [
                'ContentType' => $a['content_type'] ?? 'application/octet-stream',
                'Filename' => $a['name'],
                'Base64Content' => base64_encode($a['content']),
            ], $attachments),
        ];
        if ($bcc !== []) {
            $message['Bcc'] = array_map(fn ($e) => ['Email' => $e], $bcc);
        }
        $payload = ['Messages' => [$message]];

        $res = Http::withBasicAuth((string) ($config['api_key'] ?? ''), (string) ($config['secret_key'] ?? ''))
            ->post('https://api.mailjet.com/v3.1/send', $payload);

        if (! $res->successful()) {
            throw new RuntimeException('Mailjet send failed: '.$res->body());
        }
    }

    /**
     * @param  list<string>  $to
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     * @param  array<string, mixed>  $config
     * @param  list<string>  $bcc
     */
    private function sendCustomApi(array $to, string $subject, string $htmlBody, string $fromAddress, string $fromName, array $attachments, array $config, array $bcc = []): void
    {
        $base = rtrim((string) ($config['base_url'] ?? ''), '/');
        $path = (string) ($config['send_path'] ?? '/mail/send');
        $url = $base.(str_starts_with($path, '/') ? $path : '/'.$path);

        $request = Http::asJson();
        $scheme = $config['auth_scheme'] ?? 'bearer';
        $apiKey = (string) ($config['api_key'] ?? '');
        if ($scheme === 'bearer') {
            $request = $request->withToken($apiKey);
        } elseif ($scheme === 'api_key_header') {
            $request = $request->withHeaders([(string) ($config['auth_header'] ?? 'X-Api-Key') => $apiKey]);
        } else {
            $request = $request->withBasicAuth($apiKey, '');
        }

        $res = $request->post($url, [
            'to' => $to,
            'bcc' => $bcc,
            'subject' => $subject,
            'html' => $htmlBody,
            'from' => ['email' => $fromAddress, 'name' => $fromName],
            'attachments' => array_map(fn ($a) => [
                'name' => $a['name'],
                'content' => base64_encode($a['content']),
                'content_type' => $a['content_type'] ?? 'application/octet-stream',
            ], $attachments),
        ]);

        if (! $res->successful()) {
            throw new RuntimeException('Custom API mail send failed: '.$res->body());
        }
    }
}
