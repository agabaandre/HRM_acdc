<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Staff\Shared\ModuleSettingsBag;
use Staff\Shared\StaffApiCredentials;
use Throwable;

/**
 * Resolve APM API credentials for SAP fund-code sync.
 *
 * Auth order: shared bridge secret (FINANCE_APM_BRIDGE_SECRET / STAFF_API_TOKEN)
 * mints an APM JWT without password — same pattern as budget-execution —
 * then falls back to APM / Staff Share email+password login.
 */
final class ApmApiCredentials
{
    public const KEY_BASE_URL = 'apm_api_base_url';

    public const KEY_EMAIL = 'apm_api_email';

    public const KEY_PASSWORD = 'apm_api_password';

    /**
     * @return array{
     *   base_url: string,
     *   api_prefix: string,
     *   email: string,
     *   password: string,
     *   timeout: int,
     *   sources: array{base_url: string, email: string, password: string}
     * }
     */
    public static function resolve(?ModuleSettingsBag $bag = null): array
    {
        $bag ??= new RrSettingsBag;
        $env = static function (string $key, string $default = ''): string {
            $v = config('apm.'.match ($key) {
                'APM_BASE_URL' => 'base_url',
                'APM_API_EMAIL' => 'email',
                'APM_API_PASSWORD' => 'password',
                'APM_API_PREFIX' => 'api_prefix',
                default => '',
            }, '');
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
            if (function_exists('env')) {
                $e = env($key);
                if (is_string($e) && trim($e) !== '') {
                    return trim($e);
                }
            }

            return $default;
        };

        $pick = static function (string $dbKey, string $envKey, string $default = '') use ($bag, $env): array {
            if ($bag->has($dbKey)) {
                return ['value' => trim((string) $bag->get($dbKey)), 'source' => 'db'];
            }
            $v = $env($envKey, $default);
            if ($v !== '') {
                return ['value' => $v, 'source' => 'env'];
            }

            return ['value' => $default, 'source' => $default !== '' ? 'default' : 'unset'];
        };

        $base = $pick(self::KEY_BASE_URL, 'APM_BASE_URL', '');
        $email = $pick(self::KEY_EMAIL, 'APM_API_EMAIL');
        $pass = $pick(self::KEY_PASSWORD, 'APM_API_PASSWORD');
        $prefix = rtrim($env('APM_API_PREFIX', '/api/apm/v1'), '/');
        if ($prefix === '') {
            $prefix = '/api/apm/v1';
        }

        // Never mix APM email with Staff password (or vice versa) — that causes HTTP 422.
        // Fall back to Staff Share as a complete pair when either APM field is missing.
        if ($email['value'] === '' || $pass['value'] === '') {
            $staff = StaffApiCredentials::resolve($bag);
            if ($staff['username'] !== '' && $staff['password'] !== '') {
                $email = ['value' => $staff['username'], 'source' => 'staff_api'];
                $pass = ['value' => $staff['password'], 'source' => 'staff_api'];
            }
        }

        return [
            'base_url' => rtrim((string) $base['value'], '/'),
            'api_prefix' => $prefix,
            'email' => (string) $email['value'],
            'password' => (string) $pass['value'],
            'timeout' => (int) config('apm.timeout', 60),
            'sources' => [
                'base_url' => (string) $base['source'],
                'email' => (string) $email['source'],
                'password' => (string) $pass['source'],
            ],
        ];
    }

