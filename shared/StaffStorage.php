<?php

declare(strict_types=1);

namespace Staff\Shared;

/**
 * Host-side upload paths for the Staff ecosystem (CI3, APM, Helpdesk, staff-portal).
 *
 * Layout when STAFF_DATA_ROOT is set (recommended production):
 *   {STAFF_DATA_ROOT}/ci/            — legacy CI uploads tree (staff photos, summernote, …)
 *   {STAFF_DATA_ROOT}/apm/           — APM public disk (memo attachments, …)
 *   {STAFF_DATA_ROOT}/helpdesk/      — Helpdesk public disk (tickets, rich-text images)
 *   {STAFF_DATA_ROOT}/staff-portal/  — staff-portal module public files
 *   {STAFF_DATA_ROOT}/backups/files/ — file backups (managed from Knowledge Hub UI)
 */
final class StaffStorage
{
    public const MODULE_CI = 'ci';

    public const MODULE_APM = 'apm';

    public const MODULE_HELPDESK = 'helpdesk';

    public const MODULE_STAFF_PORTAL = 'staff-portal';

    /** @var array<string, string> */
    private const MODULE_ENV_KEYS = [
        self::MODULE_CI => 'STAFF_PORTAL_UPLOADS_ROOT',
        self::MODULE_APM => 'STAFF_APM_FILES_ROOT',
        self::MODULE_HELPDESK => 'STAFF_HELPDESK_FILES_ROOT',
        self::MODULE_STAFF_PORTAL => 'STAFF_PORTAL_MODULE_FILES_ROOT',
    ];

    public static function siteId(?string $appUrl = null): string
    {
        $override = self::envString('STAFF_SITE_ID');
        if ($override !== '') {
            return self::sanitizeSiteId($override);
        }

        $url = $appUrl
            ?? self::envString('BASE_URL')
            ?: self::envString('CI_BASE_URL')
            ?: self::envString('APP_URL')
            ?: 'http://localhost/staff';

        return self::siteIdFromUrl($url);
    }

    public static function siteIdFromUrl(string $appUrl): string
    {
        $appUrl = trim($appUrl);
        if ($appUrl === '') {
            return 'default';
        }

        $parsed = parse_url($appUrl);
        $host = strtolower((string) ($parsed['host'] ?? 'localhost'));
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        $port = isset($parsed['port']) ? (int) $parsed['port'] : null;
        $path = trim((string) ($parsed['path'] ?? ''), '/');

        $segments = array_filter(explode('.', $host), static fn (string $part): bool => $part !== '');
        $slug = implode('-', $segments);

        if ($port && ! in_array($port, [80, 443], true)) {
            $slug .= '-'.$port;
        }

        if ($path !== '') {
            $pathSlug = preg_replace('/[^a-z0-9]+/', '-', strtolower($path)) ?? '';
            $pathSlug = trim($pathSlug, '-');
            if ($pathSlug !== '') {
                $slug .= '-'.$pathSlug;
            }
        }

        return self::sanitizeSiteId($slug ?: 'default');
    }

    public static function sanitizeSiteId(string $id): string
    {
        $id = strtolower(trim($id));
        $id = preg_replace('/[^a-z0-9-]+/', '-', $id) ?? '';
        $id = preg_replace('/-+/', '-', $id) ?? '';

        return trim($id, '-') ?: 'default';
    }

    public static function hostDataRoot(?string $siteId = null): string
    {
        $explicit = self::envString('STAFF_DATA_ROOT');
        if ($explicit !== '') {
            return rtrim($explicit, '/\\');
        }

        $base = PHP_OS_FAMILY === 'Windows'
            ? (self::envString('STAFF_HOST_DATA_ROOT_WINDOWS') ?: 'C:\\staffdata')
            : (self::envString('STAFF_HOST_DATA_ROOT') ?: '/var/staffdata');

        return rtrim($base, '/\\').DIRECTORY_SEPARATOR.self::siteId($siteId);
    }

    public static function filesBackupRoot(?string $siteId = null): string
    {
        $explicit = self::envString('STAFF_FILES_BACKUP_ROOT');
        if ($explicit !== '') {
            return rtrim($explicit, '/\\');
        }

        return self::hostDataRoot($siteId).DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR.'files';
    }

    public static function ciUploadsRoot(?string $repoRoot = null): string
    {
        return self::moduleRoot(self::MODULE_CI, $repoRoot);
    }

    public static function apmPublicRoot(?string $apmBasePath = null): string
    {
        return self::moduleRoot(self::MODULE_APM, $apmBasePath);
    }

    public static function helpdeskPublicRoot(?string $helpdeskBasePath = null): string
    {
        return self::moduleRoot(self::MODULE_HELPDESK, $helpdeskBasePath);
    }

    public static function staffPortalModuleRoot(?string $staffPortalBasePath = null): string
    {
        return self::moduleRoot(self::MODULE_STAFF_PORTAL, $staffPortalBasePath);
    }

