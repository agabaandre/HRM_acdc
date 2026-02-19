<?php

namespace App\Http\Controllers;

use App\Services\PendingApprovalsService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class PendingApprovalsController extends Controller
{
    use ApproverDashboardHelper;

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
        $staffId = $request->get('staff_id');
        
        $approverInfo = null;
        
        // If staff_id is provided (from dashboard), filter pending approvals for that specific approver
        if ($staffId) {
            // Get approver information (name and roles)
            $staff = \App\Models\Staff::where('staff_id', (int) $staffId)->first();
            if ($staff) {
                // Get roles from workflow definitions
                $approvers = \App\Models\Approver::where('staff_id', (int) $staffId)
                    ->with(['workflowDefinition' => function($query) {
                        $query->where('is_enabled', 1);
                    }])
                    ->get();
                
                $roles = [];
                foreach ($approvers as $approver) {
                    if ($approver->workflowDefinition && $approver->workflowDefinition->is_enabled) {
                        $roleLevel = $approver->workflowDefinition->role . ' (Level ' . $approver->workflowDefinition->approval_order . ')';
                        if (!in_array($roleLevel, $roles)) {
                            $roles[] = $roleLevel;
                        }
                    }
                }
                
                // Also check for division-specific roles
                $approverDivisions = \App\Models\Division::where(function($query) use ($staffId) {
                    $query->where('division_head', $staffId)
                          ->orWhere('focal_person', $staffId)
                          ->orWhere('finance_officer', $staffId)
                          ->orWhere('director_id', $staffId);
                })->get();
                
                foreach ($approverDivisions as $div) {
                    if ($div->division_head == $staffId) {
                        $roles[] = 'Head of Division';
                    }
                    if ($div->focal_person == $staffId) {
                        $roles[] = 'Focal Person';
                    }
                    if ($div->finance_officer == $staffId) {
                        $roles[] = 'Finance Officer';
                    }
                    if ($div->director_id == $staffId) {
                        $roles[] = 'Director';
                    }
                }
                
                $approverInfo = [
                    'name' => trim($staff->fname . ' ' . $staff->lname),
                    'email' => $staff->work_email ?? '',
                    'roles' => array_unique($roles),
                    'division_name' => $staff->division_name ?? 'N/A'
                ];
            }
            
            // Temporarily override the session staff_id to filter by the specified approver
            $this->pendingApprovalsService->setTemporaryStaffId((int) $staffId);
        }
        
        // Get all pending approvals
        $pendingApprovals = $this->pendingApprovalsService->getPendingApprovals();
        
        // Get summary statistics
        $summaryStats = $this->pendingApprovalsService->getSummaryStats();
        
        // Get divisions for filter dropdown
        $divisions = \App\Models\Division::orderBy('division_name')->get();
        
        // Get categories for filter dropdown - group similar items (before filtering)
        $groupedCategories = $this->groupCategoriesForFilter($pendingApprovals, $summaryStats);
        
        // Filter by category if specified (after creating grouped categories)
        if ($category !== 'all') {
            $pendingApprovals = $this->filterByGroupedCategory($pendingApprovals, $category);
        }
        
        // Check if user is an admin assistant
        $isAdminAssistant = is_admin_assistant();
        
        // Reset to original staff_id if it was temporarily changed
        if ($staffId) {
            $this->pendingApprovalsService->resetStaffId();
        }

        // Average approval time for the approver being viewed — use same year/month as approver dashboard so values match
        $year = $request->filled('year') ? (int) $request->get('year') : null;
        $month = $request->filled('month') ? (int) $request->get('month') : null;
        $staffIdForAvg = $staffId ? (int) $staffId : (int) user_session('staff_id');
        $divisionIdForAvg = $staffId ? (optional(\App\Models\Staff::where('staff_id', $staffIdForAvg)->first())->division_id) : user_session('division_id');
        $avgApprovalTimeHours = $this->getAverageApprovalTimeAll($staffIdForAvg, $divisionIdForAvg, $year, $month);
        $avgApprovalTimeDisplay = $this->formatApprovalTime($avgApprovalTimeHours);

        return view('pending-approvals.index', compact(
            'pendingApprovals',
            'summaryStats',
            'groupedCategories',
            'divisions',
            'category',
            'division',
            'isAdminAssistant',
            'staffId',
            'approverInfo',
            'avgApprovalTimeDisplay',
            'avgApprovalTimeHours',
            'year',
            'month'
        ));
    }

    /**
     * Group categories for filter dropdown
     */
    private function groupCategoriesForFilter(array $pendingApprovals, array $summaryStats): array
    {
        $categories = [];
        
        // All Categories
        $categories[] = [
            'value' => 'all',
            'label' => 'All Categories',
            'count' => $summaryStats['total_pending']
        ];
        
        // Matrices
        $matrixCount = count($pendingApprovals['Matrix'] ?? []);
        if ($matrixCount > 0) {
            $categories[] = [
                'value' => 'Matrix',
                'label' => 'Matrices',
                'count' => $matrixCount
            ];
        }
        
        // Memos (Special Memo + Non-Travel Memo + Single Memo)
        $memoCount = (count($pendingApprovals['Special Memo'] ?? []) + 
                     count($pendingApprovals['Non-Travel Memo'] ?? []) + 
                     count($pendingApprovals['Single Memo'] ?? []));
        if ($memoCount > 0) {
            $categories[] = [
                'value' => 'memos',
                'label' => 'Memos',
                'count' => $memoCount
            ];
        }
        
        // Requests (Service Request + ARF + Change Request)
        $requestCount = (count($pendingApprovals['Service Request'] ?? []) + 
                       count($pendingApprovals['ARF'] ?? []) +
                       count($pendingApprovals['Change Request'] ?? []));
        if ($requestCount > 0) {
            $categories[] = [
                'value' => 'requests',
                'label' => 'Requests',
                'count' => $requestCount
            ];
        }
        
        // Change Requests (standalone category)
        $changeRequestCount = count($pendingApprovals['Change Request'] ?? []);
        if ($changeRequestCount > 0) {
            $categories[] = [
                'value' => 'Change Request',
                'label' => 'Change Requests',
                'count' => $changeRequestCount
            ];
        }
        
        return $categories;
    }

    /**
     * Filter pending approvals by grouped category
     */
    private function filterByGroupedCategory(array $pendingApprovals, string $category): array
    {
        switch ($category) {
            case 'Matrix':
                return ['Matrix' => $pendingApprovals['Matrix'] ?? []];
                
            case 'memos':
                return [
                    'Special Memo' => $pendingApprovals['Special Memo'] ?? [],
                    'Non-Travel Memo' => $pendingApprovals['Non-Travel Memo'] ?? [],
                    'Single Memo' => $pendingApprovals['Single Memo'] ?? []
                ];
                
            case 'requests':
                return [
                    'Service Request' => $pendingApprovals['Service Request'] ?? [],
                    'ARF' => $pendingApprovals['ARF'] ?? [],
                    'Change Request' => $pendingApprovals['Change Request'] ?? []
                ];
                
            default:
                return [$category => $pendingApprovals[$category] ?? []];
        }
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
