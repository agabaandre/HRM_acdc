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
                'year' => 'nullable|integer|min:2000|max:2100',
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
            $year = $request->get('year') ?: null;

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

            // Get pending counts for ALL approvers first (before pagination)
            // This will combine approvers by staff_id
            $allApproversWithCounts = $this->getPendingCountsForApprovers($approversCollection, $workflowDefinitionId, $docType, $divisionId, $year);

            // Handle sorting - check for DataTables order parameter or use default
            $orderColumn = 4; // Default: Total Pending (column index 4)
            $orderDirection = 'desc'; // Default: descending
            
            // Check if this is a DataTables request with order parameter
            if ($request->has('order') && is_array($request->get('order')) && !empty($request->get('order'))) {
                $order = $request->get('order')[0];
                $orderColumn = (int) $order['column'];
                $orderDirection = $order['dir'] === 'asc' ? 'asc' : 'desc';
            }
            
            // Map column index to sort field
            $sortFields = [
                1 => 'approver_name',
                2 => 'roles',
                4 => 'total_pending',
                5 => 'total_handled',
                6 => 'avg_approval_time_hours'
            ];
            
            $sortField = $sortFields[$orderColumn] ?? 'total_pending';
            
            // Sort approvers based on selected column
            usort($allApproversWithCounts, function($a, $b) use ($sortField, $orderDirection) {
                $aValue = $a[$sortField] ?? 0;
                $bValue = $b[$sortField] ?? 0;
                
                // Handle array fields (like roles)
                if (is_array($aValue)) {
                    $aValue = implode(', ', $aValue);
                }
                if (is_array($bValue)) {
                    $bValue = implode(', ', $bValue);
                }
                
                // Handle string comparison
                if (is_string($aValue) && is_string($bValue)) {
                    $result = strcasecmp($aValue, $bValue);
                } else {
                    // Numeric comparison
                    $result = ($aValue <=> $bValue);
                }
                
                return $orderDirection === 'asc' ? $result : -$result;
            });

            // Get total count AFTER combining by staff_id (for correct pagination)
            $totalCount = count($allApproversWithCounts);

            // Apply pagination after sorting
            $approversWithCounts = array_slice($allApproversWithCounts, ($page - 1) * $perPage, $perPage);

            // Handle Excel export (use all combined approvers, not paginated)
            if ($request->get('export')) {
                return $this->exportToExcel($allApproversWithCounts);
            }

            // Get total number of workflows for display
            $totalWorkflows = Workflow::count();

            // Return response compatible with both DataTables and original format
            return response()->json([
                'success' => true,
                'data' => $approversWithCounts,
                'recordsTotal' => $totalCount,
                'recordsFiltered' => $totalCount,
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
                    'year' => $year,
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

            // Get distinct years from matrices table
            $years = DB::table('matrices')
                ->select(DB::raw('DISTINCT year'))
                ->whereNotNull('year')
                ->orderBy('year', 'desc')
                ->pluck('year')
                ->toArray();

            // If no years found, generate default years
            if (empty($years)) {
                $currentYear = Carbon::now()->year;
                $years = range($currentYear - 5, $currentYear + 2);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'divisions' => $divisions,
                    'workflow_definitions' => $workflowDefinitions,
                    'document_types' => $documentTypes,
                    'approval_levels' => $approvalLevels,
                    'years' => $years,
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
     * Exports all approvers with their pending counts, total handled, and average approval time.
     * Matches the new table structure with row numbers and combined pending items.
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
            
            // Add BOM for UTF-8 to ensure proper encoding in Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Add CSV headers matching the new table structure
            fputcsv($file, [
                '#',
                'Approver Name',
                'Email',
                'Division',
                'Roles',
                'Levels',
                'Pending Items',
                'Total Pending',
                'Total Handled',
                'Avg Approval Time (Hours)',
                'Avg Approval Time (Display)'
            ]);

            // Add data rows
            foreach ($data as $index => $approver) {
                // Build pending items summary (combined format like in the table)
                $pendingItems = [];
                if (($approver['pending_counts']['matrix'] ?? 0) > 0) {
                    $pendingItems[] = 'Matrix: ' . $approver['pending_counts']['matrix'];
                }
                if (($approver['pending_counts']['non_travel'] ?? 0) > 0) {
                    $pendingItems[] = 'Non-Travel: ' . $approver['pending_counts']['non_travel'];
                }
                if (($approver['pending_counts']['single_memos'] ?? 0) > 0) {
                    $pendingItems[] = 'Single: ' . $approver['pending_counts']['single_memos'];
                }
                if (($approver['pending_counts']['special'] ?? 0) > 0) {
                    $pendingItems[] = 'Special: ' . $approver['pending_counts']['special'];
                }
                if (($approver['pending_counts']['arf'] ?? 0) > 0) {
                    $pendingItems[] = 'ARF: ' . $approver['pending_counts']['arf'];
                }
                if (($approver['pending_counts']['requests_for_service'] ?? 0) > 0) {
                    $pendingItems[] = 'Requests: ' . $approver['pending_counts']['requests_for_service'];
                }
                if (($approver['pending_counts']['change_requests'] ?? 0) > 0) {
                    $pendingItems[] = 'Change: ' . ($approver['pending_counts']['change_requests'] ?? 0);
                }
                $pendingItemsCombined = !empty($pendingItems) ? implode(', ', $pendingItems) : 'No pending items';
                
                fputcsv($file, [
                    $index + 1, // Row number
                    $approver['approver_name'] ?? '',
                    $approver['approver_email'] ?? '',
                    $approver['division_name'] ?? 'N/A',
                    is_array($approver['roles'] ?? null) ? implode('; ', $approver['roles']) : ($approver['role'] ?? ''),
                    is_array($approver['levels'] ?? null) ? implode(', ', $approver['levels']) : ($approver['level_no'] ?? ''),
                    $pendingItemsCombined, // Combined pending items
                    $approver['total_pending'] ?? 0,
                    $approver['total_handled'] ?? 0,
                    $approver['avg_approval_time_hours'] ?? 0,
                    $approver['avg_approval_time_display'] ?? 'No data'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
