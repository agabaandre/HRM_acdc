<?php

namespace Modules\Jobs\Services;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class StaffJobsScheduleService
{
    /**
     * Defaults for cron schedule (shared with CI3 staff_jobs_schedule_defaults).
     * Profile completion reminder is off by default.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'send_instant_mails' => true,
            'send_mails_interval_minutes' => 15,
            'performance_notifications' => ['hour' => 7, 'minute' => 0],
            'performance_approval_reminder' => ['hour' => 10, 'minute' => 0],
            'cron_register' => false,
            'mark_due_contracts' => ['hour' => 23, 'minute' => 0],
            'audit_extended_contracts' => ['hour' => 23, 'minute' => 5],
            'staff_birthday' => ['hour' => 3, 'minute' => 0],
            'staff_profile_completion_reminder' => false,
            'manage_accounts_hourly_minute' => 0,
            'apm_approver_staff_ids_cache_interval_minutes' => 60,
            'user_logs_prune_get_access' => ['hour' => 0, 'minute' => 0, 'weekday' => 2],
            'sync_pra_workplan' => ['hour' => 0, 'minute' => 5],
        ];
    }

    public function path(): string
    {
        return $this->staffRoot().'/application/cache/staff_jobs_schedule.json';
    }

    public function displayPath(): string
    {
        return 'application/cache/staff_jobs_schedule.json';
    }

    /**
     * @return array<string, mixed>
     */
    public function resolved(): array
    {
        $defaults = $this->defaults();
        $path = $this->path();
        if (! is_readable($path)) {
            return $defaults;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return $defaults;
        }
        $stored = json_decode($raw, true);
        if (! is_array($stored)) {
            return $defaults;
        }
        foreach ($defaults as $key => $_) {
            if (! array_key_exists($key, $stored)) {
                continue;
            }
            $norm = $this->normalizeKey($key, $stored[$key], $defaults);
            if ($norm !== null) {
                $defaults[$key] = $norm;
            }
        }

        return $defaults;
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    public function write(array $schedule): bool
    {
        $defaults = $this->defaults();
        $out = [];
        foreach (array_keys($defaults) as $key) {
            if (! array_key_exists($key, $schedule)) {
                continue;
            }
            $norm = $this->normalizeKey($key, $schedule[$key], $defaults);
            if ($norm !== null) {
                $out[$key] = $norm;
            }
        }

        $path = $this->path();
        $dir = dirname($path);
        if (! is_dir($dir) || ! is_writable($dir)) {
            return false;
        }

        $json = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $tmp = $path.'.tmp.'.getmypid();
        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            return false;
        }

        return @rename($tmp, $path);
    }

