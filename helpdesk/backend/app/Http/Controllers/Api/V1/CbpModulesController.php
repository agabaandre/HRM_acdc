<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StaffPortalReferenceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Proxies Staff Share API cbp_modules for the Helpdesk top nav (same links as Staff portal).
 */
class CbpModulesController extends Controller
{
    public function __invoke(Request $request, StaffPortalReferenceClient $client): JsonResponse
    {
        $profile = $request->user()?->helpdeskProfile;
        abort_unless(
            $profile && $profile->staff_id,
            403,
            'A Staff-linked helpdesk profile is required to load CBP modules.'
        );

        if (! $client->isConfigured()) {
            return response()->json([
                'message' => 'Staff API credentials are not configured. Set HELPDESK_STAFF_API_USERNAME and HELPDESK_STAFF_API_PASSWORD.',
            ], 503);
        }

        $staffId = (int) $profile->staff_id;
        $permissionIds = is_array($profile->staff_portal_permissions)
            ? array_map('strval', $profile->staff_portal_permissions)
            : [];
        $ttl = max(60, (int) config('helpdesk.reference_data_cache_ttl', 300));
        $permKey = $permissionIds !== [] ? md5(implode(',', $permissionIds)) : 'db';
        $cacheKey = 'helpdesk_cbp_modules_v3_'.$staffId.'_'.$permKey;

        try {
            $data = Cache::remember(
                $cacheKey,
                $ttl,
                fn () => $client->fetchCbpModules($staffId, 'helpdesk_itsm', 'helpdesk_itsm', $permissionIds)
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'cache_ttl_seconds' => $ttl,
                'source' => 'staff_share_api',
            ],
        ]);
    }
}
