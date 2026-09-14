<?php

namespace Modules\Core\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Models\PortalUser;
use Modules\Core\Support\CbpModulesNav;
use Modules\Settings\Services\CbpModulesAdminService;

class PortalApiController extends Controller
{
    public function __construct(
        protected CbpModulesAdminService $cbpModulesAdmin,
    ) {}

    public function cbpModules(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof PortalUser) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($this->cbpModulesAdmin->tableExists()) {
            $this->cbpModulesAdmin->ensureCoreModules();
        }

        $session = $user->toSessionArray();
        $path = trim((string) $request->header('X-Portal-Path', $request->query('path', '')), '/');
        $exclude = trim((string) $request->query('exclude', ''));
        $active = trim((string) $request->query('active', ''));

        // When browsing the staff SPA (not CBP home), highlight Human Resource.
        if ($active === '' && $path !== '' && $path !== 'home') {
            $active = 'staff_portal';
        }

        $payload = CbpModulesNav::payload($session, $path, $exclude, $active);

        return response()->json([
            'data' => $payload,
            'meta' => [
                'source' => \App\Support\LegacySchema::has('cbp_modules') ? 'cbp_modules' : 'empty',
                'degraded' => false,
            ],
        ]);
    }
}
