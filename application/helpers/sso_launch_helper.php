<?php
if (! defined('BASEPATH') && ! defined('LARAVEL_START')) {
    exit('No direct script access allowed');
}

if (!function_exists('staff_sso_normalize_module_key')) {
    function staff_sso_normalize_module_key(string $moduleKey): string
    {
        $key = strtolower(trim($moduleKey));
        $aliases = [
            'helpdesk' => 'helpdesk_itsm',
            'finance' => 'finance_management',
            'apm' => 'approvals_management',
            'approvals' => 'approvals_management',
        ];

        return $aliases[$key] ?? $key;
    }
}

if (!function_exists('staff_sso_cache_dir')) {
    function staff_sso_cache_dir(): string
    {
        if (defined('APPPATH')) {
            $dir = rtrim(APPPATH, '/\\') . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'cbp_sso';
        } else {
            // Always resolve via this helper's location (staff/application/helpers/).
            $dir = dirname(__FILE__, 2) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'cbp_sso';
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        } elseif (!is_writable($dir)) {
            @chmod($dir, 0775);
        }

        return $dir;
    }
}

if (!function_exists('staff_sso_jwt_secret')) {
    function staff_sso_jwt_secret(): string
    {
        $envSecret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET');
        $envSecret = is_string($envSecret) ? trim($envSecret) : '';
        if ($envSecret !== '') {
            return $envSecret;
        }
        $ci = function_exists('get_instance') ? get_instance() : null;
        if ($ci !== null) {
            return trim((string) $ci->config->item('encryption_key'));
        }

        return '';
    }
}

if (!function_exists('staff_sso_base64url_encode')) {
    function staff_sso_base64url_encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

if (!function_exists('staff_sso_build_jwt')) {
    /**
     * Build cross-system SSO token as JWT (HS256).
     */
    function staff_sso_build_jwt(array $session): string
    {
        $secret = staff_sso_jwt_secret();
        if ($secret === '') {
            return base64_encode(json_encode($session));
        }

        $now = time();
        $payload = $session;
        $payload['iat'] = $now;
        $payload['exp'] = $now + 7200;

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $h = staff_sso_base64url_encode(json_encode($header));
        $p = staff_sso_base64url_encode(json_encode($payload));
        $sig = hash_hmac('sha256', $h . '.' . $p, $secret, true);

        return $h . '.' . $p . '.' . staff_sso_base64url_encode($sig);
    }
}

if (!function_exists('staff_sso_compact_claims')) {
    /**
     * Keep only SSO fields modules need — avoids huge JWTs from full CI session objects.
     *
     * @return array<string, mixed>
     */
    function staff_sso_compact_claims(array $session): array
    {
        $keys = [
            'staff_id', 'auth_staff_id', 'user_id', 'permissions', 'base_url',
            'work_email', 'email', 'private_email', 'mail', 'userPrincipalName',
            'name', 'fname', 'lname', 'title', 'role',
            'directorate_id', 'division_id', 'photo', 'helpdesk_role', 'helpdeskRole',
            'ci_token', 'SAPNO',
        ];
        $out = [];
        foreach ($keys as $key) {
            if (! array_key_exists($key, $session)) {
                continue;
            }
            $value = $session[$key];
            if ($value === null || $value === '') {
                continue;
            }
            $out[$key] = $value;
        }

        if (isset($out['permissions']) && is_string($out['permissions'])) {
            $out['permissions'] = array_values(array_filter(array_map('trim', explode(',', $out['permissions']))));
        }

        return $out;
    }
}

if (!function_exists('staff_sso_create_code')) {
    /**
     * Store a one-time SSO code (JWT never sent in URL).
     *
     * @return string 64-char hex code
     */
    function staff_sso_create_code(string $jwt, string $moduleKey, int $userId, int $ttlSeconds = 120): string
    {
        staff_sso_prune_expired_codes();
        $code = bin2hex(random_bytes(32));
        $path = staff_sso_cache_dir() . DIRECTORY_SEPARATOR . hash('sha256', $code) . '.json';
        $payload = [
            'jwt' => $jwt,
            'module_key' => staff_sso_normalize_module_key($moduleKey),
            'user_id' => $userId,
            'exp' => time() + $ttlSeconds,
            'created' => time(),
        ];
        @file_put_contents($path, json_encode($payload), LOCK_EX);

        return $code;
    }
}

if (!function_exists('staff_sso_prune_expired_codes')) {
    function staff_sso_prune_expired_codes(): void
    {
        $dir = staff_sso_cache_dir();
        $now = time();
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
            $raw = @file_get_contents($file);
            if ($raw === false || $raw === '') {
                @unlink($file);
                continue;
            }
            $data = json_decode($raw, true);
            if (!is_array($data) || empty($data['exp']) || (int) $data['exp'] < $now) {
                @unlink($file);
            }
        }
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.consuming') ?: [] as $file) {
            @unlink($file);
        }
    }
}

