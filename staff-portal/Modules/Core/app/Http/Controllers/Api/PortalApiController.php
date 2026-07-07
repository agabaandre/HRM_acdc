<?php

namespace Modules\Core\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SsoJwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\PortalUser;
use Modules\Core\Support\PortalNavigation;

class PortalApiController extends Controller
{
    public function cbpModules(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof PortalUser) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $session = $user->toSessionArray();
        $permissions = $session['permissions'] ?? [];
        $roleId = (int) ($session['role_id'] ?? $session['role'] ?? 0);

        $spaUrl = rtrim((string) config('staff-portal.spa_url', '/'), '/').'/';

        $home = [
            'label' => 'CBP Home',
            'href' => $spaUrl,
            'is_active' => false,
        ];

        $modules = [];

        if (! \App\Support\LegacySchema::has('cbp_modules')) {
            return response()->json(['home' => $home, 'modules' => $modules]);
        }

        $rows = DB::table('cbp_modules')
            ->where('is_enabled', 1)
            ->orderBy('sort_order')
            ->get();

        foreach ($rows as $row) {
            $code = (string) $row->permission_code;
            if (! PortalNavigation::can($permissions, $code)) {
                continue;
            }
            if ((int) $row->is_production === 0 && $roleId !== 10) {
                continue;
            }

            $href = $this->resolveModuleHref($row, $session);
            $modules[] = [
                'label' => $row->system_name,
                'href' => $href,
                'icon' => $row->icon_class ?: 'fa-th',
                'is_active' => false,
                'opens_in_new_tab' => str_starts_with($href, 'http'),
                'module_key' => $row->module_key ?? null,
            ];
        }

        return response()->json(['home' => $home, 'modules' => $modules]);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    protected function resolveModuleHref(object $row, array $session): string
    {
        $url = (string) $row->base_url;
        if ((int) $row->uses_staff_portal_token === 1) {
            $token = rawurlencode(SsoJwt::encode($session));

            return str_contains($url, '?') ? "{$url}&token={$token}" : "{$url}?token={$token}";
        }

        return $url;
    }
}
