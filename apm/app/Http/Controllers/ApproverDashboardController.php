<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
            // Validate request parameters (allow empty values). When exporting, allow higher per_page.
            $isExport = $request->boolean('export');
            $request->validate([
                'page' => 'nullable|integer|min:1',
                'per_page' => [
                    'nullable',
                    'integer',
                    'min:1',
                    $isExport ? 'max:10000' : 'max:100',
                ],
                'q' => 'nullable|string|max:255',
                'division_id' => 'nullable|integer|exists:divisions,id',
                'doc_type' => 'nullable|string|in:matrix,non_travel,single_memos,special,memos,arf,requests_for_service,change_requests',
                'workflow_definition_id' => 'nullable|integer|exists:workflows,id',
                'approval_level' => 'nullable|integer|min:1',
                'month' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer|min:2000|max:2100',
                'export' => 'nullable|boolean',
                'format' => 'nullable|string|in:pdf,csv',
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
            $month = $request->get('month') ?: null;
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
            $allApproversWithCounts = $this->getPendingCountsForApprovers($approversCollection, $workflowDefinitionId, $docType, $divisionId, $year, $month);

            // Handle sorting - check for DataTables order parameter or use default
            $orderColumn = 7; // Default: Avg. Time (column index 7)
            $orderDirection = 'desc'; // Default: descending (highest days first)
            
            $orderFirst = null;
            if ($request->has('order')) {
                $orderParam = $request->get('order');
                if (is_array($orderParam) && !empty($orderParam)) {
                    $orderFirst = $orderParam[0];
                } elseif (is_string($orderParam)) {
                    $orderData = json_decode($orderParam, true);
                    if (is_array($orderData) && !empty($orderData)) {
                        $orderFirst = $orderData[0];
                    }
                }
            }
            if ($orderFirst !== null) {
                // Support both [{column: 7, dir: "desc"}] and [[7, "desc"]] formats
                if (isset($orderFirst['column'])) {
                    $orderColumn = (int) $orderFirst['column'];
                    $orderDirection = (isset($orderFirst['dir']) && $orderFirst['dir'] === 'asc') ? 'asc' : 'desc';
                } elseif (isset($orderFirst[0], $orderFirst[1])) {
                    $orderColumn = (int) $orderFirst[0];
                    $orderDirection = (strtolower((string) $orderFirst[1]) === 'asc') ? 'asc' : 'desc';
                }
            }
            
            // Map column index to sort field (0=#, 1=Approver, 2=Last approval date, 3=Role, 4=Pending, 5=Total Pending, 6=Total Handled, 7=Avg. Time)
            $sortFields = [
                1 => 'approver_name',
                2 => 'last_approval_date',
                3 => 'roles',
                5 => 'total_pending',
                6 => 'total_handled',
                7 => 'avg_approval_time_hours'
            ];
            
            $sortField = $sortFields[$orderColumn] ?? 'avg_approval_time_hours';
            
            // Sort approvers based on selected column
            usort($allApproversWithCounts, function($a, $b) use ($sortField, $orderDirection) {
                $aValue = $a[$sortField] ?? 0;
                $bValue = $b[$sortField] ?? 0;
                // For last_approval_date use empty string for null so datetime comparison works and nulls sort last when desc
                if ($sortField === 'last_approval_date') {
                    $aValue = $aValue ?: '';
                    $bValue = $bValue ?: '';
                }
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

            // Handle export (PDF by default, CSV when format=csv)
            if ($request->get('export')) {
                $format = $request->get('format', 'pdf');
                if ($format === 'csv') {
                    return $this->exportToCsv($allApproversWithCounts);
                }
                return $this->exportToPdf($request, $allApproversWithCounts);
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
                    'month' => $month,
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
     * Get workflow stats (average approval time by workflow) respecting current filters.
     */
    public function getWorkflowStats(Request $request): JsonResponse
    {
        try {
            $userSession = user_session();
            $userDivisionId = $userSession['division_id'] ?? null;
            $userPermissions = $userSession['permissions'] ?? [];
            $hasPermission88 = in_array(88, $userPermissions);

            $divisionId = $request->get('division_id') ? (int) $request->get('division_id') : null;
            $docType = $request->get('doc_type') ?: null;
            $month = $request->get('month') ? (int) $request->get('month') : null;
            $year = $request->get('year') ? (int) $request->get('year') : null;

            if (!$hasPermission88 && $userDivisionId && $userDivisionId > 0) {
                $divisionId = $userDivisionId;
            }

            $workflowStats = $this->getAverageApprovalTimeByWorkflowFiltered($divisionId, $docType, $year, $month);

            return response()->json([
                'success' => true,
                'data' => $workflowStats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving workflow stats: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Export dashboard data to PDF using mPDF.
     */
    private function exportToPdf(Request $request, array $data)
    {
        $approvers = array_map(function ($row, $index) {
            $pendingItems = [];
            $counts = $row['pending_counts'] ?? [];
            if (($counts['matrix'] ?? 0) > 0) {
                $pendingItems[] = 'Matrix: ' . $counts['matrix'];
            }
            if (($counts['non_travel'] ?? 0) > 0) {
                $pendingItems[] = 'Non-Travel: ' . $counts['non_travel'];
            }
            if (($counts['single_memos'] ?? 0) > 0) {
                $pendingItems[] = 'Single: ' . $counts['single_memos'];
            }
            if (($counts['special'] ?? 0) > 0) {
                $pendingItems[] = 'Special: ' . $counts['special'];
            }
            if (($counts['arf'] ?? 0) > 0) {
                $pendingItems[] = 'ARF: ' . $counts['arf'];
            }
            if (($counts['requests_for_service'] ?? 0) > 0) {
                $pendingItems[] = 'Requests: ' . $counts['requests_for_service'];
            }
            if (($counts['change_requests'] ?? 0) > 0) {
                $pendingItems[] = 'Change: ' . ($counts['change_requests'] ?? 0);
            }
            $row['pending_items_display'] = !empty($pendingItems) ? implode(', ', $pendingItems) : '—';
            return $row;
        }, $data, array_keys($data));

        $filters = [];
        if ($request->filled('division_id')) {
            $div = \Illuminate\Support\Facades\DB::table('divisions')->where('id', $request->get('division_id'))->value('division_name');
            $filters[] = 'Division: ' . ($div ?: $request->get('division_id'));
        }
        if ($request->filled('doc_type')) {
            $filters[] = 'Doc type: ' . $request->get('doc_type');
        }
        if ($request->filled('approval_level')) {
            $filters[] = 'Level: ' . $request->get('approval_level');
        }
        if ($request->filled('year')) {
            $filters[] = 'Year: ' . $request->get('year');
        }
        if ($request->filled('month')) {
            $filters[] = 'Month: ' . $request->get('month');
        }
        if ($request->filled('q')) {
            $filters[] = 'Search: ' . $request->get('q');
        }
        $filtersSummary = empty($filters) ? 'None' : implode('; ', $filters);

        $totalPending = array_sum(array_column($data, 'total_pending'));
        $summary = count($data) . ' approver(s), ' . $totalPending . ' total pending item(s).';

        $htmlData = [
            'approvers' => $approvers,
            'filters_summary' => $filtersSummary,
            'summary' => $summary,
        ];

        $mpdf = generate_pdf('approver-dashboard.export-pdf', $htmlData);
        $filename = 'approver_report_' . date('Y-m-d_H-i-s') . '.pdf';
        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export dashboard data to CSV format.
     * Exports all approvers with their pending counts, total handled, and average approval time.
     */
    private function exportToCsv($data)
    {
        $filename = 'approver_report_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for UTF-8 to ensure proper encoding in Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Add CSV headers (Pending Items column removed)
            fputcsv($file, [
                '#',
                'Approver Name',
                'Last Approval Date',
                'Email',
                'Division',
                'Roles',
                'Levels',
                'Total Pending',
                'Total Handled',
                'Avg Approval Time (Hours)',
                'Avg Approval Time (Display)'
            ]);

            // Add data rows
            foreach ($data as $index => $approver) {
                fputcsv($file, [
                    $index + 1,
                    $approver['approver_name'] ?? '',
                    $approver['last_approval_date_display'] ?? '',
                    $approver['approver_email'] ?? '',
                    $approver['division_name'] ?? 'N/A',
                    is_array($approver['roles'] ?? null) ? implode('; ', $approver['roles']) : ($approver['role'] ?? ''),
                    is_array($approver['levels'] ?? null) ? implode(', ', $approver['levels']) : ($approver['level_no'] ?? ''),
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
