<?php

namespace App\Services;

use App\Models\HelpdeskLicense;
use App\Models\ItSetting;
use Illuminate\Support\Facades\Log;

class LicenseExpiryAlertService
{
    public function __construct(private PortalMailer $mailer) {}

    /**
     * @return array{enabled:bool,checked:int,sent:int,skipped:int,errors:list<string>}
     */
    public function sendDue(): array
    {
        $result = [
            'enabled' => ItSetting::licenseExpiryAlertEnabled(),
            'checked' => 0,
            'sent' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        if (! $result['enabled']) {
            return $result;
        }

        $intervalDays = ItSetting::licenseExpiryAlertIntervalDays();
        $licensesUrl = rtrim((string) config('app.url'), '/').'/licenses';

        $licenses = HelpdeskLicense::query()
            ->whereNotNull('expiry_date')
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'inactive');
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

            $result['checked']++;
            $lastSent = $license->expiry_alert_last_sent_at;
            if ($lastSent !== null && $lastSent->gt(now()->subDays($intervalDays))) {
                $result['skipped']++;
                continue;
            }

            $person = $license->responsible_person;
            $email = trim((string) ($person['email'] ?? ''));
            $name = trim((string) ($person['name'] ?? ''));
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $result['skipped']++;
                $result['errors'][] = $license->name.' has no responsible person email.';
                continue;
            }

            $status = ($expiry['is_expired'] ?? false) ? 'expired' : 'expiring soon';
            $subject = 'IT — License '.$status.': '.$license->name;
            $html = view('emails.license-expiry-alert', [
                'license' => $license,
                'expiry' => $expiry,
                'recipientName' => $name !== '' ? $name : 'there',
                'licensesUrl' => $licensesUrl,
            ])->render();

            try {
                $this->mailer->send($email, $subject, $html);
                $license->expiry_alert_last_sent_at = now();
                $license->saveQuietly();
                $result['sent']++;
            } catch (\Throwable $e) {
                $result['errors'][] = $license->name.': '.$e->getMessage();
                Log::warning('it.license_expiry_alert_failed', [
                    'license_id' => $license->id,
                    'email' => $email,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        ItSetting::putValue(ItSetting::KEY_LICENSE_EXPIRY_ALERT_LAST_RUN_AT, now()->toIso8601String());

        return $result;
    }
}