    public static function moduleRoot(string $module, ?string $appBasePath = null): string
    {
        $envKey = self::MODULE_ENV_KEYS[$module] ?? null;
        if ($envKey !== null) {
            $explicit = self::envString($envKey);
            if ($explicit !== '') {
                return rtrim($explicit, '/\\');
            }
        }

        if (self::useHostStorage()) {
            return self::hostDataRoot().DIRECTORY_SEPARATOR.$module;
        }

        return self::legacyModuleRoot($module, $appBasePath);
    }

    public static function useHostStorage(): bool
    {
        if (self::envString('STAFF_DATA_ROOT') !== '') {
            return true;
        }

        return filter_var(self::envString('STAFF_USE_HOST_STORAGE') ?: 'false', FILTER_VALIDATE_BOOL);
    }

    public static function ciPath(string $relative = ''): string
    {
        return self::join(self::ciUploadsRoot(), $relative);
    }

    /**
     * @return array<string, string>
     */
    public static function recommendedPaths(?string $appUrl = null): array
    {
        $siteId = self::siteId($appUrl);
        $root = self::hostDataRoot($siteId);

        return [
            'site_id' => $siteId,
            'data_root' => $root,
            'ci' => $root.DIRECTORY_SEPARATOR.self::MODULE_CI,
            'apm' => $root.DIRECTORY_SEPARATOR.self::MODULE_APM,
            'helpdesk' => $root.DIRECTORY_SEPARATOR.self::MODULE_HELPDESK,
            'staff_portal' => $root.DIRECTORY_SEPARATOR.self::MODULE_STAFF_PORTAL,
            'backups_files' => $root.DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR.'files',
        ];
    }

    /**
     * @return list<array{module: string, source: string, destination: string}>
     */
    public static function migrationSources(?string $repoRoot = null): array
    {
        $repoRoot = $repoRoot ?? self::detectRepoRoot();

        return [
            [
                'module' => self::MODULE_CI,
                'source' => $repoRoot.DIRECTORY_SEPARATOR.'uploads',
                'destination' => self::hostDataRoot().DIRECTORY_SEPARATOR.self::MODULE_CI,
            ],
            [
                'module' => self::MODULE_APM,
                'source' => $repoRoot.DIRECTORY_SEPARATOR.'apm'.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public',
                'destination' => self::hostDataRoot().DIRECTORY_SEPARATOR.self::MODULE_APM,
            ],
            [
                'module' => self::MODULE_HELPDESK,
                'source' => $repoRoot.DIRECTORY_SEPARATOR.'helpdesk'.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public',
                'destination' => self::hostDataRoot().DIRECTORY_SEPARATOR.self::MODULE_HELPDESK,
            ],
            [
                'module' => self::MODULE_STAFF_PORTAL,
                'source' => $repoRoot.DIRECTORY_SEPARATOR.'staff-portal'.DIRECTORY_SEPARATOR.'backend'.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public',
                'destination' => self::hostDataRoot().DIRECTORY_SEPARATOR.self::MODULE_STAFF_PORTAL,
            ],
        ];
    }

    private static function legacyModuleRoot(string $module, ?string $appBasePath = null): string
    {
        $repoRoot = self::detectRepoRoot($appBasePath);

        return match ($module) {
            self::MODULE_CI => $repoRoot.DIRECTORY_SEPARATOR.'uploads',
            self::MODULE_APM => ($appBasePath ?? $repoRoot.DIRECTORY_SEPARATOR.'apm').DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public',
            self::MODULE_HELPDESK => ($appBasePath ?? $repoRoot.DIRECTORY_SEPARATOR.'helpdesk'.DIRECTORY_SEPARATOR.'backend').DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public',
            self::MODULE_STAFF_PORTAL => ($appBasePath ?? $repoRoot.DIRECTORY_SEPARATOR.'staff-portal'.DIRECTORY_SEPARATOR.'backend').DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public',
            default => $repoRoot.DIRECTORY_SEPARATOR.'uploads',
        };
    }

    private static function detectRepoRoot(?string $appBasePath = null): string
    {
        if ($appBasePath !== null && $appBasePath !== '') {
            $normalized = realpath($appBasePath);
            if ($normalized !== false) {
                return $normalized;
            }
        }

        if (defined('FCPATH')) {
            return rtrim(FCPATH, '/\\');
        }

        return realpath(dirname(__DIR__)) ?: dirname(__DIR__);
    }

    private static function join(string $base, string $relative): string
    {
        $base = rtrim($base, '/\\');
        $relative = ltrim(str_replace(['\\', '..'], ['/', ''], $relative), '/');
        if ($relative === '') {
            return $base;
        }

        return $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private static function envString(string $key): string
    {
        $value = getenv($key);
        if (is_string($value) && $value !== '') {
            return trim($value);
        }

        if (isset($_ENV[$key]) && is_string($_ENV[$key]) && $_ENV[$key] !== '') {
            return trim($_ENV[$key]);
        }

        return '';
    }
}