if (!function_exists('staff_sso_consume_version')) {
    function staff_sso_consume_version(): int
    {
        return 3;
    }
}

if (!function_exists('staff_sso_consume_code')) {
    /**
     * Redeem a one-time SSO code. Returns null if invalid/expired/already used.
     *
     * @param string|null $expectedModuleKey When set, code must have been issued for this module.
     * @return array{jwt:string,module_key:string,user_id:int}|null
     */
    function staff_sso_consume_code(?string $code, ?string $expectedModuleKey = null): ?array
    {
        if ($code === null || $code === '' || !preg_match('/^[a-f0-9]{64}$/i', $code)) {
            return null;
        }
        $path = staff_sso_cache_dir() . DIRECTORY_SEPARATOR . hash('sha256', $code) . '.json';
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['jwt']) || empty($data['exp']) || (int) $data['exp'] < time()) {
            @unlink($path);

            return null;
        }
        $moduleKey = staff_sso_normalize_module_key((string) ($data['module_key'] ?? ''));
        if ($expectedModuleKey !== null && $expectedModuleKey !== '') {
            $expectedModuleKey = staff_sso_normalize_module_key($expectedModuleKey);
            if ($moduleKey !== $expectedModuleKey) {
                return null;
            }
        }
        @unlink($path);

        return [
            'jwt' => (string) $data['jwt'],
            'module_key' => $moduleKey,
            'user_id' => (int) ($data['user_id'] ?? 0),
        ];
    }
}

if (!function_exists('staff_sso_is_local_host')) {
    function staff_sso_is_local_host(): bool
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';

        return $host !== '' && (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);
    }
}

if (!function_exists('staff_sso_accept_url_for_module')) {
    /**
     * POST target for a cbp_modules row that uses staff portal SSO.
     */
    function staff_sso_accept_url_for_module(object $row): ?string
    {
        $resolver = (string) ($row->target_resolver ?? '');
        $base = defined('LARAVEL_START') && function_exists('base_url') === false
            ? rtrim((string) (env('BASE_URL') ?: config('app.url')), '/')
            : rtrim(base_url(), '/');

        if ($resolver === 'staff_app_token') {
            $seg = trim((string) ($row->base_url ?? ''), '/');
            if ($seg === '') {
                return null;
            }
            if ($seg === 'apm') {
                return $base . '/apm/sso/accept';
            }
            if ($seg === 'finance') {
                return $base . '/finance/sso/accept';
            }
            if ($seg === 'helpdesk') {
                return $base . '/helpdesk/backend/sso/accept';
            }

            return $base . '/' . $seg . '/sso/accept';
        }

        if ($resolver === 'finance_host') {
            if (staff_sso_is_local_host()) {
                return $base . '/finance/sso/accept';
            }
            $scheme = staff_auth_is_https_request() ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $prod = trim((string) ($row->base_url_production ?? ''), '/');
            if ($prod !== '' && preg_match('#^https?://#i', $prod)) {
                return rtrim($prod, '/') . '/sso/accept';
            }

            return $scheme . '://' . $host . '/staff/finance/sso/accept';
        }

        if ($resolver === 'external_microservice') {
            $url = '';
            if (staff_sso_is_local_host()) {
                $url = trim((string) ($row->base_url_development ?? ''));
                if ($url === '') {
                    $url = trim((string) ($row->base_url ?? ''));
                }
            } else {
                $url = trim((string) ($row->base_url_production ?? ''));
                if ($url === '') {
                    $url = trim((string) ($row->base_url ?? ''));
                }
            }
            if ($url === '') {
                return null;
            }
            if (!preg_match('#^https?://#i', $url)) {
                $url = 'https://' . ltrim(preg_replace('#^[\\/]+#', '', $url), '/');
            }

            return rtrim($url, '/') . '/sso/accept';
        }

        return null;
    }
}

