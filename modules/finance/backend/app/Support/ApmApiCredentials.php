<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Staff\Shared\ModuleSettingsBag;
use Throwable;

/**
 * Resolve APM API credentials for SAP fund-code sync: DB → env.
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

        return 'APM API credentials saved. Non-empty DB values override env; clear a field to fall back to env.';
    }

    /**
     * @param  array{base_url?: string, email?: string, password?: string}|null  $override
     * @return array{success: bool, message: string, details: list<string>}
     */
    public static function probe(?ModuleSettingsBag $bag = null, ?array $override = null): array
    {
        $resolved = self::resolve($bag);
        if (is_array($override)) {
            foreach (['base_url', 'email', 'password'] as $k) {
                if (isset($override[$k]) && trim((string) $override[$k]) !== '') {
                    $resolved[$k] = trim((string) $override[$k]);
                }
            }
            $resolved['base_url'] = rtrim((string) $resolved['base_url'], '/');
        }

        $details = [];
        if ($resolved['base_url'] === '') {
            return [
                'success' => false,
                'message' => 'FAILED — APM base URL is not configured.',
                'details' => ['base_url: unset'],
            ];
        }
        if ($resolved['email'] === '' || $resolved['password'] === '') {
            return [
                'success' => false,
                'message' => 'FAILED — set APM API email and password (DB overrides env).',
                'details' => [
                    'email: '.($resolved['email'] !== '' ? 'set' : 'empty'),
                    'password: '.($resolved['password'] !== '' ? 'set' : 'empty'),
                ],
            ];
        }

        $loginUrl = $resolved['base_url'].$resolved['api_prefix'].'/auth/login';
        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->post($loginUrl, [
                    'email' => $resolved['email'],
                    'password' => $resolved['password'],
                ]);
            if (! $response->successful()) {
                $details[] = 'login: FAIL HTTP '.$response->status();

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
            $details[] = 'login: OK ('.$resolved['email'].')';

            $codesUrl = $resolved['base_url'].$resolved['api_prefix'].'/fund-codes';
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
                'message' => 'CONNECTED to APM as '.$resolved['email'],
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
