<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('staff_auth_is_https_request')) {
    function staff_auth_is_https_request(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }

        return false;
    }
}

if (!function_exists('staff_auth_rate_limit_dir')) {
    function staff_auth_rate_limit_dir(): string
    {
        $dir = APPPATH . 'cache/login_attempts';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        return $dir;
    }
}

if (!function_exists('staff_auth_rate_limit_key')) {
    function staff_auth_rate_limit_key(string $email, string $ip): string
    {
        return hash('sha256', strtolower(trim($email)) . '|' . trim($ip));
    }
}

if (!function_exists('staff_auth_rate_limit_read')) {
    function staff_auth_rate_limit_read(string $key): array
    {
        $path = staff_auth_rate_limit_dir() . DIRECTORY_SEPARATOR . $key . '.json';
        if (!is_file($path)) {
            return ['count' => 0, 'locked_until' => 0];
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return ['count' => 0, 'locked_until' => 0];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['count' => 0, 'locked_until' => 0];
        }

        return [
            'count' => isset($data['count']) ? (int) $data['count'] : 0,
            'locked_until' => isset($data['locked_until']) ? (int) $data['locked_until'] : 0,
        ];
    }
}

if (!function_exists('staff_auth_rate_limit_write')) {
    function staff_auth_rate_limit_write(string $key, array $data): void
    {
        $path = staff_auth_rate_limit_dir() . DIRECTORY_SEPARATOR . $key . '.json';
        @file_put_contents($path, json_encode($data), LOCK_EX);
    }
}

if (!function_exists('staff_login_rate_limit_max_attempts')) {
    function staff_login_rate_limit_max_attempts(): int
    {
        return 5;
    }
}

if (!function_exists('staff_login_rate_limit_window_seconds')) {
    function staff_login_rate_limit_window_seconds(): int
    {
        return 900;
    }
}

if (!function_exists('staff_login_rate_limit_is_allowed')) {
    function staff_login_rate_limit_is_allowed(string $email, string $ip): bool
    {
        $key = staff_auth_rate_limit_key($email, $ip);
        $data = staff_auth_rate_limit_read($key);
        if (!empty($data['locked_until']) && $data['locked_until'] > time()) {
            return false;
        }
        if (!empty($data['locked_until']) && $data['locked_until'] <= time()) {
            staff_auth_rate_limit_write($key, ['count' => 0, 'locked_until' => 0]);
        }

        return (int) $data['count'] < staff_login_rate_limit_max_attempts();
    }
}

if (!function_exists('staff_login_rate_limit_lockout_remaining')) {
    function staff_login_rate_limit_lockout_remaining(string $email, string $ip): int
    {
        $key = staff_auth_rate_limit_key($email, $ip);
        $data = staff_auth_rate_limit_read($key);
        if (empty($data['locked_until'])) {
            return 0;
        }
        $remaining = (int) $data['locked_until'] - time();

        return $remaining > 0 ? $remaining : 0;
    }
}

if (!function_exists('staff_login_rate_limit_record_failure')) {
    function staff_login_rate_limit_record_failure(string $email, string $ip): void
    {
        $key = staff_auth_rate_limit_key($email, $ip);
        $data = staff_auth_rate_limit_read($key);
        $count = (int) $data['count'] + 1;
        $lockedUntil = 0;
        if ($count >= staff_login_rate_limit_max_attempts()) {
            $lockedUntil = time() + staff_login_rate_limit_window_seconds();
            $count = staff_login_rate_limit_max_attempts();
        }
        staff_auth_rate_limit_write($key, [
            'count' => $count,
            'locked_until' => $lockedUntil,
        ]);
    }
}

if (!function_exists('staff_login_rate_limit_clear')) {
    function staff_login_rate_limit_clear(string $email, string $ip): void
    {
        $key = staff_auth_rate_limit_key($email, $ip);
        $path = staff_auth_rate_limit_dir() . DIRECTORY_SEPARATOR . $key . '.json';
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

if (!function_exists('staff_auth_oauth_state_create')) {
    function staff_auth_oauth_state_create(): string
    {
        $ci = &get_instance();
        $state = bin2hex(random_bytes(16));
        $ci->session->set_userdata('oauth_state', $state);

        return $state;
    }
}

if (!function_exists('staff_auth_oauth_state_validate')) {
    function staff_auth_oauth_state_validate(?string $state): bool
    {
        $ci = &get_instance();
        $expected = (string) $ci->session->userdata('oauth_state');
        $ci->session->unset_userdata('oauth_state');
        if ($expected === '' || $state === null || $state === '') {
            return false;
        }

        return hash_equals($expected, (string) $state);
    }
}

if (!function_exists('staff_auth_regenerate_session')) {
    function staff_auth_regenerate_session(): void
    {
        $ci = &get_instance();
        if (method_exists($ci->session, 'sess_regenerate')) {
            $ci->session->sess_regenerate(true);
        }
    }
}
