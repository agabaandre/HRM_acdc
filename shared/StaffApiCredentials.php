<?php

namespace Staff\Shared;

use Throwable;

/**
 * Resolve Staff Share API credentials: DB (if set) → env → portal defaults.
 * Also probes connectivity (JWT then static token).
 */
final class StaffApiCredentials
{
    public const DEFAULT_TOKEN = 'YWZyY2FjZGNzdGFmZnRyYWNrZXI';

    public const KEY_BASE_URL = 'staff_api_base_url';

    public const KEY_USERNAME = 'staff_api_username';

    public const KEY_PASSWORD = 'staff_api_password';

    public const KEY_TOKEN = 'staff_api_token';

    /**
     * @return array{
     *   base_url: string,
     *   username: string,
     *   password: string,
     *   token: string,
     *   sources: array{base_url: string, username: string, password: string, token: string}
     * }
     */
    public static function resolve(?ModuleSettingsBag $bag = null): array
    {
        $env = static function (string $key, string $default = ''): string {
            $candidates = [];
            if (function_exists('env')) {
                $candidates[] = env($key);
            }
            $candidates[] = $_ENV[$key] ?? null;
            $candidates[] = $_SERVER[$key] ?? null;
            $g = getenv($key);
            if ($g !== false) {
                $candidates[] = $g;
            }
            foreach ($candidates as $v) {
                if (is_string($v) && trim($v) !== '') {
                    return trim($v);
                }
            }
            // Last resort: staff root .env (portal may omit STAFF_API_* in its own file).
            if (function_exists('staff_root_env_path') && function_exists('staff_parse_env_file')) {
                $root = staff_root_env_path();
                if ($root !== '') {
                    $vars = staff_parse_env_file($root);
                    $rv = $vars[$key] ?? '';
                    if (is_string($rv) && trim($rv) !== '') {
                        return trim($rv);
                    }
                }
            }

            return $default;
        };

        $pick = static function (string $dbKey, array $envKeys, string $default = '') use ($bag, $env): array {
            if ($bag instanceof ModuleSettingsBag && $bag->has($dbKey)) {
                return ['value' => trim((string) $bag->get($dbKey)), 'source' => 'db'];
            }
            foreach ($envKeys as $ek) {
                $v = $env($ek, '');
                if ($v !== '') {
                    return ['value' => $v, 'source' => 'env'];
                }
            }
            if ($default !== '') {
                return ['value' => $default, 'source' => 'default'];
            }

            return ['value' => '', 'source' => 'unset'];
        };

        $base = $pick(self::KEY_BASE_URL, ['STAFF_API_INTERNAL_BASE_URL', 'STAFF_API_BASE_URL', 'BASE_URL'], 'http://127.0.0.1/staff/backend');
        $user = $pick(self::KEY_USERNAME, ['STAFF_API_USERNAME']);
        $pass = $pick(self::KEY_PASSWORD, ['STAFF_API_PASSWORD']);
        $token = $pick(self::KEY_TOKEN, ['STAFF_API_TOKEN'], self::DEFAULT_TOKEN);

        $baseUrl = self::normalizeBaseUrl((string) $base['value']);

        return [
            'base_url' => $baseUrl,
            'username' => (string) $user['value'],
            'password' => (string) $pass['value'],
            'token' => (string) $token['value'],
            'sources' => [
                'base_url' => (string) $base['source'],
                'username' => (string) $user['source'],
                'password' => (string) $pass['source'],
                'token' => (string) $token['source'],
            ],
        ];
    }

    public static function client(?ModuleSettingsBag $bag = null, int $timeoutSeconds = 120): StaffShareHttp
    {
        $r = self::resolve($bag);

        return StaffShareHttp::fromConfig([
            'base_url' => $r['base_url'],
            'username' => $r['username'],
            'password' => $r['password'],
            'token' => $r['token'],
        ], $timeoutSeconds);
    }

