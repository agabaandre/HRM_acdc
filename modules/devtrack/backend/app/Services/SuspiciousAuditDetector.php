<?php

namespace App\Services;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Heuristics for flagging suspicious admin audit activity
 * (auth brute-force bursts, inactive account probes, etc.).
 */
class SuspiciousAuditDetector
{
    /**
     * @param  array{
     *   event_type?: string|null,
     *   action?: string|null,
     *   ip_address?: string|null,
     *   user_email?: string|null,
     *   user_id?: int|null,
     *   target_table?: string|null,
     *   http_method?: string|null,
     *   new_values?: array<string, mixed>|null
     * }  $incoming
     * @return array{is_suspicious: bool, reasons: list<string>}
     */
    public function evaluate(array $incoming): array
    {
        if (! Schema::hasColumn('audit_logs', 'is_suspicious')) {
            return ['is_suspicious' => false, 'reasons' => []];
        }

        $reasons = [];
        $eventType = (string) ($incoming['event_type'] ?? '');
        $ip = trim((string) ($incoming['ip_address'] ?? ''));
        $email = strtolower(trim((string) ($incoming['user_email'] ?? '')));
        if ($email === '' && is_array($incoming['new_values'] ?? null)) {
            $email = strtolower(trim((string) ($incoming['new_values']['email'] ?? '')));
        }

        $windowMinutes = $this->windowMinutes();
        $since = Carbon::now()->subMinutes($windowMinutes);

        if ($eventType === 'auth_failed' || $eventType === 'auth_2fa_failed' || $eventType === 'client_auth_failed') {
            $label = match ($eventType) {
                'auth_2fa_failed' => '2FA failures',
                'client_auth_failed' => 'client auth failures',
                default => 'failed logins',
            };
            $reasons[] = match ($eventType) {
                'auth_2fa_failed' => 'Failed two-factor verification',
                'client_auth_failed' => 'Failed client authentication',
                default => 'Failed login attempt',
            };
            $ipThreshold = $this->authFailIpThreshold();
            $emailThreshold = $this->authFailEmailThreshold();

            if ($ip !== '') {
                $ipCount = 1 + AuditLog::query()
                    ->whereIn('event_type', ['auth_failed', 'auth_2fa_failed', 'client_auth_failed'])
                    ->where('ip_address', $ip)
                    ->where('created_at', '>=', $since)
                    ->count();

                if ($ipCount >= $ipThreshold) {
                    $reasons[] = "Multiple {$label} from IP {$ip} ({$ipCount} in {$windowMinutes}m)";
                }
            }

            if ($email !== '' && $eventType !== 'client_auth_failed') {
                $emailCount = 1 + AuditLog::query()
                    ->whereIn('event_type', ['auth_failed', 'auth_2fa_failed'])
                    ->whereRaw('LOWER(user_email) = ?', [$email])
                    ->where('created_at', '>=', $since)
                    ->count();

                if ($emailCount >= $emailThreshold) {
                    $reasons[] = "Multiple {$label} for account {$email} ({$emailCount} in {$windowMinutes}m)";
                }
            }

            if ($email !== '' && $eventType === 'client_auth_failed') {
                $clientCount = 1 + AuditLog::query()
                    ->where('event_type', 'client_auth_failed')
                    ->whereRaw('LOWER(user_email) = ?', [$email])
                    ->where('created_at', '>=', $since)
                    ->count();

                if ($clientCount >= $emailThreshold) {
                    $reasons[] = "Multiple client auth failures for client_id {$email} ({$clientCount} in {$windowMinutes}m)";
                }
            }
        }

        if ($eventType === 'client_auth_ip_denied') {
            $reasons[] = 'Client authentication blocked by IP allowlist';
        }

        if ($eventType === 'auth_failed_inactive') {
            $reasons[] = 'Login attempt against a deactivated account';
        }

        if ($eventType === 'auth_login' && $ip !== '') {
            $priorFails = AuditLog::query()
                ->whereIn('event_type', ['auth_failed', 'auth_2fa_failed'])
                ->where('ip_address', $ip)
                ->where('created_at', '>=', $since)
                ->count();

            if ($priorFails >= $this->authFailIpThreshold()) {
                $reasons[] = "Successful login after {$priorFails} auth failures from IP {$ip} in {$windowMinutes}m";
            }
        }

        if ($eventType === 'auth_password_reset' && $ip !== '') {
            $resetCount = 1 + AuditLog::query()
                ->where('event_type', 'auth_password_reset')
                ->where('ip_address', $ip)
                ->where('created_at', '>=', $since)
                ->count();

            if ($resetCount >= $this->passwordResetThreshold()) {
                $reasons[] = "Repeated password-reset activity from IP {$ip} ({$resetCount} in {$windowMinutes}m)";
            }
        }

        // Forgot-password requests (no login) — AuthController logs via password reset service path;
        // also catch auth_forgot bursts if logged.
        if ($eventType === 'auth_forgot' && $ip !== '') {
            $forgotCount = 1 + AuditLog::query()
                ->where('event_type', 'auth_forgot')
                ->where('ip_address', $ip)
                ->where('created_at', '>=', $since)
                ->count();

            if ($forgotCount >= $this->passwordResetThreshold()) {
                $reasons[] = "Repeated password-reset requests from IP {$ip} ({$forgotCount} in {$windowMinutes}m)";
            }
        }

        if (($incoming['http_method'] ?? '') === 'DELETE'
            && ($incoming['target_table'] ?? '') === 'users'
        ) {
            $reasons[] = 'User account deleted';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'is_suspicious' => $reasons !== [],
            'reasons' => $reasons,
        ];
    }

    /**
     * Mark prior related auth failures in the same window as suspicious.
     *
     * @param  list<string>  $reasons
     */
    public function escalateRelatedAuthFailures(?string $ip, ?string $email, array $reasons): void
    {
        if (! Schema::hasColumn('audit_logs', 'is_suspicious') || $reasons === []) {
            return;
        }

        $since = Carbon::now()->subMinutes($this->windowMinutes());
        $reasonText = implode('; ', $reasons);

        $query = AuditLog::query()
            ->whereIn('event_type', [
                'auth_failed',
                'auth_2fa_failed',
                'auth_failed_inactive',
                'client_auth_failed',
                'client_auth_ip_denied',
            ])
            ->where('created_at', '>=', $since);

        if ($ip === null && $email === null) {
            return;
        }

        $query->where(function ($q) use ($ip, $email): void {
            if ($ip) {
                $q->orWhere('ip_address', $ip);
            }
            if ($email) {
                $q->orWhereRaw('LOWER(user_email) = ?', [$email]);
            }
        });

        $query->update([
            'is_suspicious' => true,
            'suspicious_reasons' => $reasonText,
        ]);
    }

    public function windowMinutes(): int
    {
        return max(1, (int) config('services.audit_suspicious.window_minutes', 15));
    }

    public function authFailIpThreshold(): int
    {
        return max(2, (int) config('services.audit_suspicious.auth_fail_ip_threshold', 3));
    }

    public function authFailEmailThreshold(): int
    {
        return max(2, (int) config('services.audit_suspicious.auth_fail_email_threshold', 3));
    }

    public function passwordResetThreshold(): int
    {
        return max(2, (int) config('services.audit_suspicious.password_reset_threshold', 5));
    }
}
