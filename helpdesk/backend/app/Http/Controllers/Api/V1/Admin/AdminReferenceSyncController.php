<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\HelpdeskAuditLogger;
use App\Services\ReferenceDataSyncService;
use App\Services\StaffPortalReferenceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReferenceSyncController extends Controller
{
    use AuthorizesHelpdeskAdmin;

    public function store(
        Request $request,
        ReferenceDataSyncService $sync,
        StaffPortalReferenceClient $client,
        HelpdeskAuditLogger $auditLogger,
    ): JsonResponse {
        $this->ensureHelpdeskAdmin($request);

        try {
            $sync->flushCaches();
            $counts = $sync->warmCaches($client);
        } catch (\Throwable $e) {
            $auditLogger->log('reference_data.sync_failed', null, null, null, [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        }

        $auditLogger->log('reference_data.sync', null, null, null, [
            'result' => $counts,
        ]);

        return response()->json([
            'data' => array_merge($counts, [
                'cache_ttl_seconds' => max(30, (int) config('helpdesk.reference_data_cache_ttl', 300)),
            ]),
        ]);
    }
}
