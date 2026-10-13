<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StaffPortalOrgClient;
use App\Support\StaffDivisionContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Models\PortalUser;
use Modules\Core\Support\CbpModulesNav;
use Modules\Settings\Services\CbpModulesAdminService;

/**
 * CBP Modules top-nav for Finance SPA (SSO Bearer auth).
 * Prefer Staff Portal Share API; fall back to staff-DB cbp_modules (not finance DB).
 */
class CbpModulesController extends Controller
{
    /** Matches cbp_modules.module_key for this app. */
    public const MODULE_KEY = 'finance_management';

    public function __construct(
        protected CbpModulesAdminService $cbpModulesAdmin,
        protected StaffPortalOrgClient $staffApi,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $staffId = (int) $request->attributes->get('risk_staff_id', 0);
        if ($staffId < 1) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            if ($this->cbpModulesAdmin->tableExists()) {
                $this->cbpModulesAdmin->ensureCoreModules();
            }
        } catch (\Throwable $e) {
            $this->safeLog('Finance CBP modules: ensureCoreModules failed', [
                'message' => $e->getMessage(),
            ]);
        }

        $path = trim((string) $request->query('path', ''), '/');
        // Empty exclude = show every module (including Finance).
        $exclude = trim((string) $request->query('exclude', ''));
        $active = trim((string) $request->query('active', ''));
        if ($active === '') {
            $active = self::MODULE_KEY;
        }

        $permissionIds = array_map('strval', (array) $request->attributes->get('risk_permissions', []));
        $shareError = null;

        try {
            if ($this->staffApi->isConfigured()) {
                $payload = $this->staffApi->fetchCbpModules($staffId, $exclude, $active, $permissionIds);

                return response()->json([
                    'data' => $payload,
                    'meta' => [
                        'source' => 'staff_share_api',
                        'degraded' => false,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            $shareError = $e->getMessage();
            $this->safeLog('Finance CBP modules: Staff Share API unavailable', [
                'staff_id' => $staffId,
                'message' => $shareError,
            ]);
        }

        try {
            $payload = $this->localPayload($staffId, $path, $exclude, $active, $permissionIds);
            if (($payload['modules'] ?? []) !== []) {
                return response()->json([
                    'data' => $payload,
                    'meta' => [
                        'source' => 'staff_cbp_modules',
                        'degraded' => $shareError !== null,
                        'share_error' => $shareError,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            $this->safeLog('Finance CBP modules: local staff-DB payload failed', [
                'staff_id' => $staffId,
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'data' => $this->staticFallbackPayload($active),
            'meta' => [
                'source' => 'static_fallback',
                'degraded' => true,
                'share_error' => $shareError,
            ],
        ]);
    }

    /**
     * @param  list<string>  $permissionIds
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    private function localPayload(
        int $staffId,
        string $path,
        string $exclude,
        string $active,
        array $permissionIds,
    ): array {
        $cacheKey = 'fin_cbp_modules_local_v2_'.$staffId.'_'.md5($exclude.'|'.$active.'|'.implode(',', $permissionIds));
        $build = function () use ($staffId, $path, $exclude, $active, $permissionIds) {
            $session = $this->sessionForStaff($staffId, $permissionIds);

            return CbpModulesNav::payload($session, $path, $exclude, $active);
        };

        try {
            return Cache::remember($cacheKey, 120, $build);
        } catch (\Throwable) {
            return $build();
        }
    }

    /**
     * @param  list<string>  $permissionIds
     * @return array<string, mixed>
     */
    private function sessionForStaff(int $staffId, array $permissionIds): array
    {
        $fallback = [
            'staff_id' => $staffId,
            'auth_staff_id' => $staffId,
            'permissions' => $permissionIds,
            'role_id' => 0,
        ];

        try {
            if (! StaffDivisionContext::portalSchema()->hasTable('user')) {
                return $fallback;
            }

            $user = PortalUser::on(StaffDivisionContext::portalConnection())
                ->where('auth_staff_id', $staffId)
                ->where('status', 1)
                ->first();

            if ($user instanceof PortalUser) {
                return $user->toSessionArray();
            }
        } catch (\Throwable $e) {
            $this->safeLog('Finance CBP modules: portal user lookup skipped', [
                'staff_id' => $staffId,
                'message' => $e->getMessage(),
            ]);
        }

        return $fallback;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function safeLog(string $message, array $context = []): void
    {
        try {
            Log::warning($message, $context);
        } catch (\Throwable) {
            // Logging must never break the CBP modules nav response.
        }
    }

    /**
     * Last-resort links when Share API and staff cbp_modules are unavailable.
     *
     * @return array{home: array<string, mixed>, modules: list<array<string, mixed>>}
     */
    private function staticFallbackPayload(string $activeModuleKey): array
    {
        $staffBase = rtrim((string) config('app.staff_portal_url', ''), '/');
        if ($staffBase === '') {
            $staffBase = rtrim((string) config('staff-portal.legacy_base_url', '/staff'), '/');
        }
        if ($staffBase === '' || $staffBase === '/') {
            $staffBase = '/staff';
        }

        $modules = [
            ['id' => 'staff_portal', 'label' => 'Human Resource', 'href' => $staffBase.'/', 'icon' => 'fa fa-users'],
            ['id' => 'approvals_management', 'label' => 'Approvals Management', 'href' => $staffBase.'/apm', 'icon' => 'fa fa-sitemap'],
            ['id' => 'finance_management', 'label' => 'Finance Management', 'href' => $staffBase.'/finance', 'icon' => 'fa fa-wallet'],
            ['id' => 'helpdesk_itsm', 'label' => 'HelpDesk', 'href' => $staffBase.'/helpdesk', 'icon' => 'fa fa-headset'],
            ['id' => 'risk_register', 'label' => 'Risk Register', 'href' => $staffBase.'/risk-register', 'icon' => 'fa fa-shield-halved'],
        ];

        $out = [];
        foreach ($modules as $mod) {
            $out[] = [
                'id' => $mod['id'],
                'label' => $mod['label'],
                'description' => '',
                'href' => $mod['href'],
                'icon' => $mod['icon'],
                'is_active' => $mod['id'] === $activeModuleKey,
                'opens_in_new_tab' => false,
                'module_key' => $mod['id'],
                'sso_launch' => false,
                'launch_url' => null,
            ];
        }

        return [
            'home' => [
                'id' => 'cbp_home',
                'label' => 'CBP Home',
                'description' => '',
                'href' => $staffBase.'/',
                'is_active' => false,
            ],
            'modules' => $out,
        ];
    }
}