    /**
     * Build schedule array from SPA PUT body (enabled + hour/minute form).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function fromRequest(array $input): array
    {
        $defaults = $this->defaults();
        $out = $defaults;

        $out['send_instant_mails'] = ! empty($input['send_instant_mails']);
        $out['send_mails_interval_minutes'] = isset($input['send_mails_interval_minutes'])
            ? max(0, min(1440, (int) $input['send_mails_interval_minutes']))
            : 0;

        if (array_key_exists('apm_approver_staff_ids_cache_interval_minutes', $input)) {
            $out['apm_approver_staff_ids_cache_interval_minutes'] = max(
                0,
                min(1440, (int) $input['apm_approver_staff_ids_cache_interval_minutes']),
            );
        }

        $dailyKeys = [
            'performance_notifications',
            'performance_approval_reminder',
            'cron_register',
            'mark_due_contracts',
            'audit_extended_contracts',
            'staff_birthday',
            'staff_profile_completion_reminder',
            'user_logs_prune_get_access',
            'sync_pra_workplan',
        ];
        foreach ($dailyKeys as $key) {
            $enabled = ! empty($input[$key.'_enabled']) || ! empty($input[$key]['enabled']);
            if (! $enabled) {
                $out[$key] = false;
                continue;
            }
            $spec = is_array($input[$key] ?? null) ? $input[$key] : [];
            $h = (int) ($spec['hour'] ?? $input[$key.'_hour'] ?? 0);
            $m = (int) ($spec['minute'] ?? $input[$key.'_minute'] ?? 0);
            $row = [
                'hour' => max(0, min(23, $h)),
                'minute' => max(0, min(59, $m)),
            ];
            if ($key === 'user_logs_prune_get_access') {
                $wd = (int) ($spec['weekday'] ?? $input[$key.'_weekday'] ?? 2);
                $row['weekday'] = max(0, min(6, $wd));
            }
            $out[$key] = $row;
        }

        $mah = $input['manage_accounts_hourly_minute'] ?? '';
        if ($mah === '' || $mah === null || $mah === false) {
            $out['manage_accounts_hourly_minute'] = null;
        } else {
            $mm = (int) $mah;
            $out['manage_accounts_hourly_minute'] = ($mm >= 0 && $mm <= 59) ? $mm : null;
        }

        return $out;
    }

    /**
     * @return array<string, array{label: string, help: string, weekday_select?: bool}>
     */
    public function dailyJobsMeta(): array
    {
        return [
            'staff_profile_completion_reminder' => [
                'label' => 'Profile completion reminder',
                'help' => 'Email staff (eligible contracts) who are missing extended profile fields. Off by default.',
            ],
            'staff_birthday' => [
                'label' => 'Staff birthday',
                'help' => 'Birthday notifications.',
            ],
            'mark_due_contracts' => [
                'label' => 'Mark due contracts',
                'help' => 'Updates contract due status.',
            ],
            'audit_extended_contracts' => [
                'label' => 'Audit extended contracts',
                'help' => 'Clears stale due/expired reminders after contracts are extended.',
            ],
            'performance_notifications' => [
                'label' => 'Performance notifications (PPA / Mid / End)',
                'help' => 'Queues supervisor reminder emails for PPA, midterm, and endterm.',
            ],
            'performance_approval_reminder' => [
                'label' => 'Performance approval reminders',
                'help' => 'Pending performance approval reminders to supervisors.',
            ],
            'cron_register' => [
                'label' => 'Cron register bundle',
                'help' => 'Legacy bundle (manage accounts only; birthday and contracts use dedicated schedules).',
            ],
            'user_logs_prune_get_access' => [
                'label' => 'Prune user_logs GET access',
                'help' => 'Weekly: deletes user_logs rows where http_method is GET.',
                'weekday_select' => true,
            ],
            'sync_pra_workplan' => [
                'label' => 'Sync PRA workplan',
                'help' => 'Daily: pulls Africa CDC PRA public workplan into staff-portal workplan tables.',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, commands: list<string>}>
     */
    public function instantJobs(): array
    {
        return [
            'notify_staff_profile_extension' => [
                'label' => 'Profile completion reminder emails',
                'commands' => ['jobs:staff-profile-completion-reminder'],
            ],
            'staff_birthday' => [
                'label' => 'Staff birthday',
                'commands' => ['jobs:staff-birthday', 'jobs:send-instant-mails'],
            ],
            'mark_due_contracts' => [
                'label' => 'Mark due contracts',
                'commands' => ['jobs:mark-due-contracts'],
            ],
            'audit_extended_contracts' => [
                'label' => 'Audit extended contracts',
                'commands' => ['jobs:audit-extended-contracts'],
            ],
            'cron_register' => [
                'label' => 'Cron register (bundle)',
                'commands' => ['jobs:manage-accounts'],
            ],
            'send_instant_mails' => [
                'label' => 'Instant mail queue (one pass)',
                'commands' => ['jobs:send-instant-mails'],
            ],
            'send_mails' => [
                'label' => 'Full mail queue (one pass)',
                'commands' => ['jobs:send-mails'],
            ],
            'manage_accounts' => [
                'label' => 'Manage accounts',
                'commands' => ['jobs:manage-accounts'],
            ],
            'performance_approval_reminder' => [
                'label' => 'Performance approval reminders',
                'commands' => ['jobs:performance-approval-reminder'],
            ],
            'performance_notifications_bundle' => [
                'label' => 'Performance notifications (PPA, Midterm, Endterm)',
                'commands' => ['jobs:performance-notifications'],
            ],
            'prune_user_logs_get_access' => [
                'label' => 'Prune user_logs GET access rows',
                'commands' => ['jobs:prune-user-logs-get-access'],
            ],
            'sync_pra_workplan' => [
                'label' => 'Sync PRA workplan',
                'commands' => ['workplan:sync-pra'],
            ],
        ];
    }

    /**
     * @return array{ok: bool, output: string, label: string}
     */
    public function runNow(string $jobKey): array
    {
        $defs = $this->instantJobs();
        if ($jobKey === '' || ! isset($defs[$jobKey])) {
            throw new RuntimeException('Unknown job.');
        }

        $chunks = [];
        foreach ($defs[$jobKey]['commands'] as $command) {
            $exit = Artisan::call($command);
            $chunks[] = trim(Artisan::output());
            if ($exit !== 0) {
                return [
                    'ok' => false,
                    'output' => implode("\n", array_filter($chunks)),
                    'label' => $defs[$jobKey]['label'],
                ];
            }
        }

        return [
            'ok' => true,
            'output' => implode("\n", array_filter($chunks)),
            'label' => $defs[$jobKey]['label'],
        ];
    }

    /**
     * @param  array<string, mixed>  $defaults
     */
    public function normalizeKey(string $key, mixed $value, array $defaults): mixed
    {
        if (! array_key_exists($key, $defaults)) {
            return null;
        }
        if ($key === 'send_instant_mails') {
            return (bool) $value;
        }
        if ($key === 'send_mails_interval_minutes' || $key === 'apm_approver_staff_ids_cache_interval_minutes') {
            return max(0, min(1440, (int) $value));
        }
        if ($key === 'manage_accounts_hourly_minute') {
            if ($value === null || $value === '' || $value === false) {
                return null;
            }
            $m = (int) $value;

            return ($m >= 0 && $m <= 59) ? $m : null;
        }
        if ($value === false || $value === '0' || $value === 0) {
            return false;
        }
        if (is_array($value)) {
            $h = isset($value['hour']) ? (int) $value['hour'] : 0;
            $mm = isset($value['minute']) ? (int) $value['minute'] : 0;
            $out = [
                'hour' => max(0, min(23, $h)),
                'minute' => max(0, min(59, $mm)),
            ];
            if (isset($defaults[$key]) && is_array($defaults[$key]) && array_key_exists('weekday', $defaults[$key])) {
                $wd = isset($value['weekday']) ? (int) $value['weekday'] : (int) $defaults[$key]['weekday'];
                $out['weekday'] = max(0, min(6, $wd));
            }

            return $out;
        }

        return $defaults[$key];
    }

    protected function staffRoot(): string
    {
        // staff-portal/backend → staff-portal → staff
        return dirname(base_path(), 2);
    }
}
