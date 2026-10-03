<?php

namespace Staff\Shared;

use RuntimeException;
use Throwable;

/**
 * Shared outbound email settings for CBP modules.
 * Resolve: DB (if set) → env → defaults.
 */
final class ModuleEmailSettings
{
    public const KEY_DISPATCH = 'staff_mail_dispatch';

    public const KEY_TRANSPORT = 'mail_transport';

    public const KEY_FROM_ADDRESS = 'mail_from_address';

    public const KEY_FROM_NAME = 'mail_from_name';

    public const KEY_SUBJECT_PREFIX = 'mail_subject_prefix';

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(
        string $moduleLabel,
        string $defaultFromName,
        string $defaultSubjectPrefix,
        ?string $moduleEnvPath = null,
        ?string $shareBaseUrl = null,
        ?ModuleSettingsBag $bag = null,
    ): array {
        $resolved = self::resolve($defaultFromName, $defaultSubjectPrefix, $bag);
        $api = StaffApiCredentials::resolve($bag);

        $envVal = static function (string $key): string {
            if (function_exists('env')) {
                return trim((string) env($key, ''));
            }
            $v = getenv($key);

            return is_string($v) ? trim($v) : '';
        };

        $portalBase = rtrim((string) (function_exists('env') ? env('CI_BASE_URL', env('BASE_URL', 'http://localhost/staff')) : (getenv('CI_BASE_URL') ?: getenv('BASE_URL') ?: 'http://localhost/staff')), '/');
        if (str_ends_with($portalBase, '/backend')) {
            $portalBase = preg_replace('#/backend$#', '', $portalBase) ?: $portalBase;
        }

        $shareBase = $shareBaseUrl ?: $api['base_url'];

        return [
            'module' => $moduleLabel,
            'settings' => [
                'staff_mail_dispatch' => $bag?->has(self::KEY_DISPATCH) ? (string) $bag->get(self::KEY_DISPATCH) : '',
                'mail_transport' => $bag?->has(self::KEY_TRANSPORT) ? (string) $bag->get(self::KEY_TRANSPORT) : '',
                'mail_from_address' => $bag?->has(self::KEY_FROM_ADDRESS) ? (string) $bag->get(self::KEY_FROM_ADDRESS) : '',
                'mail_from_name' => $bag?->has(self::KEY_FROM_NAME) ? (string) $bag->get(self::KEY_FROM_NAME) : '',
                'mail_subject_prefix' => $bag?->has(self::KEY_SUBJECT_PREFIX) ? (string) $bag->get(self::KEY_SUBJECT_PREFIX) : '',
            ],
            'resolved' => $resolved,
            'sources' => $resolved['sources'],
            'status' => [
                'exchange_configured' => $envVal('EXCHANGE_TENANT_ID') !== '' && $envVal('EXCHANGE_CLIENT_ID') !== '' && $envVal('EXCHANGE_CLIENT_SECRET') !== '',
                'http_configured' => $envVal('MAIL_HTTP_CLIENT_ID') !== '' && $envVal('MAIL_HTTP_CLIENT_SECRET') !== '',
                'smtp_configured' => $envVal('MAIL_HOST') !== '' && $envVal('MAIL_USERNAME') !== '',
                'share_base' => $shareBase,
                'portal_mail_client' => class_exists(StaffPortalMailClient::class),
                'module_env_writable' => is_file($moduleEnvPath ?? '') && is_writable($moduleEnvPath ?? ''),
            ],
            'portal_email_url' => $portalBase.'/settings/email-servers',
            'portal_staff_api_url' => $portalBase.'/settings/staff-api',
        ];
    }

