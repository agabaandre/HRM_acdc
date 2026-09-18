<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\SsoJwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Models\PortalUser;
use Modules\Core\Support\StaffSsoLaunch;

/**
 * Launch sibling CBP modules from Risk Register (SSO Bearer session).
 */
class CbpModulesLaunchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $staffId = (int) $request->attributes->get('risk_staff_id', 0);
        if ($staffId < 1) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $moduleKey = trim((string) $request->input('module_key', ''));
        if ($moduleKey === '') {
            return response()->json(['message' => 'module_key is required'], 422);
        }

        $user = PortalUser::query()
            ->where('auth_staff_id', $staffId)
            ->where('status', 1)
            ->first();

        if ($user instanceof PortalUser) {
            $result = StaffSsoLaunch::prepareLaunch($user, $moduleKey);
        } else {
            $result = $this->launchFromRiskSession($request, $moduleKey);
        }

        if (! ($result['ok'] ?? false)) {
            return response()->json(
                ['message' => $result['message'] ?? 'Launch failed'],
                (int) ($result['status'] ?? 400)
            );
        }

        if (! empty($result['redirect_url'])) {
            return response()->json([
                'redirect_url' => $result['redirect_url'],
                'label' => $result['label'] ?? '',
                'module_key' => $result['module_key'] ?? $moduleKey,
            ]);
        }

        return response()->json([
            'accept_url' => $result['accept_url'] ?? '',
            'staff_sso_jwt' => $result['staff_sso_jwt'] ?? '',
            'label' => $result['label'] ?? '',
            'module_key' => $result['module_key'] ?? $moduleKey,
        ]);
    }

    /**
     * @return array{ok: true, accept_url: string, staff_sso_jwt: string, label: string, module_key: string}|array{ok: true, redirect_url: string, label: string, module_key: string, accept_url?: string, staff_sso_jwt?: string}|array{ok: false, message: string, status: int}
     */
    private function launchFromRiskSession(Request $request, string $moduleKey): array
    {
        $row = StaffSsoLaunch::findEnabledModule($moduleKey);
        if (! $row) {
            return ['ok' => false, 'message' => 'Invalid or disabled module.', 'status' => 400];
        }

        $session = [
            'staff_id' => (int) $request->attributes->get('risk_staff_id', 0),
            'auth_staff_id' => (int) $request->attributes->get('risk_staff_id', 0),
            'permissions' => array_map('strval', (array) $request->attributes->get('risk_permissions', [])),
            'division_id' => (int) $request->attributes->get('risk_division_id', 0),
            'role_id' => 0,
            'name' => '',
            'email' => '',
        ];

        // Enrich from cached SSO payload when available.
        $bearer = $request->bearerToken();
        if ($bearer) {
            $cached = \Illuminate\Support\Facades\Cache::get('risk_api_token:'.$bearer);
            if (is_array($cached)) {
                foreach (['name', 'email', 'role_id', 'division_id', 'permissions'] as $key) {
                    if (array_key_exists($key, $cached) && $cached[$key] !== null && $cached[$key] !== '') {
                        $session[$key] = $cached[$key];
                    }
                }
            }
        }

        if (! StaffSsoLaunch::userCanAccessModule($session, $row)) {
            return ['ok' => false, 'message' => 'You do not have permission to open this module.', 'status' => 403];
        }

        if ((int) ($row->uses_staff_portal_token ?? 0) !== 1) {
            $href = \Modules\Core\Support\CbpModulesNav::resolveHref(
                $row,
                $session,
                (int) ($session['role_id'] ?? 0)
            );
            if ($href === null || $href === '') {
                return ['ok' => false, 'message' => 'Module link is not configured.', 'status' => 500];
            }
            if (! preg_match('#^https?://#i', $href) && ! str_starts_with($href, '/')) {
                $spa = rtrim((string) config('staff-portal.spa_url', '/staff/'), '/');
                $href = ($href === 'dashboard' || str_starts_with($href, 'dashboard/'))
                    ? $spa.'/dashboard'
                    : rtrim((string) config('staff-portal.legacy_base_url', '/staff/'), '/').'/'.ltrim($href, '/');
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

        $acceptUrl = StaffSsoLaunch::acceptUrlForModule($row);
        if ($acceptUrl === null || $acceptUrl === '') {
            return ['ok' => false, 'message' => 'SSO accept URL is not configured for this module.', 'status' => 500];
        }

        $jwt = SsoJwt::encode(
            StaffSsoLaunch::compactClaims($session),
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
}
