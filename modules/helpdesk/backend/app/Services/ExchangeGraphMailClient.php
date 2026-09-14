<?php

namespace App\Services;

use AgabaandreOffice365\ExchangeEmailService\ExchangeOAuth;
use RuntimeException;

class ExchangeGraphMailClient
{
    private ?ExchangeOAuth $oauth = null;

    /**
     * @param  string|array<int, string>  $to
     * @param  array<int, string>  $cc
     * @param  array<int, string>  $bcc
     */
    public function send(
        string|array $to,
        string $subject,
        string $htmlBody,
        ?string $fromEmail = null,
        ?string $fromName = null,
        array $cc = [],
        array $bcc = [],
    ): void {
        $oauth = $this->oauth();

        if (! $oauth->isConfigured()) {
            throw new RuntimeException(
                'Exchange OAuth is not configured. Set EXCHANGE_TENANT_ID, EXCHANGE_CLIENT_ID, and EXCHANGE_CLIENT_SECRET (copy from apm/.env).'
            );
        }

        if ($oauth->getAuthMethod() === ExchangeOAuth::AUTH_CLIENT_CREDENTIALS) {
            $oauth->getClientCredentialsToken();
        } elseif (! $oauth->hasValidToken()) {
            $oauth->refreshAccessToken();
        }

        $fromEmail ??= (string) config('mail.from.address');
        $fromName ??= (string) config('mail.from.name');

        $ok = $oauth->sendEmail(
            $to,
            $subject,
            $htmlBody,
            true,
            $fromEmail,
            $fromName,
            $cc,
            $bcc,
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

        $this->oauth = new ExchangeOAuth(
            config('helpdesk.exchange_tenant_id'),
            config('helpdesk.exchange_client_id'),
            config('helpdesk.exchange_client_secret'),
            config('helpdesk.exchange_redirect_uri'),
            config('helpdesk.exchange_scope'),
            config('helpdesk.exchange_auth_method'),
        );

        return $this->oauth;
    }
}
