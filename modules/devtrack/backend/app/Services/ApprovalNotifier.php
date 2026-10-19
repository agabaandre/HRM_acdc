<?php

namespace App\Services;

use App\Models\ExternalIntegration;
use App\Models\User;
use App\Support\TrustedFrontendUrl;
use Throwable;

class ApprovalNotifier
{
    public function __construct(
        private readonly EmailDispatchService $dispatch,
    ) {}

    public function notifyAccountApproved(User $user): void
    {
        $email = trim((string) $user->email);
        if ($email === '') {
            return;
        }

        $appName = (string) config('app.name', 'Email Server');
        $loginUrl = rtrim(TrustedFrontendUrl::base(), '/').'/login';
        $name = e($user->name ?: 'there');
        $app = e($appName);
        $url = e($loginUrl);

        $this->queue(
            to: $email,
            subject: "{$appName} — your account has been approved",
            body: <<<HTML
<p>Hello {$name},</p>
<p>Your account on <strong>{$app}</strong> has been approved. You can sign in and complete authenticator setup if prompted.</p>
<p><a href="{$url}">Sign in</a></p>
HTML,
            source: 'account_approved',
        );
    }

    public function notifyAccountRejected(User $user, ?string $reason = null): void
    {
        $email = trim((string) $user->email);
        if ($email === '') {
            return;
        }

        $appName = (string) config('app.name', 'Email Server');
        $name = e($user->name ?: 'there');
        $app = e($appName);
        $reasonHtml = '';
        if (is_string($reason) && trim($reason) !== '') {
            $reasonHtml = '<p><strong>Reason:</strong> '.e(trim($reason)).'</p>';
        }

        $this->queue(
            to: $email,
            subject: "{$appName} — your account registration was not approved",
            body: <<<HTML
<p>Hello {$name},</p>
<p>Your account registration on <strong>{$app}</strong> was not approved. You will not be able to sign in with this account.</p>
{$reasonHtml}
<p>If you believe this was a mistake, contact your organisation administrator.</p>
HTML,
            source: 'account_rejected',
        );
    }

    public function notifyIntegrationApproved(ExternalIntegration $integration): void
    {
        $recipients = $this->integrationRecipientEmails($integration);
        if ($recipients === []) {
            return;
        }

        $appName = (string) config('app.name', 'Email Server');
        $integrationsUrl = rtrim(TrustedFrontendUrl::base(), '/').'/integrations';
        $clientName = e($integration->name);
        $clientId = e($integration->slug);
        $app = e($appName);
        $url = e($integrationsUrl);

        $body = <<<HTML
<p>Your integration client <strong>{$clientName}</strong> (<code>{$clientId}</code>) on <strong>{$app}</strong> has been approved and is now active.</p>
<p>Connecting systems can authenticate with this client's credentials.</p>
<p><a href="{$url}">View your clients</a></p>
HTML;

        foreach ($recipients as $to) {
            $this->queue(
                to: $to,
                subject: "{$appName} — integration “{$integration->name}” approved",
                body: $body,
                source: 'integration_approved',
            );
        }
    }

    public function notifyIntegrationRejected(ExternalIntegration $integration): void
    {
        $recipients = $this->integrationRecipientEmails($integration);
        if ($recipients === []) {
            return;
        }

        $appName = (string) config('app.name', 'Email Server');
        $integrationsUrl = rtrim(TrustedFrontendUrl::base(), '/').'/integrations';
        $clientName = e($integration->name);
        $clientId = e($integration->slug);
        $app = e($appName);
        $url = e($integrationsUrl);

        $body = <<<HTML
<p>Your integration client <strong>{$clientName}</strong> (<code>{$clientId}</code>) on <strong>{$app}</strong> has been disabled and can no longer authenticate.</p>
<p><a href="{$url}">View your clients</a></p>
HTML;

        foreach ($recipients as $to) {
            $this->queue(
                to: $to,
                subject: "{$appName} — integration “{$integration->name}” disabled",
                body: $body,
                source: 'integration_rejected',
            );
        }
    }

    /**
     * @return list<string>
     */
    private function integrationRecipientEmails(ExternalIntegration $integration): array
    {
        $integration->loadMissing('users:id,name,email');

        return $integration->users
            ->pluck('email')
            ->filter(fn ($email) => is_string($email) && trim($email) !== '')
            ->map(fn ($email) => trim((string) $email))
            ->unique()
            ->values()
            ->all();
    }

    private function queue(string $to, string $subject, string $body, string $source): void
    {
        try {
            $this->dispatch->queue(
                to: $to,
                subject: $subject,
                body: $body,
                isHtml: true,
                source: $source,
                senderIp: request()?->ip(),
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
