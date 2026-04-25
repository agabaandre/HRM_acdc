<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SendExchangeTestEmailCommand extends Command
{
    protected $signature = 'exchange:test-send
                            {email : Recipient work email}
                            {--subject= : Optional subject line}';

    protected $description = 'Send a single test email via Microsoft Graph (ExchangeOAuth), same path as notification jobs';

    public function handle(): int
    {
        $email = $this->argument('email');
        $prefix = env('MAIL_SUBJECT_PREFIX', 'APM');
        $subject = $this->option('subject') ?: "{$prefix}: Exchange Graph test " . now()->toIso8601String();

        $config = config('exchange-email');

        require_once app_path('ExchangeEmailService/ExchangeOAuth.php');

        $oauth = new \AgabaandreOffice365\ExchangeEmailService\ExchangeOAuth(
            $config['tenant_id'],
            $config['client_id'],
            $config['client_secret'],
            $config['redirect_uri'] ?? 'http://localhost:8000/oauth/callback',
            'https://graph.microsoft.com/.default',
            'client_credentials'
        );

        if (!$oauth->isConfigured()) {
            $this->error('Exchange OAuth is not configured (EXCHANGE_TENANT_ID / EXCHANGE_CLIENT_ID / EXCHANGE_CLIENT_SECRET).');

            return self::FAILURE;
        }

        $body = '<p>This is a <strong>test message</strong> from <code>php artisan exchange:test-send</code> using Microsoft Graph.</p>'
            . '<p>Sent at: ' . e(now()->toDateTimeString()) . '</p>';

        try {
            $ok = $oauth->sendEmail(
                $email,
                $subject,
                $body,
                true,
                null,
                null,
                [],
                [],
                []
            );
        } catch (\Throwable $e) {
            $this->error('Graph send threw: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (!$ok) {
            $this->error('Graph send returned failure. lastSendError: ' . ($oauth->lastSendError ?? '(none)'));

            return self::FAILURE;
        }

        $this->info("Sent OK to {$email}");

        return self::SUCCESS;
    }
}
