<?php

namespace Modules\Core\Support;

use App\Support\SsoJwt;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\PortalUser;

/**
 * Cross-module SSO launch (replaces CI3 home/launch_module + sso_launch_helper).
 */
final class StaffSsoLaunch
{
    /**
     * @return array<string, mixed>
     */
    public static function compactClaims(array $session): array
    {
        $keys = [
            'staff_id', 'auth_staff_id', 'user_id', 'permissions', 'base_url',
            'work_email', 'email', 'private_email', 'mail', 'userPrincipalName',
            'name', 'fname', 'lname', 'title', 'role', 'role_id',
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

    public static function findEnabledModule(string $moduleKey): ?object
    {
        $key = self::normalizeModuleKey($moduleKey);
        if ($key === '' || ! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
            return null;
        }
        if (! \App\Support\LegacySchema::has('cbp_modules')) {
            return null;
        }

        return DB::table('cbp_modules')
            ->where('module_key', $key)
            ->where('is_enabled', 1)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $session  PortalUser::toSessionArray()
     */
    public static function userCanAccessModule(array $session, object $row): bool
    {
        $perm = (string) ($row->permission_code ?? '');
        if ($perm === '') {
            return false;
        }
        $permissions = $session['permissions'] ?? [];
        $permList = array_map('strval', (array) $permissions);
        if (! in_array($perm, $permList, true)) {
            return false;
        }
        $roleId = (int) ($session['role_id'] ?? $session['role'] ?? 0);
        if ((int) ($row->is_production ?? 1) === 0 && $roleId !== 10) {
            return false;
        }

        return true;
    }

    public static function acceptUrlForModule(object $row): ?string
    {
        $resolver = (string) ($row->target_resolver ?? '');
        $legacyBase = rtrim(self::legacyStaffBase(), '/');

        if ($resolver === 'staff_app_token') {
            $seg = trim((string) ($row->base_url ?? ''), '/');
            if ($seg === '') {
                return null;
            }
            if ($seg === 'apm') {
                return $legacyBase.'/apm/sso/accept';
            }
            if ($seg === 'finance') {
                return $legacyBase.'/finance/sso/accept';
            }
            if ($seg === 'helpdesk') {
                return $legacyBase.'/helpdesk/backend/sso/accept';
            }

            return $legacyBase.'/'.$seg.'/sso/accept';
        }

        if ($resolver === 'finance_host') {
            if (self::isLocalHost()) {
                return $legacyBase.'/finance/sso/accept';
            }
            $scheme = request()->isSecure() ? 'https' : 'http';
            $host = (string) (request()->getHost() ?: 'localhost');
            $prod = trim((string) ($row->base_url_production ?? ''), '/');
            if ($prod !== '' && preg_match('#^https?://#i', $prod)) {
                return rtrim($prod, '/').'/sso/accept';
            }

            return $scheme.'://'.$host.'/staff/finance/sso/accept';
        }

        if ($resolver === 'external_microservice') {
            $url = '';
            if (self::isLocalHost()) {
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
            if (! preg_match('#^https?://#i', $url)) {
                $url = 'https://'.ltrim(preg_replace('#^[\\/]+#', '', $url) ?? $url, '/');
            }
            // Helpdesk SPA mount posts to backend accept.
            $normalized = rtrim($url, '/');
            if (str_ends_with($normalized, '/helpdesk') || str_ends_with($normalized, '/helpdesk/')) {
                return rtrim($normalized, '/').'/backend/sso/accept';
            }

            return $normalized.'/sso/accept';
        }

        return null;
    }

    public static function isAllowedAcceptUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $requestHost = strtolower((string) (request()->getHost() ?: ''));
        if ($host === '' || $requestHost === '') {
            return false;
        }
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $requestHost = preg_replace('/:\d+$/', '', $requestHost) ?? $requestHost;
        if ($host !== $requestHost) {
            return false;
        }
        $path = (string) parse_url($url, PHP_URL_PATH);

        return $path !== '' && str_contains($path, '/sso/accept');
    }

    /**
     * @return array{ok: true, accept_url: string, staff_sso_jwt: string, label: string, module_key: string}|array{ok: false, message: string, status: int}
     */
    public static function prepareLaunch(PortalUser $user, string $moduleKey): array
    {
        $row = self::findEnabledModule($moduleKey);
        if (! $row) {
            return ['ok' => false, 'message' => 'Invalid or disabled module.', 'status' => 400];
        }

        $session = $user->toSessionArray();
        if (! self::userCanAccessModule($session, $row)) {
            return ['ok' => false, 'message' => 'You do not have permission to open this module.', 'status' => 403];
        }

        if ((int) ($row->uses_staff_portal_token ?? 0) !== 1) {
            $href = CbpModulesNav::resolveHref($row, $session, (int) ($session['role_id'] ?? 0));
            if ($href === null || $href === '') {
                return ['ok' => false, 'message' => 'Module link is not configured.', 'status' => 500];
            }

            return [
                'ok' => true,
                'accept_url' => '',
                'staff_sso_jwt' => '',
                'label' => (string) $row->system_name,
                'module_key' => (string) $row->module_key,
                'redirect_url' => $href,
            ];
        }

        $acceptUrl = self::acceptUrlForModule($row);
        if ($acceptUrl === null || $acceptUrl === '') {
            return ['ok' => false, 'message' => 'SSO accept URL is not configured for this module.', 'status' => 500];
        }
        if (! self::isAllowedAcceptUrl($acceptUrl)) {
            return ['ok' => false, 'message' => 'SSO target is not allowed.', 'status' => 500];
        }

        $jwt = SsoJwt::encode(
            self::compactClaims($session),
            (int) config('staff-portal.sso.token_ttl', 7200)
        );

        return [
            'ok' => true,
            'accept_url' => $acceptUrl,
            'staff_sso_jwt' => $jwt,
            'label' => (string) $row->system_name,
            'module_key' => (string) $row->module_key,
        ];
    }

    protected static function legacyStaffBase(): string
    {
        $configured = trim((string) config('staff-portal.legacy_base_url', '/staff/'));
        if ($configured === '') {
            $configured = '/staff/';
        }
        if (! preg_match('#^https?://#i', $configured)) {
            $scheme = request()->getScheme();
            $host = request()->getHttpHost();

            return $scheme.'://'.$host.'/'.trim($configured, '/');
        }

        $parts = parse_url($configured);
        $cfgHost = (string) ($parts['host'] ?? '');
        $path = (string) ($parts['path'] ?? '/staff');
        $reqHost = (string) (request()->getHost() ?: '');
        $cfgIsLocal = $cfgHost !== '' && (str_contains($cfgHost, 'localhost') || str_contains($cfgHost, '127.0.0.1'));
        $reqIsLocal = $reqHost !== '' && (str_contains($reqHost, 'localhost') || str_contains($reqHost, '127.0.0.1'));

        if (($cfgIsLocal && ! $reqIsLocal) || ($reqHost !== '' && $cfgHost !== '' && strcasecmp($cfgHost, $reqHost) !== 0 && ! $reqIsLocal)) {
            return request()->getScheme().'://'.request()->getHttpHost().rtrim($path, '/');
        }

        return rtrim($configured, '/');
    }

    protected static function isLocalHost(): bool
    {
        $host = (string) (request()->getHost() ?: '');

        return $host !== '' && (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1'));
    }
}