    /**
     * @return array{
     *   staff_mail_dispatch: string,
     *   mail_transport: string,
     *   mail_from_address: string,
     *   mail_from_name: string,
     *   mail_subject_prefix: string,
     *   sources: array<string, string>
     * }
     */
    public static function resolve(
        string $defaultFromName,
        string $defaultSubjectPrefix,
        ?ModuleSettingsBag $bag = null,
    ): array {
        $env = static function (string $key, string $default = ''): string {
            if (function_exists('env')) {
                return trim((string) env($key, $default));
            }
            $v = getenv($key);

            return is_string($v) ? trim($v) : $default;
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

            return ['value' => $default, 'source' => $default !== '' ? 'default' : 'unset'];
        };

        $dispatch = $pick(self::KEY_DISPATCH, ['STAFF_MAIL_DISPATCH'], 'auto');
        $transport = $pick(self::KEY_TRANSPORT, ['MAIL_TRANSPORT', 'MAIL_MAILER'], 'exchange');
        $fromAddr = $pick(self::KEY_FROM_ADDRESS, ['MAIL_FROM_ADDRESS']);
        $fromName = $pick(self::KEY_FROM_NAME, ['MAIL_FROM_NAME'], $defaultFromName);
        $prefix = $pick(self::KEY_SUBJECT_PREFIX, ['MAIL_SUBJECT_PREFIX'], $defaultSubjectPrefix);

        $d = strtolower((string) $dispatch['value']);
        if (! in_array($d, ['auto', 'portal', 'local'], true)) {
            $d = 'auto';
        }
        $t = strtolower((string) $transport['value']);
        if ($t === '') {
            $t = 'exchange';
        }

        return [
            'staff_mail_dispatch' => $d,
            'mail_transport' => $t,
            'mail_from_address' => (string) $fromAddr['value'],
            'mail_from_name' => (string) $fromName['value'],
            'mail_subject_prefix' => (string) $prefix['value'],
            'sources' => [
                'staff_mail_dispatch' => (string) $dispatch['source'],
                'mail_transport' => (string) $transport['source'],
                'mail_from_address' => (string) $fromAddr['source'],
                'mail_from_name' => (string) $fromName['source'],
                'mail_subject_prefix' => (string) $prefix['source'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{pairs: array<string, string>, message: string}
     */
    public static function persist(
        array $input,
        string $defaultFromName,
        string $defaultSubjectPrefix,
        string $moduleEnvPath,
        ?string $rootEnvPath = null,
        ?ModuleSettingsBag $bag = null,
    ): array {
        $dispatch = strtolower(trim((string) ($input['staff_mail_dispatch'] ?? '')));
        $transport = strtolower(trim((string) ($input['mail_transport'] ?? '')));

        if ($bag instanceof ModuleSettingsBag) {
            // Empty string clears DB override (fall back to env).
            $bag->set(self::KEY_DISPATCH, $dispatch === '' ? null : $dispatch);
            $bag->set(self::KEY_TRANSPORT, $transport === '' ? null : $transport);
            $bag->set(self::KEY_FROM_ADDRESS, trim((string) ($input['mail_from_address'] ?? '')) ?: null);
            $bag->set(self::KEY_FROM_NAME, trim((string) ($input['mail_from_name'] ?? '')) ?: null);
            $bag->set(self::KEY_SUBJECT_PREFIX, trim((string) ($input['mail_subject_prefix'] ?? '')) ?: null);
        }

        $resolved = self::resolve($defaultFromName, $defaultSubjectPrefix, $bag);
        if (! in_array($resolved['staff_mail_dispatch'], ['auto', 'portal', 'local'], true)) {
            throw new RuntimeException('staff_mail_dispatch must be auto, portal, or local.');
        }
        if (! in_array($resolved['mail_transport'], ['http', 'exchange', 'smtp', 'zoho', 'log'], true)) {
            throw new RuntimeException('mail_transport must be http, exchange, smtp, zoho, or log.');
        }

        $pairs = [
            'STAFF_MAIL_DISPATCH' => $resolved['staff_mail_dispatch'],
            'MAIL_TRANSPORT' => $resolved['mail_transport'],
            'MAIL_MAILER' => $resolved['mail_transport'],
            'MAIL_FROM_ADDRESS' => $resolved['mail_from_address'],
            'MAIL_FROM_NAME' => $resolved['mail_from_name'],
            'MAIL_SUBJECT_PREFIX' => $resolved['mail_subject_prefix'],
        ];

        // Also mirror resolved values into .env when writable (keeps workers in sync).
        if (is_file($moduleEnvPath) && is_writable($moduleEnvPath)) {
            self::upsertEnvKeys($moduleEnvPath, $pairs);
        }
        $root = $rootEnvPath ?? dirname($moduleEnvPath, 2).'/.env';
        if (! is_file($root)) {
            $alt = dirname($moduleEnvPath, 3).'/.env';
            if (is_file($alt)) {
                $root = $alt;
            }
        }
        if (is_file($root) && is_writable($root)) {
            self::upsertEnvKeys($root, [
                'STAFF_MAIL_DISPATCH' => $pairs['STAFF_MAIL_DISPATCH'],
                'MAIL_TRANSPORT' => $pairs['MAIL_TRANSPORT'],
                'MAIL_MAILER' => $pairs['MAIL_MAILER'],
                'MAIL_FROM_ADDRESS' => $pairs['MAIL_FROM_ADDRESS'],
                'MAIL_FROM_NAME' => $pairs['MAIL_FROM_NAME'],
            ]);
        }

        foreach ($pairs as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        return [
            'pairs' => $pairs,
            'message' => 'Email settings saved. DB values override env when set; clear a field to fall back to env / Staff Portal.',
        ];
    }

    /**
     * @param  (callable(string, string, string): bool)|null  $sender
     * @return array{success: bool, message: string}
     */
    public static function sendTest(
        string $to,
        string $moduleLabel,
        string $defaultSubjectPrefix,
        ?callable $sender = null,
        ?ModuleSettingsBag $bag = null,
        string $defaultFromName = 'Africa CDC',
    ): array {
        $resolved = self::resolve($defaultFromName, $defaultSubjectPrefix, $bag);
        $prefix = $resolved['mail_subject_prefix'];
        $subject = $prefix.': Email settings test '.gmdate('c');
        $body = '<p>This is a <strong>test message</strong> from '.$moduleLabel.' → Email settings.</p>'
            .'<p>Dispatch: <code>'.htmlspecialchars($resolved['staff_mail_dispatch'], ENT_QUOTES, 'UTF-8').'</code> · '
            .'Transport: <code>'.htmlspecialchars($resolved['mail_transport'], ENT_QUOTES, 'UTF-8').'</code></p>'
            .'<p>Sent at: '.htmlspecialchars(gmdate('Y-m-d H:i:s').' UTC', ENT_QUOTES, 'UTF-8').'</p>';

        try {
            if ($sender !== null) {
                $ok = (bool) $sender($to, $subject, $body);
            } else {
                $client = StaffPortalMailClient::fromAppConfig(null, $resolved['staff_mail_dispatch']);
                $client->send($to, $subject, $body);
                $ok = true;
            }
            if (! $ok) {
                return [
                    'success' => false,
                    'message' => 'Send returned failure. Check STAFF_MAIL_DISPATCH, Share credentials, and portal Email settings.',
                ];
            }

            return ['success' => true, 'message' => "Test email sent to {$to}."];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, string>  $pairs
     */
    public static function upsertEnvKeys(string $path, array $pairs): void
    {
        if (! is_file($path)) {
            throw new RuntimeException("Environment file missing: {$path}");
        }
        if (! is_writable($path)) {
            throw new RuntimeException("Environment file not writable: {$path}");
        }

        $content = (string) file_get_contents($path);
        foreach ($pairs as $key => $value) {
            $escaped = self::escapeEnvValue($value);
            $line = $key.'='.$escaped;
            $pattern = '/^'.preg_quote($key, '/').'\s*=.*$/m';
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $line, $content, 1) ?? $content;
            } else {
                $content = rtrim($content)."\n{$line}\n";
            }
        }

        $backup = $path.'.backup.'.date('Y-m-d-H-i-s');
        copy($path, $backup);
        file_put_contents($path, $content);
    }

    private static function escapeEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (preg_match('/\s|#|"|\'/', $value)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
