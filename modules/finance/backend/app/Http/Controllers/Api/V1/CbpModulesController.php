<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StaffPortalOrgClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Models\PortalUser;
use Modules\Core\Support\CbpModulesNav;
use Modules\Settings\Services\CbpModulesAdminService;

/**
 * CBP Modules top-nav for Risk Register SPA (SSO Bearer auth).
 * Prefer Staff Portal Share API (Helpdesk pattern); fall back to local cbp_modules.
 */
class CbpModulesController extends Controller
{
    public const MODULE_KEY = 'finance';

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
            Log::warning('Finance CBP modules: ensureCoreModules failed', [
                'message' => $e->getMessage(),
            ]);
        }

        $path = trim((string) $request->query('path', ''), '/');
        $exclude = trim((string) $request->query('exclude', self::MODULE_KEY));
        $active = trim((string) $request->query('active', ''));
        if ($active === '') {
            $active = self::MODULE_KEY;
        }

        $permissionIds = array_map('strval', (array) $request->attributes->get('risk_permissions', []));

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
            Log::warning('Risk Register CBP modules: Staff Share API unavailable', [
                'staff_id' => $staffId,
                'message' => $e->getMessage(),
            ]);
        }

        $payload = $this->localPayload($staffId, $path, $exclude, $active, $permissionIds);

        return response()->json([
            'data' => $payload,
            'meta' => [
                'source' => 'local_cbp_modules',
                'degraded' => false,
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
        $cacheKey = 'rr_cbp_modules_local_v1_'.$staffId.'_'.md5($exclude.'|'.$active.'|'.implode(',', $permissionIds));
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
        $user = PortalUser::query()
            ->where('auth_staff_id', $staffId)
            ->where('status', 1)
            ->first();

        if ($user instanceof PortalUser) {
            return $user->toSessionArray();
        }

        return [
            'staff_id' => $staffId,
            'auth_staff_id' => $staffId,
            'permissions' => $permissionIds,
            'role_id' => 0,
        ];
    }
}