if (!function_exists('staff_sso_user_can_access_module')) {
    function staff_sso_user_can_access_module(object $user, object $row): bool
    {
        $perm = (string) ($row->permission_code ?? '');
        if ($perm === '') {
            return false;
        }
        $permList = isset($user->permissions) ? array_map('strval', (array) $user->permissions) : [];
        if (!in_array($perm, $permList, true)) {
            return false;
        }
        if ((int) ($row->is_production ?? 1) === 0 && (int) ($user->role ?? 0) !== 10) {
            return false;
        }

        return true;
    }
}

if (!function_exists('staff_sso_is_allowed_accept_url')) {
    /**
     * Allowlist SSO accept targets to same host / staff mount (blocks open redirects).
     */
    function staff_sso_is_allowed_accept_url(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '' || $requestHost === '') {
            return false;
        }
        $host = preg_replace('/:\d+$/', '', $host);
        $requestHost = preg_replace('/:\d+$/', '', $requestHost);
        if ($host !== $requestHost) {
            return false;
        }
        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($path === '' || strpos($path, '/sso/accept') === false) {
            return false;
        }

        return true;
    }
}

if (!function_exists('staff_sso_url_token_allowed')) {
    function staff_sso_url_token_allowed(): bool
    {
        $flag = $_ENV['SSO_ALLOW_URL_TOKEN'] ?? getenv('SSO_ALLOW_URL_TOKEN');
        if ($flag === false || $flag === null || $flag === '') {
            return (defined('ENVIRONMENT') && ENVIRONMENT !== 'production');
        }

        return in_array(strtolower(trim((string) $flag)), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('staff_sso_decode_jwt')) {
    /**
     * Decode Staff portal SSO JWT (HS256). Returns null when invalid or expired (unless allowed).
     *
     * @return array<string, mixed>|null
     */
    function staff_sso_decode_jwt(string $token, bool $allowExpired = false): ?array
    {
        $token = trim($token);
        $parts = explode('.', $token);
        $secret = staff_sso_jwt_secret();
        if (count($parts) !== 3 || $secret === '') {
            return null;
        }

        [$h, $p, $s] = $parts;
        $sig = staff_sso_base64url_encode(hash_hmac('sha256', $h . '.' . $p, $secret, true));
        if (!hash_equals($sig, $s)) {
            return null;
        }

        $payloadJson = base64_decode(strtr($p, '-_', '+/') . str_repeat('=', (4 - strlen($p) % 4) % 4));
        $payload = is_string($payloadJson) ? json_decode($payloadJson, true) : null;
        if (!is_array($payload)) {
            return null;
        }

        $exp = isset($payload['exp']) ? (int) $payload['exp'] : 0;
        if (!$allowExpired && $exp > 0 && $exp < time()) {
            return null;
        }

        return $payload;
    }
}

if (!function_exists('staff_sso_parse_bearer_token')) {
    /**
     * Parse Bearer token: JWT HS256 or legacy base64 JSON session blob.
     *
     * @return array<string, mixed>|null
     */
    function staff_sso_parse_bearer_token(string $token, bool $allowExpired = false): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $jwt = staff_sso_decode_jwt($token, $allowExpired);
        if ($jwt !== null) {
            return $jwt;
        }

        $decoded = base64_decode($token, true);
        $json = is_string($decoded) ? json_decode($decoded, true) : null;

        return is_array($json) ? $json : null;
    }
}

if (!function_exists('staff_sso_refresh_grace_seconds')) {
    function staff_sso_refresh_grace_seconds(): int
    {
        return 86400;
    }
}

if (!function_exists('staff_sso_jwt_may_refresh')) {
    function staff_sso_jwt_may_refresh(string $token): bool
    {
        $payload = staff_sso_decode_jwt($token, true);
        if ($payload === null) {
            return false;
        }
        $exp = isset($payload['exp']) ? (int) $payload['exp'] : 0;
        if ($exp <= 0) {
            return true;
        }

        return $exp >= (time() - staff_sso_refresh_grace_seconds());
    }
}
