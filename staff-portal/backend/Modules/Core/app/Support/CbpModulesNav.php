<?php

namespace Modules\Core\Support;

use Illuminate\Support\Facades\DB;

/**
 * CBP modules nav payload — mirrors CI3 Cbp_modules_mdl::get_api_nav_payload / resolve_href.
 */
class CbpModulesNav
{
    /**
     * @param  array<string, mixed>  $session  PortalUser::toSessionArray() (permissions, role_id, …)
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    public static function payload(
        array $session,
        string $portalPath = '',
        string $excludeModuleKey = '',
        string $activeModuleKey = '',
    ): array {
        $spaUrl = rtrim(self::publicBase(
            (string) config('staff-portal.spa_url', '/staff/staff-portal/'),
            '/staff/staff-portal/',
        ), '/').'/';
        $legacyBase = rtrim(self::publicBase(
            (string) config('staff-portal.legacy_base_url', '/staff/'),
            '/staff/',
        ), '/');
        $path = trim($portalPath, '/');
        $homeActive = $activeModuleKey === '' && ($path === '' || $path === 'home');

        $home = [
            'id' => 'cbp_home',
            'label' => 'CBP Home',
            'description' => '',
            'href' => $spaUrl,
            'is_active' => $homeActive,
        ];

        $modules = [];

        if (! \App\Support\LegacySchema::has('cbp_modules')) {
            return ['home' => $home, 'modules' => $modules];
        }

        $permissions = $session['permissions'] ?? [];
        $roleId = (int) ($session['role_id'] ?? $session['role'] ?? 0);
        $excludeModuleKey = trim($excludeModuleKey);
        $activeModuleKey = trim($activeModuleKey);
        $launchUrl = $legacyBase.'/home/launch_module';

        $rows = DB::table('cbp_modules')
            ->where('is_enabled', 1)
            ->orderBy('sort_order')
            ->get();

        foreach ($rows as $row) {
            $moduleKey = trim((string) ($row->module_key ?? ''));
            if ($moduleKey === '') {
                $moduleKey = 'cbp_module_'.(int) $row->id;
            }
            if ($excludeModuleKey !== '' && $moduleKey === $excludeModuleKey) {
                continue;
            }

            $code = (string) $row->permission_code;
            if (! PortalNavigation::can($permissions, $code)) {
                continue;
            }
            if ((int) $row->is_production === 0 && $roleId !== 10) {
                continue;
            }

            $resolved = self::resolveHref($row, $session, $roleId);
            if ($resolved === null || $resolved === '') {
                continue;
            }

            $ssoLaunch = (int) ($row->uses_staff_portal_token ?? 0) === 1;
            $resolver = (string) ($row->target_resolver ?? 'codeigniter');
            $absolute = in_array($resolver, ['staff_app_token', 'finance_host', 'external_microservice'], true)
                || (bool) preg_match('#^https?://#i', $resolved);

            // SPA-relative paths for in-portal links (Human Resource → dashboard).
            $href = $resolved;
            if (! $absolute && ! str_starts_with($href, '/')) {
                $href = self::spaPathForCiRoute($href, $spaUrl);
            }

            $icon = trim((string) ($row->icon_class ?: 'fa-th'));
            if ($icon !== '' && ! str_starts_with($icon, 'fa ') && str_starts_with($icon, 'fa-')) {
                $icon = 'fa '.$icon;
            }

            $isActive = $activeModuleKey !== ''
                ? $moduleKey === $activeModuleKey
                : false;

            $modules[] = [
                'id' => $moduleKey,
                'label' => (string) $row->system_name,
                'description' => (string) ($row->description ?? ''),
                'href' => $href,
                'icon' => $icon,
                'is_active' => $isActive,
                'opens_in_new_tab' => $absolute && ! $ssoLaunch,
                'module_key' => $moduleKey,
                'sso_launch' => $ssoLaunch,
                'launch_url' => $ssoLaunch ? $launchUrl : null,
            ];
        }

        return ['home' => $home, 'modules' => $modules];
    }

    /**
     * @param  array<string, mixed>  $session
     */
    public static function resolveHref(object $row, array $session, int $roleId = 0): ?string
    {
        $resolver = (string) ($row->target_resolver ?? 'codeigniter');
        $legacyBase = rtrim(self::publicBase(
            (string) config('staff-portal.legacy_base_url', '/staff/'),
            '/staff/',
        ), '/');

        if ($resolver === 'codeigniter') {
            $path = (string) $row->base_url;
            if (! empty($row->alternate_for_role_id)
                && (int) $row->alternate_for_role_id === $roleId
                && ! empty($row->alternate_base_url)
            ) {
                $path = (string) $row->alternate_base_url;
            }
            $path = trim($path, '/');

            return $path === '' ? null : $path;
        }

        if ($resolver === 'staff_app_token') {
            $seg = trim((string) $row->base_url, '/');
            if ($seg === '') {
                return null;
            }
            $url = $legacyBase.'/'.$seg;
            // APM SSO must not use the directory root (Apache trailing-slash issues).
            if ($seg === 'apm' && (int) ($row->uses_staff_portal_token ?? 0) === 1) {
                $url .= '/sso';
            }

            return $url;
        }

        if ($resolver === 'finance_host') {
            return self::resolveFinanceHostHref($row);
        }

        if ($resolver === 'external_microservice') {
            return self::resolveExternalMicroserviceHref($row);
        }

        return null;
    }

