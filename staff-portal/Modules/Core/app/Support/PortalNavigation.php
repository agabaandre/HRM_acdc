<?php

namespace Modules\Core\Support;

use App\Support\SsoJwt;
use Illuminate\Support\Facades\DB;

class PortalNavigation
{
    /**
     * @param  list<int|string>  $permissions
     */
    public static function can(array $permissions, int|string $code): bool
    {
        return in_array($code, $permissions, true)
            || in_array((int) $code, array_map('intval', $permissions), true);
    }

    public static function active(string ...$segments): string
    {
        $current = request()->segment(1) ?? '';

        return in_array($current, $segments, true) ? 'active' : '';
    }

    /**
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    public static function cbpModulesPayload(): array
    {
        $session = session('user', []);
        $permissions = $session['permissions'] ?? [];
        $roleId = (int) ($session['role_id'] ?? $session['role'] ?? 0);
        $current = trim(request()->path(), '/');

        $home = [
            'label' => 'CBP Home',
            'href' => route('core.home'),
            'is_active' => $current === '' || $current === 'home',
        ];

        $modules = [];

        if (! \App\Support\LegacySchema::has('cbp_modules')) {
            return ['home' => $home, 'modules' => $modules];
        }

        $rows = DB::table('cbp_modules')
            ->where('is_enabled', 1)
            ->orderBy('sort_order')
            ->get();

        foreach ($rows as $row) {
            $code = (string) $row->permission_code;
            if (! self::can($permissions, $code)) {
                continue;
            }
            if ((int) $row->is_production === 0 && $roleId !== 10) {
                continue;
            }

            $href = self::resolveModuleHref($row, $session);
            $modules[] = [
                'label' => $row->system_name,
                'href' => $href,
                'icon' => $row->icon_class ?: 'fa-th',
                'is_active' => false,
                'opens_in_new_tab' => str_starts_with($href, 'http'),
            ];
        }

        return ['home' => $home, 'modules' => $modules];
    }

    /**
     * @param  array<string, mixed>  $session
     */
    protected static function resolveModuleHref(object $row, array $session): string
    {
        $url = (string) $row->base_url;
        if ((int) $row->uses_staff_portal_token === 1) {
            $token = rawurlencode(SsoJwt::encode($session));

            return str_contains($url, '?') ? "{$url}&token={$token}" : "{$url}?token={$token}";
        }

        return $url;
    }
}
