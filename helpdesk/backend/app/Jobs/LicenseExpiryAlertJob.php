<?php

namespace App\Jobs;

use App\Mail\LicenseExpiryAlertMail;
use App\Models\HelpdeskLicense;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LicenseExpiryAlertJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        if (! HelpdeskSetting::licenseExpiryAlertEnabled()) {
            return;
        }

        $intervalDays = HelpdeskSetting::licenseExpiryAlertIntervalDays();
        $frontend = rtrim((string) config('helpdesk.frontend_url', 'http://localhost/staff/helpdesk'), '/');
        $licensesUrl = $frontend.'/tools/licenses';

        $adminEmails = User::query()
            ->whereHas('helpdeskProfile', function ($q) {
                $q->where('role', HelpdeskProfile::ROLE_ADMIN)
                    ->orWhere('grant_helpdesk_admin', true);
            })
            ->pluck('email')
            ->map(fn ($e) => trim((string) $e))
            ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();

        $licenses = HelpdeskLicense::query()
            ->whereNotNull('expiry_date')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'inactive');
            })
            ->get();

        foreach ($licenses as $license) {
            $expiry = $license->expiry;
            $inWindow = ($expiry['is_expiring_soon'] ?? false) || ($expiry['is_expired'] ?? false);
            if (! $inWindow) {
                if ($license->expiry_alert_last_sent_at !== null) {
                    $license->expiry_alert_last_sent_at = null;
                    $license->saveQuietly();
                }
                continue;
            }

            $lastSent = $license->expiry_alert_last_sent_at;
            if ($lastSent !== null && $lastSent->gt(now()->subDays($intervalDays))) {
                continue;
            }

            $recipients = $adminEmails;
            $responsibleEmail = trim((string) ($license->responsible_person['email'] ?? ''));
            if ($responsibleEmail !== '' && filter_var($responsibleEmail, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = $responsibleEmail;
            }
            $recipients = array_values(array_unique($recipients));
            if ($recipients === []) {
                continue;
            }

            $sentAny = false;
            foreach ($recipients as $email) {
                $name = $email === $responsibleEmail
                    ? (string) ($license->responsible_person['name'] ?? 'there')
                    : 'Helpdesk admin';

                try {
                    Mail::to($email)->send(new LicenseExpiryAlertMail(
                        $license,
                        $expiry,
                        $licensesUrl,
                        $name,
                    ));
                    $sentAny = true;
                } catch (\Throwable $e) {
                    Log::warning('helpdesk.license_expiry_alert_failed', [
                        'license_id' => $license->id,
                        'email' => $email,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            if ($sentAny) {
                $license->expiry_alert_last_sent_at = now();
                $license->saveQuietly();
            }
        }
    }
}