    /**
     * UI snapshot (secrets masked).
     *
     * @return array<string, mixed>
     */
    public static function snapshot(?ModuleSettingsBag $bag = null, string $moduleLabel = 'Module'): array
    {
        $resolved = self::resolve($bag);
        $dbBase = $bag?->get(self::KEY_BASE_URL);
        $dbUser = $bag?->get(self::KEY_USERNAME);
        $dbToken = $bag?->get(self::KEY_TOKEN);

        return [
            'module' => $moduleLabel,
            'settings' => [
                'staff_api_base_url' => $dbBase !== null && trim($dbBase) !== '' ? trim($dbBase) : '',
                'staff_api_username' => $dbUser !== null && trim($dbUser) !== '' ? trim($dbUser) : '',
                'staff_api_password' => '',
                'staff_api_token' => '',
            ],
            'resolved' => [
                'base_url' => $resolved['base_url'],
                'username' => $resolved['username'],
                'password_configured' => $resolved['password'] !== '',
                'token_configured' => $resolved['token'] !== '',
                'token_preview' => $resolved['token'] !== '' ? (substr($resolved['token'], 0, 4).'…('.strlen($resolved['token']).' chars)') : '',
            ],
            'sources' => $resolved['sources'],
            'db_overrides' => [
                'base_url' => $bag?->has(self::KEY_BASE_URL) ?? false,
                'username' => $bag?->has(self::KEY_USERNAME) ?? false,
                'password' => $bag?->has(self::KEY_PASSWORD) ?? false,
                'token' => $bag?->has(self::KEY_TOKEN) ?? false,
            ],
            'portal_email_url' => self::portalBase().'/settings/email-servers',
            'portal_staff_api_url' => self::portalBase().'/settings/staff-api',
            'docs_url' => rtrim($resolved['base_url'], '/').'/share/docs',
        ];
    }

    /**
     * Persist DB overrides. Empty password/token keeps existing; clear_* removes DB override.
     *
     * @param  array<string, mixed>  $input
     */
    public static function persist(ModuleSettingsBag $bag, array $input): string
    {
        $map = [
            self::KEY_BASE_URL => 'staff_api_base_url',
            self::KEY_USERNAME => 'staff_api_username',
        ];
        foreach ($map as $dbKey => $inputKey) {
            if (array_key_exists($inputKey, $input)) {
                $v = trim((string) ($input[$inputKey] ?? ''));
                $bag->set($dbKey, $v === '' ? null : $v);
            }
        }

        if (! empty($input['clear_password'])) {
            $bag->set(self::KEY_PASSWORD, null);
        } elseif (array_key_exists('staff_api_password', $input)) {
            $pw = (string) ($input['staff_api_password'] ?? '');
            if ($pw !== '') {
                $bag->set(self::KEY_PASSWORD, $pw);
            }
        }

        if (! empty($input['clear_token'])) {
            $bag->set(self::KEY_TOKEN, null);
        } elseif (array_key_exists('staff_api_token', $input)) {
            $tok = trim((string) ($input['staff_api_token'] ?? ''));
            if ($tok !== '') {
                $bag->set(self::KEY_TOKEN, $tok);
            }
        }

        return 'Staff API credentials saved. Non-empty DB values override env; clear a field (or clear flags) to fall back to env / portal defaults.';
    }

