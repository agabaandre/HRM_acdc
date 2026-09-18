<?php

namespace App\Services;

use AgabaandreOffice365\ExchangeEmailService\ExchangeOAuth;
use RuntimeException;

class ExchangeGraphMailClient
{
    private ?ExchangeOAuth $oauth = null;

    /**
     * @param  array<string, mixed>|null  $credentials  Optional override (tenant_id, client_id, …)
     */
    public function __construct(private readonly ?array $credentials = null) {}

    /**
     * @param  string|array<int, string>  $to
     * @param  array<int, string>  $cc
     * @param  array<int, string>  $bcc
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     */
    public function send(
        string|array $to,
        string $subject,
        string $htmlBody,
        ?string $fromEmail = null,
        ?string $fromName = null,
        array $cc = [],
        array $bcc = [],
        array $attachments = [],
    ): void {
        $oauth = $this->oauth();

        if (! $oauth->isConfigured()) {
            throw new RuntimeException(
                'Exchange OAuth is not configured. Set EXCHANGE_TENANT_ID, EXCHANGE_CLIENT_ID, and EXCHANGE_CLIENT_SECRET, or configure an Exchange email provider in Settings.'
            );
        }

        if ($oauth->getAuthMethod() === ExchangeOAuth::AUTH_CLIENT_CREDENTIALS) {
            $oauth->getClientCredentialsToken();
        } elseif (! $oauth->hasValidToken()) {
            $oauth->refreshAccessToken();
        }

        $fromEmail ??= (string) (config('exchange-email.from_email') ?: config('mail.from.address'));
        $fromName ??= (string) (config('exchange-email.from_name') ?: config('mail.from.name'));

        $ok = $oauth->sendEmail(
            $to,
            $subject,
            $htmlBody,
            true,
            $fromEmail,
            $fromName,
            $cc,
            $bcc,
            $attachments,
        );

        if (! $ok) {
            throw new RuntimeException(
                'Microsoft Graph mail send failed: '.($oauth->lastSendError ?? 'unknown error')
            );
        }
    }

    private function oauth(): ExchangeOAuth
    {
        if ($this->oauth !== null) {
            return $this->oauth;
        }

        $c = $this->credentials ?? [];

        $this->oauth = new ExchangeOAuth(
            $c['tenant_id'] ?? config('exchange-email.tenant_id'),
            $c['client_id'] ?? config('exchange-email.client_id'),
            $c['client_secret'] ?? config('exchange-email.client_secret'),
            $c['redirect_uri'] ?? config('exchange-email.redirect_uri'),
            $c['scope'] ?? config('exchange-email.scope'),
            $c['auth_method'] ?? config('exchange-email.auth_method'),
        );

        return $this->oauth;
    }
}