    /**
     * Ordered login attempts: primary APM pair, then Staff Share pair if different.
     *
     * @return list<array{
     *   base_url: string,
     *   api_prefix: string,
     *   email: string,
     *   password: string,
     *   timeout: int,
     *   label: string
     * }>
     */
    public static function loginCandidates(?ModuleSettingsBag $bag = null): array
    {
        $bag ??= new RrSettingsBag;
        $primary = self::resolve($bag);
        $candidates = [];

        if ($primary['email'] !== '' && $primary['password'] !== '') {
            $candidates[] = [
                'base_url' => $primary['base_url'],
                'api_prefix' => $primary['api_prefix'],
                'email' => $primary['email'],
                'password' => $primary['password'],
                'timeout' => $primary['timeout'],
                'label' => $primary['sources']['email'] === 'staff_api' ? 'staff_api' : 'apm',
            ];
        }

        $staff = StaffApiCredentials::resolve($bag);
        if ($staff['username'] !== '' && $staff['password'] !== '') {
            $sameEmail = strcasecmp($staff['username'], $primary['email']) === 0;
            $samePass = hash_equals($primary['password'], $staff['password']);
            if (! ($sameEmail && $samePass)) {
                $candidates[] = [
                    'base_url' => $primary['base_url'],
                    'api_prefix' => $primary['api_prefix'],
                    'email' => $staff['username'],
                    'password' => $staff['password'],
                    'timeout' => $primary['timeout'],
                    'label' => 'staff_api',
                ];
            }
        }

        return $candidates;
    }

    /**
     * Emails usable for Finance→APM bridge mint (password not required).
     *
     * @return list<string>
     */
    public static function bridgeIdentityEmails(?ModuleSettingsBag $bag = null): array
    {
        $bag ??= new RrSettingsBag;
        $emails = [];

        if ($bag->has(self::KEY_EMAIL)) {
            $v = trim((string) $bag->get(self::KEY_EMAIL));
            if ($v !== '') {
                $emails[] = $v;
            }
        }

        $envEmail = trim((string) config('apm.email', ''));
        if ($envEmail === '' && function_exists('env')) {
            $e = env('APM_API_EMAIL');
            if (is_string($e) && trim($e) !== '') {
                $envEmail = trim($e);
            }
        }
        if ($envEmail !== '') {
            $emails[] = $envEmail;
        }

        $staff = StaffApiCredentials::resolve($bag);
        if ($staff['username'] !== '') {
            $emails[] = $staff['username'];
        }

        $unique = [];
        foreach ($emails as $email) {
            $key = strtolower($email);
            if (! isset($unique[$key])) {
                $unique[$key] = $email;
            }
        }

        return array_values($unique);
    }

