<?php

namespace App\Services;

use App\Models\User;
use App\Support\TrustedFrontendUrl;
use Throwable;

class PendingRegistrationNotifier
{
    public function __construct(
        private readonly EmailDispatchService $dispatch,
    ) {}

    public function notifyAdmins(User $registrant): void
    {
        $emails = User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->pluck('email')
            ->filter(fn ($email) => is_string($email) && $email !== '')
            ->values()
            ->all();

        $fallback = trim((string) env('ADMIN_EMAIL', ''));
        if ($fallback !== '') {
            $emails[] = $fallback;
        }

        $emails = array_values(array_unique($emails));
        if ($emails === []) {
            return;
        }

        $appName = (string) config('app.name', 'Email Server');
        $usersUrl = rtrim(TrustedFrontendUrl::base(), '/').'/users?tab=pending';
        $subject = "{$appName} — new account awaiting approval";
        $body = $this->emailBody($appName, $registrant, $usersUrl);

        foreach ($emails as $to) {
            try {
                $this->dispatch->queue(
                    to: $to,
                    subject: $subject,
                    body: $body,
                    isHtml: true,
                    source: 'registration_pending',
                    senderIp: request()?->ip(),
                );
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    private function emailBody(string $appName, User $registrant, string $usersUrl): string
    {
        $name = e($registrant->name);
        $email = e($registrant->email);
        $org = e((string) ($registrant->organisation ?: '—'));
        $phone = e((string) ($registrant->phone ?: '—'));
        $url = e($usersUrl);
        $app = e($appName);

        return <<<HTML
<p>A new account registration is waiting for administrator approval on <strong>{$app}</strong>.</p>
<ul>
  <li><strong>Name:</strong> {$name}</li>
  <li><strong>Email:</strong> {$email}</li>
  <li><strong>Organisation:</strong> {$org}</li>
  <li><strong>Phone:</strong> {$phone}</li>
</ul>
<p><a href="{$url}">Review pending accounts</a></p>
HTML;
    }
}
