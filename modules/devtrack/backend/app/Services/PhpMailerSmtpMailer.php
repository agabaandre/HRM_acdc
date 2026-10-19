<?php

namespace App\Services;

use App\Enums\EmailDriver;
use App\Models\EmailProvider;
use PHPMailer\PHPMailer\Exception as PhpMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

class PhpMailerSmtpMailer
{
    /**
     * Send via PHPMailer SMTP using the provider's encrypted config (with env fallbacks).
     *
     * @param  array<int, string>  $cc
     * @param  array<int, string>  $bcc
     * @param  list<array{filename: string, content: string, content_type?: string}>  $attachments
     *
     * @throws RuntimeException
     */
    public function send(
        EmailProvider $provider,
        string $to,
        string $subject,
        string $body,
        bool $isHtml,
        string $fromAddress,
        string $fromName,
        array $cc = [],
        array $bcc = [],
        array $attachments = [],
    ): void {
        if ($provider->driver !== EmailDriver::Smtp) {
            throw new RuntimeException('PHPMailer SMTP transport requires an SMTP provider.');
        }

        $settings = $this->resolveSettings($provider, $fromAddress);

        $mail = new PHPMailer(true);

        try {
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = PHPMailer::ENCODING_BASE64;
            $mail->isSMTP();
            $mail->Host = $settings['host'];
            $mail->Port = $settings['port'];
            $mail->Timeout = 45;
            $mail->SMTPKeepAlive = false;
            $mail->AuthType = 'LOGIN';

            // Use a real mailbox domain for EHLO / Message-ID — Docker container
            // hostnames (e.g. 3aa163459983) tank Gmail deliverability.
            $mail->Hostname = $this->resolveHeloHostname($settings['host'], $fromAddress);
            $mail->MessageID = sprintf(
                '<%s@%s>',
                bin2hex(random_bytes(16)),
                $mail->Hostname
            );

            if ($settings['username'] !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $settings['username'];
                $mail->Password = $settings['password'];
            } else {
                $mail->SMTPAuth = false;
            }

            if ($settings['encryption'] === 'ssl') {
                // Implicit TLS (SMTPS) — typical for cPanel / port 465.
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->SMTPAutoTLS = false;
            } elseif ($settings['encryption'] === 'tls') {
                // Explicit STARTTLS — typical for Office 365 / port 587.
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->SMTPAutoTLS = true;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            }

            // Allow corporate / cPanel certs that sometimes mismatch when
            // connecting by an alternate hostname.
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];

            $mail->setFrom($fromAddress, $fromName);
            $mail->Sender = $fromAddress;
            $mail->addAddress($to);

            foreach ($cc as $address) {
                $mail->addCC($address);
            }

            foreach ($bcc as $address) {
                $mail->addBCC($address);
            }

            $mail->Subject = $subject;

            if ($isHtml) {
                $mail->isHTML(true);
                $mail->Body = $body;
                $mail->AltBody = trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            } else {
                $mail->isHTML(false);
                $mail->Body = $body;
            }

            foreach ($attachments as $attachment) {
                $mail->addStringAttachment(
                    $attachment['content'],
                    $attachment['filename'],
                    PHPMailer::ENCODING_BASE64,
                    $attachment['content_type'] ?? 'application/octet-stream',
                );
            }

            if (! $mail->send()) {
                throw new RuntimeException(
                    'SMTP send failed: '.($mail->ErrorInfo !== '' ? $mail->ErrorInfo : 'unknown PHPMailer error')
                );
            }
        } catch (PhpMailerException $e) {
            $detail = trim($mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage());
            throw new RuntimeException(
                sprintf(
                    'SMTP send failed (%s:%d/%s, user=%s): %s',
                    $settings['host'],
                    $settings['port'],
                    $settings['encryption'] ?: 'none',
                    $settings['username'] !== '' ? $settings['username'] : '(none)',
                    $detail !== '' ? $detail : 'unknown PHPMailer error'
                ),
                previous: $e
            );
        }
    }

    /**
     * @return array{host: string, port: int, encryption: string, username: string, password: string}
     */
    public function resolveSettings(EmailProvider $provider, string $fromAddress = ''): array
    {
        $host = (string) ($provider->configValue('host') ?? '');

        if ($host === '') {
            throw new RuntimeException(
                'SMTP host is not configured for this provider. Set it under Email providers in the admin UI.'
            );
        }

        $port = (int) ($provider->configValue('port') ?: 587);

        $encryption = strtolower((string) ($provider->configValue('encryption') ?? ''));

        // Laravel-style scheme aliases if stored that way in provider config.
        $encryption = match ($encryption) {
            'smtps' => 'ssl',
            'smtp' => '',
            default => $encryption,
        };

        if ($encryption === '') {
            $encryption = match (true) {
                $port === 465 => 'ssl',
                in_array($port, [587, 2587], true) => 'tls',
                default => '',
            };
        }

        $username = (string) ($provider->configValue('username') ?? '');
        $password = (string) ($provider->configValue('password') ?? '');

        // Admins sometimes paste the hostname into Username. cPanel/Office365
        // need the mailbox address (usually the From address).
        if ($username === '' || strcasecmp($username, $host) === 0) {
            if (str_contains($fromAddress, '@')) {
                $username = $fromAddress;
            }
        }

        if ($fromAddress === '') {
            throw new RuntimeException(
                'From address is required. Set it on the email provider in the admin UI.'
            );
        }

        if ($username !== '' && $password === '') {
            throw new RuntimeException(
                'SMTP password is missing. Edit the provider, enter the mailbox password, and save.'
            );
        }

        return [
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'password' => $password,
        ];
    }

    /**
     * Prefer the From-domain (or SMTP host) for EHLO / Message-ID so Docker
     * container hostnames never appear on the wire.
     */
    private function resolveHeloHostname(string $smtpHost, string $fromAddress): string
    {
        $fromHost = '';
        if (str_contains($fromAddress, '@')) {
            $fromHost = strtolower(trim(substr($fromAddress, strrpos($fromAddress, '@') + 1)));
        }

        if ($fromHost !== '' && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $fromHost) === 1) {
            return $fromHost;
        }

        $smtpHost = strtolower(trim($smtpHost));
        if ($smtpHost !== '' && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $smtpHost) === 1) {
            return $smtpHost;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($appHost) && $appHost !== '' ? $appHost : 'localhost';
    }
}
