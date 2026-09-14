<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CbpModulesNavService;
use App\Services\StaffPortalReferenceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Proxies Staff Share API cbp_modules for the Helpdesk top nav (same links as Staff portal).
 */
class CbpModulesController extends Controller
{
    public function __invoke(
        Request $request,
        CbpModulesNavService $nav,
        StaffPortalReferenceClient $client,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $payload = $nav->resolveForUser($user, $client);

        return response()->json($payload);
    }
}
