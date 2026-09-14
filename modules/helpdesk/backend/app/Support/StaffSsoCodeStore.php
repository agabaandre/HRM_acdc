<?php

namespace App\Support;

/**
 * Shared one-time SSO codes written by the Staff portal (CodeIgniter).
 */
final class StaffSsoCodeStore
{
    public static function cacheDir(): string
    {
        $candidates = [
            dirname(base_path(), 2).'/application/cache/cbp_sso',
            dirname(base_path(), 1).'/application/cache/cbp_sso',
            dirname(base_path(), 3).'/application/cache/cbp_sso',
        ];
        foreach ($candidates as $dir) {
            if (is_dir(dirname($dir))) {
                if (! is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }

                return $dir;
            }
        }

        return storage_path('framework/cbp_sso');
    }

    public static function normalizeModuleKey(string $moduleKey): string
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

    /**
     * @return array{jwt:string,module_key:string,user_id:int}|null
     */
    public static function consume(?string $code, ?string $expectedModuleKey = null): ?array
    {
        if ($code === null || $code === '' || ! preg_match('/^[a-f0-9]{64}$/i', $code)) {
            return null;
        }

        $path = self::cacheDir().DIRECTORY_SEPARATOR.hash('sha256', $code).'.json';
        if (! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (! is_array($data) || empty($data['jwt']) || empty($data['exp']) || (int) $data['exp'] < time()) {
            @unlink($path);

            return null;
        }

        $moduleKey = self::normalizeModuleKey((string) ($data['module_key'] ?? ''));
        if ($expectedModuleKey !== null && $expectedModuleKey !== '') {
            $expectedModuleKey = self::normalizeModuleKey($expectedModuleKey);
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