    /**
     * Mint APM JWT via shared bridge secret (same approach as budget-execution).
     *
     * @return array{access_token: string, email: string, label: string}|null
     */
    public static function loginViaBridge(?ModuleSettingsBag $bag = null): ?array
    {
        $secret = trim((string) config('services.apm_bridge.secret', ''));
        if ($secret === '') {
            return null;
        }

        $bag ??= new RrSettingsBag;
        $resolved = self::resolve($bag);
        $baseUrl = rtrim((string) $resolved['base_url'], '/');
        if ($baseUrl === '') {
            return null;
        }

        $prefix = rtrim((string) $resolved['api_prefix'], '/');
        if ($prefix === '') {
            $prefix = '/api/apm/v1';
        }
        $url = $baseUrl.$prefix.'/internal/finance-session';
        $timeout = max(10, (int) $resolved['timeout']);

        foreach (self::bridgeIdentityEmails($bag) as $email) {
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'X-Finance-Bridge-Token' => $secret,
                        'Accept' => 'application/json',
                        'X-Requested-With' => 'XMLHttpRequest',
                    ])
                    ->post($url, ['email' => $email]);
            } catch (Throwable) {
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $token = data_get($response->json(), 'data.access_token');
            if (! is_string($token) || $token === '') {
                continue;
            }

            $mintedEmail = (string) (data_get($response->json(), 'data.email') ?: $email);

            return [
                'access_token' => $token,
                'email' => $mintedEmail,
                'label' => 'bridge',
            ];
        }

        return null;
    }

    /**
     * @return array{access_token: string, email: string, label: string}
     */
    public static function login(?ModuleSettingsBag $bag = null): array
    {
        $bag ??= new RrSettingsBag;

        // Prefer shared bridge secret (budget-execution style) — no password / allow_email_login.
        $bridged = self::loginViaBridge($bag);
        if ($bridged !== null) {
            return $bridged;
        }

        $candidates = self::loginCandidates($bag);
        if ($candidates === []) {
            throw new \RuntimeException(
                'APM API bridge secret or email/password must be configured '
                .'(FINANCE_APM_BRIDGE_SECRET / STAFF_API_TOKEN, or Settings → API credentials).'
            );
        }

        $errors = [];
        foreach ($candidates as $creds) {
            if ($creds['base_url'] === '') {
                $errors[] = $creds['label'].': APM base URL is not configured';

                continue;
            }
            $loginUrl = rtrim($creds['base_url'], '/').rtrim($creds['api_prefix'], '/').'/auth/login';
            try {
                $response = Http::timeout(max(10, (int) $creds['timeout']))
                    ->acceptJson()
                    ->post($loginUrl, [
                        'email' => $creds['email'],
                        'password' => $creds['password'],
                    ]);
            } catch (Throwable $e) {
                $errors[] = $creds['label'].' ('.$creds['email'].'): '.$e->getMessage();

                continue;
            }

            if (! $response->successful()) {
                $hint = trim((string) data_get(
                    $response->json(),
                    'errors.email.0',
                    data_get($response->json(), 'message', '')
                ));
                $errors[] = $creds['label'].' ('.$creds['email'].'): HTTP '.$response->status()
                    .($hint !== '' ? ' — '.$hint : '');

                continue;
            }

            $token = data_get($response->json(), 'data.access_token');
            if (! is_string($token) || $token === '') {
                $errors[] = $creds['label'].' ('.$creds['email'].'): missing access_token';

                continue;
            }

            return [
                'access_token' => $token,
                'email' => $creds['email'],
                'label' => $creds['label'],
            ];
        }

        throw new \RuntimeException('APM login failed: '.implode('; ', $errors));
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(?ModuleSettingsBag $bag = null): array
    {
        $bag ??= new RrSettingsBag;
        $resolved = self::resolve($bag);

        return [
            'settings' => [
                'apm_api_base_url' => $bag->has(self::KEY_BASE_URL) ? trim((string) $bag->get(self::KEY_BASE_URL)) : '',
                'apm_api_email' => $bag->has(self::KEY_EMAIL) ? trim((string) $bag->get(self::KEY_EMAIL)) : '',
                'apm_api_password' => '',
            ],
            'resolved' => [
                'base_url' => $resolved['base_url'],
                'email' => $resolved['email'],
                'password_configured' => $resolved['password'] !== '',
                'api_prefix' => $resolved['api_prefix'],
            ],
            'sources' => $resolved['sources'],
            'db_overrides' => [
                'base_url' => $bag->has(self::KEY_BASE_URL),
                'email' => $bag->has(self::KEY_EMAIL),
                'password' => $bag->has(self::KEY_PASSWORD),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function persist(ModuleSettingsBag $bag, array $input): string
    {
        if (array_key_exists('apm_api_base_url', $input)) {
            $v = trim((string) ($input['apm_api_base_url'] ?? ''));
            $bag->set(self::KEY_BASE_URL, $v === '' ? null : $v);
        }
        if (array_key_exists('apm_api_email', $input)) {
            $v = trim((string) ($input['apm_api_email'] ?? ''));
            $bag->set(self::KEY_EMAIL, $v === '' ? null : $v);
        }
        if (! empty($input['clear_apm_password'])) {
            $bag->set(self::KEY_PASSWORD, null);
        } elseif (array_key_exists('apm_api_password', $input)) {
            $pw = (string) ($input['apm_api_password'] ?? '');
            if ($pw !== '') {
                $bag->set(self::KEY_PASSWORD, $pw);
            }
        }

        return 'APM API credentials saved. Non-empty DB values override env; clear email/password to reuse Staff Share credentials.';
    }

    /**
     * @param  array{base_url?: string, email?: string, password?: string}|null  $override
     * @return array{success: bool, message: string, details: list<string>}
     */
    public static function probe(?ModuleSettingsBag $bag = null, ?array $override = null): array
    {
        $bag ??= new RrSettingsBag;

        if (is_array($override) && (
            (isset($override['email']) && trim((string) $override['email']) !== '')
            || (isset($override['password']) && trim((string) $override['password']) !== '')
            || (isset($override['base_url']) && trim((string) $override['base_url']) !== '')
        )) {
            $resolved = self::resolve($bag);
            foreach (['base_url', 'email', 'password'] as $k) {
                if (isset($override[$k]) && trim((string) $override[$k]) !== '') {
                    $resolved[$k] = trim((string) $override[$k]);
                }
            }
            $resolved['base_url'] = rtrim((string) $resolved['base_url'], '/');

            return self::probeWithCreds($resolved, 'override');
        }

        $bridgeProbe = self::probeViaBridge($bag);
        if ($bridgeProbe['success']) {
            return $bridgeProbe;
        }

        $candidates = self::loginCandidates($bag);
        if ($candidates === []) {
            $details = $bridgeProbe['details'];
            $details[] = 'no password login candidates';

            return [
                'success' => false,
                'message' => 'FAILED — set FINANCE_APM_BRIDGE_SECRET (or STAFF_API_TOKEN), or APM API email/password.',
                'details' => $details,
            ];
        }

        $details = $bridgeProbe['details'];
        $lastFail = $bridgeProbe;
        foreach ($candidates as $creds) {
            $result = self::probeWithCreds($creds, $creds['label']);
            $details = array_merge($details, $result['details']);
            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => $result['message'],
                    'details' => $details,
                ];
            }
            $lastFail = $result;
        }

        return [
            'success' => false,
            'message' => $lastFail['message'] ?? 'FAILED — APM login rejected.',
            'details' => $details,
        ];
    }

    /**
     * @return array{success: bool, message: string, details: list<string>}
     */
    private static function probeViaBridge(?ModuleSettingsBag $bag = null): array
    {
        $secret = trim((string) config('services.apm_bridge.secret', ''));
        if ($secret === '') {
            return [
                'success' => false,
                'message' => 'FAILED — bridge secret not configured.',
                'details' => ['bridge: secret unset'],
            ];
        }

        $bag ??= new RrSettingsBag;
        $resolved = self::resolve($bag);
        $baseUrl = rtrim((string) $resolved['base_url'], '/');
        if ($baseUrl === '') {
            return [
                'success' => false,
                'message' => 'FAILED — APM base URL is not configured.',
                'details' => ['bridge: base_url unset'],
            ];
        }

        $emails = self::bridgeIdentityEmails($bag);
        if ($emails === []) {
            return [
                'success' => false,
                'message' => 'FAILED — no APM identity email for bridge mint.',
                'details' => ['bridge: no identity email'],
            ];
        }

        $prefix = rtrim((string) $resolved['api_prefix'], '/');
        if ($prefix === '') {
            $prefix = '/api/apm/v1';
        }
        $url = $baseUrl.$prefix.'/internal/finance-session';
        $details = [];

        foreach ($emails as $email) {
            $details[] = 'trying: bridge ('.$email.')';
            try {
                $response = Http::timeout(20)
                    ->withHeaders([
                        'X-Finance-Bridge-Token' => $secret,
                        'Accept' => 'application/json',
                        'X-Requested-With' => 'XMLHttpRequest',
                    ])
                    ->post($url, ['email' => $email]);
            } catch (Throwable $e) {
                $details[] = 'bridge: error '.$e->getMessage();

                continue;
            }

            if (! $response->successful()) {
                $hint = trim((string) data_get($response->json(), 'message', ''));
                $details[] = 'bridge: FAIL HTTP '.$response->status()
                    .($hint !== '' ? ' — '.$hint : '');

                continue;
            }

            $token = data_get($response->json(), 'data.access_token');
            if (! is_string($token) || $token === '') {
                $details[] = 'bridge: FAIL (missing access_token)';

                continue;
            }

            $details[] = 'bridge: OK ('.$email.')';
            $codesUrl = $baseUrl.$prefix.'/fund-codes';
            $list = Http::timeout(20)
                ->withToken($token)
                ->acceptJson()
                ->get($codesUrl, ['year' => (int) date('Y'), 'per_page' => 1]);
            if ($list->successful()) {
                $total = (int) data_get($list->json(), 'pagination.total', 0);
                $details[] = 'fund-codes: OK (year '.date('Y').', total '.$total.')';
            } else {
                $details[] = 'fund-codes: FAIL HTTP '.$list->status();
            }

            return [
                'success' => true,
                'message' => 'CONNECTED to APM as '.$email.' (bridge)',
                'details' => $details,
            ];
        }

        return [
            'success' => false,
            'message' => 'FAILED — APM bridge mint rejected.',
            'details' => $details,
        ];
    }

    /**
     * @param  array{base_url: string, api_prefix?: string, email: string, password: string, timeout?: int}  $resolved
     * @return array{success: bool, message: string, details: list<string>}
     */
    private static function probeWithCreds(array $resolved, string $label): array
    {
        $details = [
            'trying: '.$label.' ('.$resolved['email'].')',
        ];
        if (($resolved['base_url'] ?? '') === '') {
            return [
                'success' => false,
                'message' => 'FAILED — APM base URL is not configured.',
                'details' => array_merge($details, ['base_url: unset']),
            ];
        }
        if (($resolved['email'] ?? '') === '' || ($resolved['password'] ?? '') === '') {
            return [
                'success' => false,
                'message' => 'FAILED — set APM API email/password, or Staff Share username/password.',
                'details' => array_merge($details, ['email/password empty']),
            ];
        }

        $prefix = rtrim((string) ($resolved['api_prefix'] ?? '/api/apm/v1'), '/');
        if ($prefix === '') {
            $prefix = '/api/apm/v1';
        }
        $loginUrl = rtrim($resolved['base_url'], '/').$prefix.'/auth/login';
        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->post($loginUrl, [
                    'email' => $resolved['email'],
                    'password' => $resolved['password'],
                ]);
            if (! $response->successful()) {
                $hint = trim((string) data_get($response->json(), 'errors.email.0', data_get($response->json(), 'message', '')));
                $details[] = 'login: FAIL HTTP '.$response->status();
                if ($hint !== '') {
                    $details[] = 'login detail: '.$hint;
                }

                return [
                    'success' => false,
                    'message' => 'FAILED — APM login rejected (HTTP '.$response->status().').',
                    'details' => $details,
                ];
            }
            $token = data_get($response->json(), 'data.access_token');
            if (! is_string($token) || $token === '') {
                $details[] = 'login: FAIL (missing access_token)';

                return [
                    'success' => false,
                    'message' => 'FAILED — APM login response missing access_token.',
                    'details' => $details,
                ];
            }
            $details[] = 'login: OK ('.$resolved['email'].' via '.$label.')';

            $codesUrl = rtrim($resolved['base_url'], '/').$prefix.'/fund-codes';
            $list = Http::timeout(20)
                ->withToken($token)
                ->acceptJson()
                ->get($codesUrl, ['year' => (int) date('Y'), 'per_page' => 1]);
            if ($list->successful()) {
                $total = (int) data_get($list->json(), 'pagination.total', 0);
                $details[] = 'fund-codes: OK (year '.date('Y').', total '.$total.')';
            } else {
                $details[] = 'fund-codes: FAIL HTTP '.$list->status();
            }

            return [
                'success' => true,
                'message' => 'CONNECTED to APM as '.$resolved['email'].' ('.$label.')',
                'details' => $details,
            ];
        } catch (Throwable $e) {
            $details[] = 'error: '.$e->getMessage();

            return [
                'success' => false,
                'message' => 'FAILED — '.$e->getMessage(),
                'details' => $details,
            ];
        }
    }
}
