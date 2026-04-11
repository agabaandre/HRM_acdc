<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('staff_jobs_schedule_defaults')) {
    /**
     * Default wall-clock schedule for jobs/run/tick (must stay in sync with Run.php comments).
     * Note: staff_profile_completion_reminder runs the job daily; Jobs::notify_staff_incomplete_profile_extension dedupes per staff every 2 days.
     */
    function staff_jobs_schedule_defaults()
    {
        return [
            'send_instant_mails'                => true,
            'send_mails_interval_minutes'       => 15,
            'performance_notifications'         => ['hour' => 7, 'minute' => 0],
            'performance_approval_reminder'     => ['hour' => 10, 'minute' => 0],
            'cron_register'                     => false,
            'mark_due_contracts'                => ['hour' => 23, 'minute' => 0],
            'staff_birthday'                    => ['hour' => 3, 'minute' => 0],
            'staff_profile_completion_reminder' => ['hour' => 8, 'minute' => 30],
            'manage_accounts_hourly_minute'     => 0,
        ];
    }
}

if (!function_exists('staff_jobs_schedule_path')) {
    function staff_jobs_schedule_path()
    {
        return APPPATH . 'cache/staff_jobs_schedule.json';
    }
}

if (!function_exists('staff_jobs_schedule_normalize_key')) {
    /**
     * @param mixed $value
     * @return mixed|null null = skip key (unknown)
     */
    function staff_jobs_schedule_normalize_key($key, $value, array $defaults)
    {
        if (!array_key_exists($key, $defaults)) {
            return null;
        }
        if ($key === 'send_instant_mails') {
            return (bool) $value;
        }
        if ($key === 'send_mails_interval_minutes') {
            $n = (int) $value;

            return max(0, min(1440, $n));
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
            $h = max(0, min(23, $h));
            $mm = max(0, min(59, $mm));

            return ['hour' => $h, 'minute' => $mm];
        }

        return $defaults[$key];
    }
}

if (!function_exists('staff_jobs_schedule_resolved')) {
    function staff_jobs_schedule_resolved()
    {
        $defaults = staff_jobs_schedule_defaults();
        $path = staff_jobs_schedule_path();
        if (!is_readable($path)) {
            return $defaults;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return $defaults;
        }
        $stored = json_decode($raw, true);
        if (!is_array($stored)) {
            return $defaults;
        }
        foreach ($defaults as $k => $_) {
            if (!array_key_exists($k, $stored)) {
                continue;
            }
            $norm = staff_jobs_schedule_normalize_key($k, $stored[$k], $defaults);
            if ($norm !== null) {
                $defaults[$k] = $norm;
            }
        }

        return $defaults;
    }
}

if (!function_exists('staff_jobs_schedule_write')) {
    function staff_jobs_schedule_write(array $schedule)
    {
        $defaults = staff_jobs_schedule_defaults();
        $out = [];
        foreach (array_keys($defaults) as $k) {
            if (!array_key_exists($k, $schedule)) {
                continue;
            }
            $norm = staff_jobs_schedule_normalize_key($k, $schedule[$k], $defaults);
            if ($norm !== null) {
                $out[$k] = $norm;
            }
        }
        $path = staff_jobs_schedule_path();
        $dir = dirname($path);
        if (!is_dir($dir) || !is_really_writable($dir)) {
            return false;
        }
        $json = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        return (bool) file_put_contents($path, $json, LOCK_EX);
    }
}

if (!function_exists('staff_jobs_schedule_from_post')) {
    function staff_jobs_schedule_from_post(array $post)
    {
        $defaults = staff_jobs_schedule_defaults();
        $out = $defaults;
        $out['send_instant_mails'] = !empty($post['send_instant_mails']);
        $out['send_mails_interval_minutes'] = isset($post['send_mails_interval_minutes'])
            ? max(0, min(1440, (int) $post['send_mails_interval_minutes']))
            : 0;

        $dailyKeys = [
            'performance_notifications',
            'performance_approval_reminder',
            'cron_register',
            'mark_due_contracts',
            'staff_birthday',
            'staff_profile_completion_reminder',
        ];
        foreach ($dailyKeys as $dk) {
            if (empty($post[$dk . '_enabled'])) {
                $out[$dk] = false;
            } else {
                $h = isset($post[$dk . '_hour']) ? (int) $post[$dk . '_hour'] : 0;
                $m = isset($post[$dk . '_minute']) ? (int) $post[$dk . '_minute'] : 0;
                $out[$dk] = ['hour' => max(0, min(23, $h)), 'minute' => max(0, min(59, $m))];
            }
        }

        $mah = $post['manage_accounts_hourly_minute'] ?? '';
        if ($mah === '' || $mah === null) {
            $out['manage_accounts_hourly_minute'] = null;
        } else {
            $mm = (int) $mah;
            $out['manage_accounts_hourly_minute'] = ($mm >= 0 && $mm <= 59) ? $mm : null;
        }

        return $out;
    }
}
