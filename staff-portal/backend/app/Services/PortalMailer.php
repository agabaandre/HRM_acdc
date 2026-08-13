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
     */
    public function send(
        string|array $to,
        string $subject,
        string $htmlBody,
        array $attachments = [],
        ?PortalEmailProvider $provider = null,
    ): void {
        $resolved = $this->providers->resolveForSend($provider);
        $driver = $resolved['provider']->driver;
        $config = $resolved['config'];
        $fromAddress = $resolved['from_address'];
        $fromName = $resolved['from_name'];
        $recipients = is_array($to) ? $to : [$to];

        match ($driver) {
            'exchange' => $this->sendExchange($recipients, $subject, $htmlBody, $fromAddress, $fromName, $attachments, $config),
            'smtp', 'zoho' => $this->sendSmtp($recipients, $subject, $htmlBody, $fromAddress, $fromName, $attachments, $config, $driver),
            'log' => $this->sendLog($recipients, $subject, $htmlBody, $attachments),
            'sendgrid' => $this->sendSendgrid($recipients, $subject, $htmlBody, $fromAddress, $fromName, $attachments, $config),
            'mailgun' => $this->sendMailgun($recipients, $subject, $htmlBody, $fromAddress, $fromName, $attachments, $config),
            'postmark' => $this->sendPostmark($recipients, $subject, $htmlBody, $fromAddress, $fromName, $attachments, $config),
            'mailjet' => $this->sendMailjet($recipients, $subject, $htmlBody, $fromAddress, $fromName, $attachments, $config),
            'ses' => $this->sendViaLaravelMailer('ses', $recipients, $subject, $htmlBody, $fromAddress, $fromName, $attachments),
            'api' => $this->sendCustomApi($recipients, $subject, $htmlBody, $fromAddress, $fromName, $attachments, $config),
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
    private function sendExchange(
        array $to,
        string $subject,
        string $htmlBody,
        string $fromAddress,
        string $fromName,
        array $attachments,
        array $config,
    ): void {
        $client = new ExchangeGraphMailClient($config);
        $client->send(
            count($to) === 1 ? $to[0] : $to,
            $subject,
            $htmlBody,
            $fromAddress,
            $fromName,
            [],
            [],
            $attachments,
        );
    }

    /**
     * @param  list<string>  $to
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     * @param  array<string, mixed>  $config
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

        $this->sendViaLaravelMailer('portal_smtp', $to, $subject, $htmlBody, $fromAddress, $fromName, $attachments);
    }

    /**
     * @param  list<string>  $to
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     */
    private function sendViaLaravelMailer(
        string $mailer,
        array $to,
        string $subject,
        string $htmlBody,
        string $fromAddress,
        string $fromName,
        array $attachments,
    ): void {
        Mail::mailer($mailer)->html($htmlBody, function ($message) use ($to, $subject, $fromAddress, $fromName, $attachments) {
            $message->to($to)->subject($subject)->from($fromAddress, $fromName);
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
     */
    private function sendLog(array $to, string $subject, string $htmlBody, array $attachments): void
    {
        Log::info('PortalMailer log transport', [
            'to' => $to,
            'subject' => $subject,
            'html_length' => strlen($htmlBody),
            'attachments' => array_map(fn ($a) => $a['name'], $attachments),
        ]);
    }

    /**
     * @param  list<string>  $to
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     * @param  array<string, mixed>  $config
     */
    private function sendSendgrid(array $to, string $subject, string $htmlBody, string $fromAddress, string $fromName, array $attachments, array $config): void
    {
        $payload = [
            'personalizations' => [['to' => array_map(fn ($e) => ['email' => $e], $to)]],
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
     */
    private function sendMailgun(array $to, string $subject, string $htmlBody, string $fromAddress, string $fromName, array $attachments, array $config): void
    {
        $domain = (string) ($config['domain'] ?? '');
        $region = ($config['region'] ?? 'us') === 'eu' ? 'api.eu.mailgun.net' : 'api.mailgun.net';
        $request = Http::withBasicAuth('api', (string) ($config['api_key'] ?? ''))
            ->asMultipart()
            ->attach('from', "{$fromName} <{$fromAddress}>")
            ->attach('to', implode(',', $to))
            ->attach('subject', $subject)
            ->attach('html', $htmlBody);

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
     */
    private function sendPostmark(array $to, string $subject, string $htmlBody, string $fromAddress, string $fromName, array $attachments, array $config): void
    {
        $payload = [
            'From' => "{$fromName} <{$fromAddress}>",
            'To' => implode(',', $to),
            'Subject' => $subject,
            'HtmlBody' => $htmlBody,
            'MessageStream' => $config['message_stream'] ?? 'outbound',
        ];
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
     */
    private function sendMailjet(array $to, string $subject, string $htmlBody, string $fromAddress, string $fromName, array $attachments, array $config): void
    {
        $payload = [
            'Messages' => [[
                'From' => ['Email' => $fromAddress, 'Name' => $fromName],
                'To' => array_map(fn ($e) => ['Email' => $e], $to),
                'Subject' => $subject,
                'HTMLPart' => $htmlBody,
                'Attachments' => array_map(fn ($a) => [
                    'ContentType' => $a['content_type'] ?? 'application/octet-stream',
                    'Filename' => $a['name'],
                    'Base64Content' => base64_encode($a['content']),
                ], $attachments),
            ]],
        ];

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
     */
    private function sendCustomApi(array $to, string $subject, string $htmlBody, string $fromAddress, string $fromName, array $attachments, array $config): void
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
