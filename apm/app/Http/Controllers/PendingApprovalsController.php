<?php

namespace App\Http\Controllers;

use App\Services\PendingApprovalsService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class PendingApprovalsController extends Controller
{
    protected $pendingApprovalsService;

    public function __construct()
    {
        // Pass session data to the service
        $sessionData = [
            'staff_id' => user_session('staff_id'),
            'division_id' => user_session('division_id'),
            'permissions' => user_session('permissions', []),
            'name' => user_session('name'),
            'email' => user_session('email'),
            'base_url' => user_session('base_url')
        ];
        
        $this->pendingApprovalsService = new PendingApprovalsService($sessionData);
    }

    /**
     * Display the pending approvals dashboard
     */
    public function index(Request $request): View
    {
        $category = $request->get('category', 'all');
        $division = $request->get('division', 'all');
        
        // Get all pending approvals
        $pendingApprovals = $this->pendingApprovalsService->getPendingApprovals();
        
        // Get summary statistics
        $summaryStats = $this->pendingApprovalsService->getSummaryStats();
        
        // Filter by category if specified
        if ($category !== 'all') {
            $pendingApprovals = [$category => $pendingApprovals[$category] ?? []];
        }
        
        // Get divisions for filter dropdown
        $divisions = \App\Models\Division::orderBy('division_name')->get();
        
        // Get categories for filter dropdown
        $categories = collect($pendingApprovals)->keys()->map(function ($cat) {
            return [
                'value' => $cat,
                'label' => $cat,
                'count' => count($pendingApprovals[$cat] ?? [])
            ];
        })->prepend(['value' => 'all', 'label' => 'All Categories', 'count' => $summaryStats['total_pending']]);
        
        return view('pending-approvals.index', compact(
            'pendingApprovals',
            'summaryStats',
            'categories',
            'divisions',
            'category',
            'division'
        ));
    }

    /**
     * Get pending approvals as JSON (for AJAX requests)
     */
    public function getPendingApprovals(Request $request): JsonResponse
    {
        $category = $request->get('category', 'all');
        $division = $request->get('division', 'all');
        
        $pendingApprovals = $this->pendingApprovalsService->getPendingApprovals();
        $summaryStats = $this->pendingApprovalsService->getSummaryStats();
        
        // Filter by category if specified
        if ($category !== 'all') {
            $pendingApprovals = [$category => $pendingApprovals[$category] ?? []];
        }
        
        return response()->json([
            'success' => true,
            'data' => $pendingApprovals,
            'summary' => $summaryStats,
            'filters' => [
                'category' => $category,
                'division' => $division
            ]
        ]);
    }

    /**
     * Get pending approvals for a specific category
     */
    public function getByCategory(string $category): JsonResponse
    {
        $pendingItems = $this->pendingApprovalsService->getPendingByCategory($category);
        
        return response()->json([
            'success' => true,
            'data' => $pendingItems,
            'category' => $category,
            'count' => $pendingItems->count()
        ]);
    }

    /**
     * Get summary statistics
     */
    public function getSummary(): JsonResponse
    {
        $summaryStats = $this->pendingApprovalsService->getSummaryStats();
        
        return response()->json([
            'success' => true,
            'data' => $summaryStats
        ]);
    }

    /**
     * Get recent pending approvals for header dropdown
     */
    public function getRecentPending(): JsonResponse
    {
        $pendingApprovals = $this->pendingApprovalsService->getPendingApprovals();
        $summaryStats = $this->pendingApprovalsService->getSummaryStats();
        
        // Flatten all pending items and get the 5 most recent
        $allPending = collect($pendingApprovals)->flatten(1);
        $recentItems = $allPending->sortByDesc('date_received')->take(5);
        
        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summaryStats,
                'recent_items' => $recentItems->values()->toArray()
            ]
        ]);
    }

    /**
     * Mark an item as viewed (for tracking purposes)
     */
    public function markAsViewed(Request $request): JsonResponse
    {
        $request->validate([
            'item_type' => 'required|string',
            'item_id' => 'required|integer'
        ]);

        // Here you could implement a tracking system to mark items as viewed
        // For now, we'll just return success
        
        return response()->json([
            'success' => true,
            'message' => 'Item marked as viewed'
        ]);
    }

    /**
     * Send pending approvals notification
     */
    public function sendNotification(): JsonResponse
    {
        try {
            $success = $this->pendingApprovalsService->sendPendingApprovalsNotification();
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Notification sent successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No approvers to notify or notification failed'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending notification: ' . $e->getMessage()
            ], 500);
        }
    }



}
