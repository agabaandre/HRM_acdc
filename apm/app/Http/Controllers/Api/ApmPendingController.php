<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PendingApprovalsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApmPendingController extends Controller
{
    /**
     * Pending approvals for the authenticated API user (same logic as web pending-approvals page).
     * Processed items are not included.
     */
    public function index(Request $request): JsonResponse
    {
        $sessionData = $request->attributes->get('api_user_session');
        if (!$sessionData) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $service = new PendingApprovalsService($sessionData);
        $pendingApprovals = $service->getPendingApprovals();
        $summaryStats = $service->getSummaryStats();

        $category = $request->get('category', 'all');
        if ($category !== 'all') {
            $pendingApprovals = [$category => $pendingApprovals[$category] ?? []];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'pending' => $pendingApprovals,
                'summary' => $summaryStats,
                'filters' => ['category' => $category],
            ],
        ]);
    }

    /**
     * Summary stats only (total and by category).
     */
    public function summary(Request $request): JsonResponse
    {
        $sessionData = $request->attributes->get('api_user_session');
        if (!$sessionData) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $service = new PendingApprovalsService($sessionData);
        $summaryStats = $service->getSummaryStats();

        return response()->json([
            'success' => true,
            'data' => $summaryStats,
        ]);
    }
}
