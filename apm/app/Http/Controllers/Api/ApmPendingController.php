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

        // Ensure each category value is a list (array) for mobile consumption (no "0", "1" keys)
        $pendingAsLists = [];
        foreach ($pendingApprovals as $cat => $items) {
            $pendingAsLists[$cat] = array_values($items);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'pending' => $pendingAsLists,
                'summary' => $this->summaryForApi($summaryStats),
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
            'data' => $this->summaryForApi($summaryStats),
        ]);
    }

    /**
     * Format summary stats for API: by_category and by_division as lists for easy mobile consumption.
     */
    private function summaryForApi(array $summaryStats): array
    {
        $byCategory = $summaryStats['by_category'] ?? [];
        $byDivision = $summaryStats['by_division'] ?? [];
        $byCategoryList = [];
        foreach ($byCategory as $name => $count) {
            $byCategoryList[] = ['name' => $name, 'count' => $count];
        }
        $byDivisionList = [];
        foreach ($byDivision as $name => $count) {
            $byDivisionList[] = ['name' => $name, 'count' => $count];
        }
        return [
            'total_pending' => $summaryStats['total_pending'] ?? 0,
            'by_category' => $byCategoryList,
            'by_division' => $byDivisionList,
            'oldest_pending' => $summaryStats['oldest_pending'] ?? null,
            'newest_pending' => $summaryStats['newest_pending'] ?? null,
        ];
    }
}