    /**
     * @return array{success: bool, status: string, mode: string, details: list<string>, message: string, base_url: string}
     */
    public static function probe(?ModuleSettingsBag $bag = null, ?array $override = null): array
    {
        $resolved = self::resolve($bag);
        if (is_array($override)) {
            foreach (['base_url', 'username', 'password', 'token'] as $k) {
                if (isset($override[$k]) && trim((string) $override[$k]) !== '') {
                    $resolved[$k] = trim((string) $override[$k]);
                }
            }
            if (isset($override['base_url'])) {
                $resolved['base_url'] = self::normalizeBaseUrl($resolved['base_url']);
            }
        }

        $token = $resolved['token'] !== '' ? $resolved['token'] : self::DEFAULT_TOKEN;
        $details = [];
        $client = StaffShareHttp::fromConfig([
            'base_url' => $resolved['base_url'],
            'username' => $resolved['username'],
            'password' => $resolved['password'],
            'token' => $token,
        ], 20);

        $jwtOk = false;
        $sampleOk = false;
        $mode = '';

        if ($resolved['username'] !== '' && $resolved['password'] !== '') {
            try {
                $jwt = $client->accessToken();
                if (is_string($jwt) && $jwt !== '') {
                    $jwtOk = true;
                    $mode = 'JWT (POST /share/token)';
                    $details[] = 'credentials → token: OK';
                } else {
                    $details[] = 'credentials → token: FAIL (rejected or empty — static token still tried)';
                }
            } catch (Throwable $e) {
                $details[] = 'credentials → token: FAIL ('.$e->getMessage().')';
            }
        } else {
            $details[] = 'credentials → token: SKIP (username/password empty)';
        }

        try {
            $staff = $client->getJson('/share/get_current_staff', ['limit' => 2]);
            $sampleOk = true;
            if ($mode === '') {
                $mode = $jwtOk ? 'JWT' : 'static STAFF_API_TOKEN';
            }
            $details[] = 'staff sample: OK ('.(is_array($staff) ? count($staff) : 0).' rows)';
        } catch (Throwable $e) {
            $details[] = 'staff sample: FAIL ('.$e->getMessage().')';
        }

        $divisionsOk = false;
        try {
            $div = $client->getJson('/share/divisions');
            $divisionsOk = true;
            $details[] = 'divisions: OK ('.(is_array($div) ? count($div) : 0).')';
        } catch (Throwable $e) {
            $details[] = 'divisions: FAIL ('.$e->getMessage().')';
        }

        // Same-app fallback: HTTP to loopback often deadlocks under Apache/PHP-FPM.
        // When Share data is available in-process, verify token + DB without another HTTP hop.
        if (! $sampleOk && class_exists(\Modules\Share\Services\ShareReferenceDataService::class)) {
            try {
                $expected = trim((string) config('share.api_token', ''));
                if ($expected === '') {
                    $expected = self::DEFAULT_TOKEN;
                }
                if (! hash_equals($expected, $token)) {
                    $details[] = 'in-process: FAIL (STAFF_API_TOKEN does not match share.api_token / default)';
                } else {
                    /** @var \Modules\Share\Services\ShareReferenceDataService $data */
                    $data = app(\Modules\Share\Services\ShareReferenceDataService::class);
                    $staff = $data->currentStaff([], 2, null);
                    $div = $data->divisions();
                    $sampleOk = true;
                    $divisionsOk = true;
                    if ($mode === '') {
                        $mode = 'in-process static STAFF_API_TOKEN';
                    }
                    $details[] = 'in-process staff sample: OK ('.count($staff).' rows)';
                    $details[] = 'in-process divisions: OK ('.count($div).')';
                }
            } catch (Throwable $e) {
                $details[] = 'in-process: FAIL ('.$e->getMessage().')';
            }
        }

        $ok = $sampleOk || ($jwtOk && $divisionsOk);
        if ($ok && $mode === '') {
            $mode = 'static STAFF_API_TOKEN';
        }

        $message = $ok
            ? 'CONNECTED via '.$mode
            : self::probeFailureMessage($resolved, $details);

        return [
            'success' => $ok,
            'status' => $ok ? 'CONNECTED' : 'FAILED',
            'mode' => $mode,
            'details' => $details,
            'base_url' => $resolved['base_url'],
            'message' => $message,
        ];
    }

    /**
     * @param  array{username: string, password: string, token: string}  $resolved
     * @param  list<string>  $details
     */
    private static function probeFailureMessage(array $resolved, array $details): string
    {
        $hasCreds = $resolved['username'] !== '' && $resolved['password'] !== '';
        $hasToken = ($resolved['token'] !== '' ? $resolved['token'] : self::DEFAULT_TOKEN) !== '';
        if ($hasCreds || $hasToken) {
            $hint = implode('; ', array_slice($details, 0, 3));

            return 'FAILED — credentials are set but Share API did not accept them'
                .($hint !== '' ? ' ('.$hint.')' : '')
                .'. Check base URL reachability and that STAFF_API_TOKEN matches the portal Share config.';
        }

        return 'FAILED — set STAFF_API_USERNAME/PASSWORD and/or STAFF_API_TOKEN (DB overrides env).';
    }

    public static function normalizeBaseUrl(string $base): string
    {
        $base = rtrim(trim($base), '/');
        if ($base === '') {
            $base = 'http://127.0.0.1/staff/backend';
        }
        if (preg_match('#^https?://web(/|$)#i', $base)) {
            $base = preg_replace('#^(https?://)web#i', '${1}127.0.0.1', $base) ?: $base;
        }
        if (! str_ends_with($base, '/backend')) {
            $base .= '/backend';
        }

        return $base;
    }

    private static function portalBase(): string
    {
        $portalBase = rtrim((string) (function_exists('env') ? env('CI_BASE_URL', env('BASE_URL', 'http://localhost/staff')) : (getenv('CI_BASE_URL') ?: getenv('BASE_URL') ?: 'http://localhost/staff')), '/');
        if (str_ends_with($portalBase, '/backend')) {
            $portalBase = preg_replace('#/backend$#', '', $portalBase) ?: $portalBase;
        }

        return $portalBase;
    }
}