    /**
     * Map CI3 in-app routes to the Vue SPA when serving from staff-portal.
     */
    protected static function spaPathForCiRoute(string $ciPath, string $spaUrl): string
    {
        $ciPath = trim($ciPath, '/');
        $spa = rtrim($spaUrl, '/');
        if ($ciPath === '' || $ciPath === 'dashboard' || str_starts_with($ciPath, 'dashboard/')) {
            return $spa.'/dashboard';
        }
        if ($ciPath === 'auth/profile' || str_starts_with($ciPath, 'auth/profile')) {
            return $spa.'/profile';
        }

        // Other CI routes still live under the legacy staff mount.
        $legacyBase = rtrim(self::publicBase(
            (string) config('staff-portal.legacy_base_url', '/staff/'),
            '/staff/',
        ), '/');

        return $legacyBase.'/'.$ciPath;
    }

    protected static function resolveFinanceHostHref(object $row): ?string
    {
        $host = (string) (request()->getHost() ?? '');
        $isLocal = $host !== '' && (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1'));

        if ($isLocal) {
            $devBase = trim((string) ($row->base_url_development ?? ''), '/');
            if ($devBase === '') {
                $devBase = 'http://localhost/staff/finance';
            }
            if (! str_starts_with($devBase, 'http')) {
                $devBase = 'http://'.$devBase;
            }
            $url = rtrim($devBase, '/');
            if (preg_match('#^https?://[^/]+/?$#', $url)) {
                $url = rtrim($url, '/').'/staff/finance';
            }

            return $url;
        }

        $scheme = request()->isSecure() ? 'https' : 'http';
        $prod = trim((string) ($row->base_url_production ?? ''), '/');
        if ($prod !== '') {
            if (preg_match('#^https?://#i', $prod)) {
                return self::rewriteAbsoluteIfForeignHost(rtrim($prod, '/'));
            }

            return $scheme.'://'.$host.'/'.$prod;
        }

        return $scheme.'://'.$host.'/staff/finance';
    }

    protected static function resolveExternalMicroserviceHref(object $row): ?string
    {
        $host = (string) (request()->getHost() ?? '');
        $isLocal = $host !== '' && (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1'));
        $url = $isLocal
            ? trim((string) ($row->base_url_development ?? ''))
            : trim((string) ($row->base_url_production ?? ''));
        if ($url === '') {
            $url = trim((string) ($row->base_url ?? ''));
        }
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.ltrim(preg_replace('#^[\\/]+#', '', $url) ?? $url, '/');
        }
        $url = rtrim($url, '/');
        if ($url === '' || preg_match('#^https?:/?$#i', $url)) {
            return null;
        }

        return self::rewriteAbsoluteIfForeignHost($url);
    }

    /**
     * Turn configured APP/SPA/legacy URLs into same-origin paths.
     * Production .env often still has http://localhost/...; cards must follow the live host.
     */
    protected static function publicBase(string $configured, string $fallbackPath): string
    {
        $fallbackPath = '/'.ltrim($fallbackPath, '/');
        $configured = trim($configured);

        if ($configured === '') {
            return rtrim($fallbackPath, '/');
        }

        if (! preg_match('#^https?://#i', $configured)) {
            return rtrim('/'.ltrim($configured, '/'), '/') ?: rtrim($fallbackPath, '/');
        }

        $parts = parse_url($configured);
        $cfgHost = (string) ($parts['host'] ?? '');
        $path = (string) ($parts['path'] ?? '');
        if ($path === '' || $path === '/') {
            $path = $fallbackPath;
        }

        $reqHost = '';
        try {
            $reqHost = (string) request()->getHost();
        } catch (\Throwable) {
            $reqHost = '';
        }

        $cfgIsLocal = $cfgHost !== '' && (str_contains($cfgHost, 'localhost') || str_contains($cfgHost, '127.0.0.1'));
        $reqIsLocal = $reqHost !== '' && (str_contains($reqHost, 'localhost') || str_contains($reqHost, '127.0.0.1'));

        if ($cfgIsLocal && ! $reqIsLocal) {
            return rtrim($path, '/') ?: rtrim($fallbackPath, '/');
        }

        if ($reqHost !== '' && $cfgHost !== '' && strcasecmp($cfgHost, $reqHost) !== 0 && ! $reqIsLocal) {
            return rtrim($path, '/') ?: rtrim($fallbackPath, '/');
        }

        if ($cfgHost === $reqHost || $reqHost === '') {
            return rtrim($path, '/') ?: rtrim($fallbackPath, '/');
        }

        return rtrim($configured, '/');
    }

    /**
     * Drop localhost (or other foreign) hosts from absolute URLs when the request is on production.
     */
    protected static function rewriteAbsoluteIfForeignHost(string $url): string
    {
        if (! preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        $reqHost = '';
        try {
            $reqHost = (string) request()->getHost();
        } catch (\Throwable) {
            $reqHost = '';
        }

        $urlIsLocal = $host !== '' && (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1'));
        $reqIsLocal = $reqHost !== '' && (str_contains($reqHost, 'localhost') || str_contains($reqHost, '127.0.0.1'));

        if ($urlIsLocal && ! $reqIsLocal) {
            return rtrim($path, '/') ?: '/';
        }

        return $url;
    }
}
