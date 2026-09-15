<?php

namespace App\Services;

use App\Support\RuntimeUrl;
use App\Support\StaffApiBaseUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Top-bar CBP Modules dropdown (Staff Share API — same payload as Helpdesk).
 * Fallback modules are rendered inside the dropdown, not as separate header links.
 */
class CbpModulesNavService
{
    /**
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    public static function headerNav(): array
    {
        $defaults = self::defaultPayload();

        $staffId = (int) data_get(session('user'), 'staff_id', 0);
        if ($staffId < 1) {
            return $defaults;
        }

        $client = app(StaffPortalShareClient::class);
        if (! $client->isConfigured()) {
            return self::fallbackPayload($defaults);
        }

        try {
            $perms = array_map('strval', (array) session('permissions', []));
            $permKey = $perms !== [] ? md5(implode(',', $perms)) : 'db';

            return Cache::remember(
                'apm_cbp_modules_nav_'.$staffId.'_'.$permKey,
                300,
                fn () => self::sanitizeNavPayload(
                    $client->fetchCbpModules($staffId, 'approvals_management', 'approvals_management', $perms)
                )
            );
        } catch (\Throwable $e) {
            Log::warning('CbpModulesNavService: '.$e->getMessage());

            return self::fallbackPayload($defaults);
        }
    }

    public static function staffWebBaseUrl(): string
    {
        return RuntimeUrl::staffPortalBaseUrl();
    }

    /**
     * Ensure CBP Home / Staff Portal browser links never point at /backend (Share API).
     *
     * @param  array{home: array<string, mixed>, modules: list<array<string, mixed>>}  $payload
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    public static function sanitizeNavPayload(array $payload): array
    {
        $spa = rtrim(self::staffWebBaseUrl(), '/');

        $home = $payload['home'] ?? [];
        if (! is_array($home)) {
            $home = [];
        }
        $homeHref = self::rewritePublicSpaHref((string) ($home['href'] ?? ''), $spa.'/');
        $home['href'] = $homeHref !== '' ? $homeHref : $spa.'/';
        $payload['home'] = $home;

        $modules = $payload['modules'] ?? [];
        if (! is_array($modules)) {
            $modules = [];
        }
        foreach ($modules as $i => $mod) {
            if (! is_array($mod)) {
                continue;
            }
            $key = (string) ($mod['module_key'] ?? $mod['id'] ?? '');
            $href = (string) ($mod['href'] ?? '');
            if ($key === 'staff_portal' || $key === 'cbp_home' || self::hrefLooksLikeStaffApi($href)) {
                $mod['href'] = $spa.'/dashboard';
            } else {
                $rewritten = self::rewritePublicSpaHref($href, '');
                if ($rewritten !== '') {
                    $mod['href'] = $rewritten;
                }
            }
            $modules[$i] = $mod;
        }
        $payload['modules'] = array_values($modules);

        return $payload;
    }

    private static function hrefLooksLikeStaffApi(string $href): bool
    {
        $href = strtolower(trim($href));
        if ($href === '') {
            return false;
        }

        return (bool) preg_match('#(^|/)backend(/|$|\?)#', $href)
            || str_contains($href, '/share/');
    }

    private static function rewritePublicSpaHref(string $href, string $emptyFallback): string
    {
        $href = trim($href);
        if ($href === '') {
            return $emptyFallback;
        }
        if (self::hrefLooksLikeStaffApi($href)) {
            return $emptyFallback !== '' ? $emptyFallback : rtrim(self::staffWebBaseUrl(), '/').'/dashboard';
        }
        $normalized = RuntimeUrl::normalizeStaffPortalPublicUrl($href);
        // normalizeStaffPortalPublicUrl strips path after /backend; keep non-API absolute paths as-is.
        if ($normalized !== '' && ! self::hrefLooksLikeStaffApi($href) && preg_match('#^https?://#i', $href)) {
            $path = (string) (parse_url($href, PHP_URL_PATH) ?? '');
            if ($path !== '' && $path !== '/' && ! preg_match('#/(backend)(/|$)#', $path)) {
                return rtrim($href, '/');
            }
        }

        return $normalized !== '' ? $normalized : $href;
    }

    /**
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    private static function defaultPayload(): array
    {
        $spa = rtrim(self::staffWebBaseUrl(), '/');

        return [
            'home' => [
                'id' => 'cbp_home',
                'label' => 'CBP Home',
                'description' => '',
                'href' => $spa.'/',
                'is_active' => false,
            ],
            'modules' => [],
        ];
    }

    /**
     * @param  array{home: array<string, mixed>, modules: list<array<string, mixed>>}  $defaults
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    private static function fallbackPayload(array $defaults): array
    {
        $modules = [];
        foreach (CbpPlatformMenuService::primaryNavItems() as $item) {
            $modules[] = [
                'id' => md5((string) (($item['url'] ?? '').($item['title'] ?? ''))),
                'label' => (string) ($item['title'] ?? 'Module'),
                'description' => (string) ($item['description'] ?? ''),
                'href' => (string) ($item['url'] ?? '#'),
                'icon' => (string) ($item['icon'] ?? 'fa fa-th'),
                'opens_in_new_tab' => true,
                'is_active' => false,
            ];
        }

        if ($modules !== []) {
            $defaults['modules'] = $modules;

            return $defaults;
        }

        $staffBase = self::staffWebBaseUrl();

        $defaults['modules'] = [
            [
                'id' => 'staff_portal',
                'label' => 'Staff Portal',
                'description' => '',
                // SPA home/dashboard — never …/backend (Laravel API)
                'href' => rtrim($staffBase, '/').'/dashboard',
                'icon' => 'fa fa-users',
                'opens_in_new_tab' => false,
                'is_active' => false,
            ],
            [
                'id' => 'finance_management',
                'label' => 'Finance Management',
                'description' => '',
                'href' => $staffBase.'/finance',
                'icon' => 'fa fa-wallet',
                'opens_in_new_tab' => false,
                'is_active' => false,
            ],
        ];

        return $defaults;
    }
}
