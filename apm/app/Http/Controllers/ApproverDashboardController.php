<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Workflow;
use Carbon\Carbon;

class ApproverDashboardController extends Controller
{
    use ApproverDashboardHelper;
    /**
     * Display the approver dashboard page.
     */
    public function index(): View
    {
        $userSession = user_session();
        $userDivisionId = $userSession['division_id'] ?? null;
        $userPermissions = $userSession['permissions'] ?? [];
        $hasPermission88 = in_array(88, $userPermissions);
        
        return view('approver-dashboard.index', compact('userDivisionId', 'hasPermission88'));
    }

    /**
     * Get approver dashboard data via API.
     */
    public function getDashboardData(Request $request)
    {
        try {
            // Validate request parameters (allow empty values)
            $request->validate([
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
                'q' => 'nullable|string|max:255',
                'division_id' => 'nullable|integer|exists:divisions,id',
                'doc_type' => 'nullable|string|in:matrix,non_travel,single_memos,special,memos,arf,requests_for_service,change_requests',
                'workflow_definition_id' => 'nullable|integer|exists:workflows,id',
                'approval_level' => 'nullable|integer|min:1',
                'export' => 'nullable|boolean',
            ]);

            $userSession = user_session();
            $userDivisionId = $userSession['division_id'] ?? null;
            $userPermissions = $userSession['permissions'] ?? [];
            $hasPermission88 = in_array(88, $userPermissions);

            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 25);
            $search = $request->get('q') ?: null;
            $divisionId = $request->get('division_id') ?: null;
            $docType = $request->get('doc_type') ?: null;
            $workflowDefinitionId = $request->get('workflow_definition_id') ?: null;
            $approvalLevel = $request->get('approval_level') ?: null;

            // If user doesn't have permission 88, restrict to their division
            // Only apply restriction if we have valid session data
            if (!$hasPermission88 && $userDivisionId && $userDivisionId > 0) {
                $divisionId = $userDivisionId;
            }

            // Get active workflow if not specified
            if (!$workflowDefinitionId) {
                $activeWorkflow = Workflow::where('is_active', 1)->first();
                $workflowDefinitionId = $activeWorkflow ? $activeWorkflow->id : null;
            }

            if (!$workflowDefinitionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active workflow found',
                    'data' => []
                ]);
            }

            // Build the query for approvers with pending counts
            $approversCollection = $this->buildApproverQuery($workflowDefinitionId, $search, $divisionId, $docType, $approvalLevel);

            // Get total count for pagination (before getting counts)
            $totalCount = $approversCollection->count();

            // Get pending counts for ALL approvers first (before pagination)
            $allApproversWithCounts = $this->getPendingCountsForApprovers($approversCollection, $workflowDefinitionId, $docType, $divisionId);

            // Sort approvers by total_pending (descending) - biggest number first
            usort($allApproversWithCounts, function($a, $b) {
                return $b['total_pending'] <=> $a['total_pending'];
            });

            // Apply pagination after sorting
            $approversWithCounts = array_slice($allApproversWithCounts, ($page - 1) * $perPage, $perPage);

            // Handle Excel export
            if ($request->get('export')) {
                return $this->exportToExcel($approversWithCounts);
            }

            // Get total number of workflows for display
            $totalWorkflows = Workflow::count();

            return response()->json([
                'success' => true,
                'data' => $approversWithCounts,
                'total_workflows' => $totalWorkflows,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalCount,
                    'last_page' => ceil($totalCount / $perPage),
                    'from' => (($page - 1) * $perPage) + 1,
                    'to' => min($page * $perPage, $totalCount),
                ],
                'filters' => [
                    'workflow_definition_id' => $workflowDefinitionId,
                    'search' => $search,
                    'division_id' => $divisionId,
                    'doc_type' => $docType,
                    'approval_level' => $approvalLevel,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard data: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get filter options for the dashboard.
     */
    public function getFilterOptions(): JsonResponse
    {
        try {
            $userSession = user_session();
            $userDivisionId = $userSession['division_id'] ?? null;
            $userPermissions = $userSession['permissions'] ?? [];
            $hasPermission88 = in_array(88, $userPermissions);

            // Get divisions - restrict to user's division if no permission 88
            $divisionsQuery = DB::table('divisions')->select('id', 'division_name');
            // Only apply restriction if we have valid session data
            if (!$hasPermission88 && $userDivisionId && $userDivisionId > 0) {
                $divisionsQuery->where('id', $userDivisionId);
            }
            $divisions = $divisionsQuery->orderBy('division_name')->get();

            $workflowDefinitions = DB::table('workflows')
                ->select('id', 'workflow_name as name', 'is_active')
                ->orderBy('workflow_name')
                ->get();

            // Get active workflow
            $activeWorkflow = Workflow::where('is_active', 1)->first();
            
            // Get approval levels from workflow_definition with role names
            $approvalLevels = DB::table('workflow_definition')
                ->select('approval_order', 'role')
                ->where('workflow_id', $activeWorkflow ? $activeWorkflow->id : 1)
                ->where('is_enabled', 1)
                ->orderBy('approval_order')
                ->get()
                ->map(function($item) {
                    return ['value' => $item->approval_order, 'label' => $item->role . ' (Level ' . $item->approval_order . ')'];
                });

            $documentTypes = [
                ['value' => 'matrix', 'label' => 'Matrix'],
                ['value' => 'non_travel', 'label' => 'Non-Travel Memos'],
                ['value' => 'single_memos', 'label' => 'Single Memos'],
                ['value' => 'special', 'label' => 'Special Memos'],
                ['value' => 'memos', 'label' => 'Memos'],
                ['value' => 'arf', 'label' => 'ARF Requests'],
                ['value' => 'requests_for_service', 'label' => 'Requests for Service'],
                ['value' => 'change_requests', 'label' => 'Change Requests'],
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'divisions' => $divisions,
                    'workflow_definitions' => $workflowDefinitions,
                    'document_types' => $documentTypes,
                    'approval_levels' => $approvalLevels,
                    'user_division_id' => $userDivisionId,
                    'has_permission_88' => $hasPermission88,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving filter options: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get summary statistics for the dashboard.
     */
    public function getSummaryStats(Request $request): JsonResponse
    {
        try {
            $workflowDefinitionId = $request->get('workflow_definition_id');
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');

            // Get active workflow if not specified
            if (!$workflowDefinitionId) {
                $activeWorkflow = Workflow::where('is_active', 1)->first();
                $workflowDefinitionId = $activeWorkflow ? $activeWorkflow->id : null;
            }

            if (!$workflowDefinitionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active workflow found',
                    'data' => []
                ]);
            }

            $stats = [
                'total_approvers' => 0,
                'total_pending_documents' => 0,
                'pending_by_type' => [],
                'pending_by_level' => [],
            ];

            // Get total approvers
            $totalApprovers = DB::table('workflow_definition')
                ->where('workflow_id', $workflowDefinitionId)
                ->where('is_enabled', 1)
                ->distinct('role')
                ->count('role');
            $stats['total_approvers'] = $totalApprovers;

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving summary statistics: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Export dashboard data to Excel format.
     */
    private function exportToExcel($data)
    {
        $filename = 'approver_dashboard_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($file, [
                'Approver Name',
                'Email',
                'Role',
                'Level',
                'Division',
                'Matrix Pending',
                'Non-Travel Pending',
                'Single Memos Pending',
                'Special Pending',
                'ARF Pending',
                'Requests Pending',
                'Change Requests Pending',
                'Total Pending',
                'Total Handled',
                'Avg Approval Time'
            ]);

            // Add data rows
            foreach ($data as $approver) {
                fputcsv($file, [
                    $approver['approver_name'],
                    $approver['approver_email'],
                    $approver['role'],
                    $approver['level_no'],
                    $approver['division_name'],
                    $approver['pending_counts']['matrix'],
                    $approver['pending_counts']['non_travel'],
                    $approver['pending_counts']['single_memos'],
                    $approver['pending_counts']['special'],
                    $approver['pending_counts']['arf'],
                    $approver['pending_counts']['requests_for_service'],
                    $approver['pending_counts']['change_requests'] ?? 0,
                    $approver['total_pending'],
                    $approver['total_handled'] ?? 0,
                    $approver['avg_approval_time_display']
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
