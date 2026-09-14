<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class AuditLogsController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Display the audit logs index page.
     */
    public function index(Request $request): RedirectResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($request->has('export')) {
            return $this->exportRequest($request);
        }

        return redirect()->route('system-configs.index', array_merge(
            ['tab' => 'audit-logs'],
            $request->except('tab')
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function getIndexData(Request $request): array
    {
        $stats = $this->auditLogService->getStats($request);

        return [
            'actions' => $this->auditLogService->getDistinctActions(),
            'tables' => collect($this->auditLogService->getAuditTables()),
            'stats' => $stats,
        ];
    }

    public function data(Request $request): JsonResponse
    {
        if (! in_array(89, user_session('permissions', []))) {
            abort(403, 'Unauthorized access to audit logs');
        }

        return response()->json($this->auditLogService->paginateForDataTable($request));
    }

    public function exportRequest(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $auditLogs = $this->auditLogService->exportRows($request);

        return $this->exportAuditLogs($auditLogs);
    }

    /**
     * Export audit logs to CSV
     */
    private function exportAuditLogs($auditLogs)
    {
        $filename = 'audit_logs_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($auditLogs) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'ID', 'Action', 'Entity ID', 'Table', 'Causer Name', 'Causer Email', 
                'Job Title', 'Division & Duty Station', 'Source', 'Suspicious', 'Suspicious Reasons', 'Created At'
            ]);

            // CSV data
            foreach ($auditLogs as $log) {
                $divisionDutyStation = '';
                if ($log->causer_id) {
                    $division = $log->causer_division_name ?? 'N/A';
                    $dutyStation = $log->causer_duty_station_name ?? 'N/A';
                    $divisionDutyStation = $division . ' | ' . $dutyStation;
                } else {
                    $divisionDutyStation = 'N/A';
                }
                
                fputcsv($file, [
                    $log->id,
                    $log->action,
                    $log->entity_id ?? 'N/A',
                    $log->source_table,
                    $log->causer_name ?? 'Unknown User',
                    $log->causer_email ?? 'N/A',
                    $log->causer_job_title ?? 'N/A',
                    $divisionDutyStation,
                    $log->source ?? 'Unknown',
                    $log->is_suspicious ? 'Yes' : 'No',
                    $log->suspicious_reasons ?? '',
                    $log->created_at
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Show cleanup confirmation modal
     */
    public function showCleanupModal()
    {
        // Get audit log statistics for the modal
        $auditTables = $this->auditLogService->getAuditTables();
        $totalLogs = 0;
        $oldLogs = 0;
        
        foreach ($auditTables as $table) {
            try {
                $tableTotal = DB::table($table)->count();
                $tableOld = DB::table($table)
                    ->where('created_at', '<', Carbon::now()->subDays(env('LOGS_RETENTION_PERIOD', 365)))
                    ->count();
                
                $totalLogs += $tableTotal;
                $oldLogs += $tableOld;
            } catch (\Exception $e) {
                continue;
            }
        }

        return response()->json([
            'total_logs' => $totalLogs,
            'old_logs' => $oldLogs,
            'retention_days' => env('LOGS_RETENTION_PERIOD', 365)
        ]);
    }
    
    /**
     * Perform audit logs cleanup
     */
    public function cleanup(Request $request)
    {
        try {
            $retentionDays = $request->input('retention_days', env('LOGS_RETENTION_PERIOD', 365));
            $cutoffDate = Carbon::now()->subDays($retentionDays);
            
            $auditTables = $this->auditLogService->getAuditTables();
            $deletedCount = 0;
            
            foreach ($auditTables as $table) {
                try {
                    $deleted = DB::table($table)
                        ->where('created_at', '<', $cutoffDate)
                        ->delete();
                    $deletedCount += $deleted;
                } catch (\Exception $e) {
                    Log::error("Error cleaning up audit table {$table}: " . $e->getMessage());
                    continue;
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "Successfully cleaned up {$deletedCount} old audit log entries.",
                'deleted_count' => $deletedCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Audit logs cleanup error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during cleanup: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Show reversal confirmation modal
     */
    public function showReversalModal(Request $request)
    {
        $logId = $request->input('log_id');
        $table = $request->input('table');
        
        if (!$logId || !$table) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid log ID or table'
            ], 400);
        }
        
        try {
            $log = DB::table($table)->where('id', $logId)->first();
            
            if (!$log) {
                return response()->json([
                    'success' => false,
                    'message' => 'Audit log not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'log' => $log
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error loading reversal modal: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error loading audit log details'
            ], 500);
        }
    }
    
    /**
     * Perform audit log reversal
     */
    public function reverse(Request $request)
    {
        // Check if user has permission 91
        if (!in_array(91, user_session('permissions', []))) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to reverse audit logs'
            ], 403);
        }
        
        $request->validate([
            'log_id' => 'required|integer',
            'table' => 'required|string',
            'model_table' => 'required|string',
            'action_type' => 'required|string|in:restore,delete',
            'reason' => 'required|string|min:10|max:500'
        ]);
        
        try {
            $logId = $request->input('log_id');
            $table = $request->input('table');
            $modelTable = $request->input('model_table'); // Use the editable model table name
            $actionType = $request->input('action_type'); // 'restore' or 'delete'
            $reason = $request->input('reason');
            
            // Get the original log
            $log = DB::table($table)->where('id', $logId)->first();
            
            if (!$log) {
                return response()->json([
                    'success' => false,
                    'message' => 'Audit log not found'
                ], 404);
            }
            
            // Check if log can be reversed (only certain actions)
            $reversibleActions = ['created', 'updated', 'deleted'];
            if (!in_array($log->action, $reversibleActions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This action cannot be reversed'
                ], 400);
            }
            
            // Create reversal log entry based on table structure
            $reversalData = [
                'action' => 'reversed',
                'old_values' => json_encode(['original_log_id' => $logId, 'original_action' => $log->action, 'selected_action_type' => $actionType]),
                'new_values' => json_encode(['reversal_reason' => $reason]),
                'causer_type' => 'App\\Models\\Staff',
                'causer_id' => user_session('staff_id'),
                'metadata' => json_encode([
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'reversal_timestamp' => now()->toISOString(),
                    'original_log_created_at' => $log->created_at,
                    'selected_action_type' => $actionType
                ]),
                'created_at' => now(),
                'source' => 'reversal'
            ];
            
            // Handle different table structures
            if ($table === 'audit_logs') {
                // For audit_logs table (different structure)
                $reversalData['user_id'] = user_session('staff_id');
                $reversalData['user_name'] = user_session('fname') . ' ' . user_session('lname');
                $reversalData['user_email'] = user_session('work_email') ?? user_session('personal_email');
                $reversalData['resource_type'] = $log->resource_type ?? 'Unknown';
                $reversalData['resource_id'] = $log->resource_id ?? $log->entity_id;
                $reversalData['route_name'] = 'audit-logs.reverse';
                $reversalData['url'] = $request->url();
                $reversalData['method'] = 'POST';
                $reversalData['ip_address'] = $request->ip();
                $reversalData['user_agent'] = $request->userAgent();
                $reversalData['description'] = "Reversed audit log action: {$log->action} (User selected: {$actionType})";
            } else {
                // For audit_funders_logs, audit_users_logs, etc. (standard structure)
                $reversalData['entity_id'] = $log->entity_id ?? $log->resource_id ?? null;
            }
            
            // Add the table name to metadata for tracking
            $reversalData['metadata'] = json_encode(array_merge(
                json_decode($reversalData['metadata'], true),
                ['reversed_table' => $table, 'model_table' => $modelTable]
            ));
            
            // Perform actual data reversal based on the selected action type
            $reversalResult = $this->performDataReversal($log, $modelTable, $reason, $actionType);
            
            if (!$reversalResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $reversalResult['message']
                ], 400);
            }
            
            // Insert reversal log
            $reversalLogId = DB::table($table)->insertGetId($reversalData);
            
            // Log the reversal action
            Log::info('Audit log reversal performed', [
                'original_log_id' => $logId,
                'reversal_log_id' => $reversalLogId,
                'table' => $table,
                'model_table' => $modelTable,
                'action' => $log->action,
                'entity_id' => $log->entity_id,
                'reason' => $reason,
                'user_id' => user_session('staff_id'),
                'data_reversal' => $reversalResult['data_reversal']
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Audit log and data have been successfully reversed',
                'reversal_log_id' => $reversalLogId,
                'data_reversal' => $reversalResult['data_reversal']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Audit log reversal error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during reversal: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Prepare decoded audit values for DB insert/update: encode arrays and objects to JSON,
     * and normalize ISO date/datetime strings to MySQL format (Y-m-d or Y-m-d H:i:s).
     */
    private function prepareValuesForDb(array $values): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $out[$key] = json_encode($value);
            } elseif (is_string($value) && $this->looksLikeIsoDate($value)) {
                try {
                    $parsed = Carbon::parse($value);
                    $out[$key] = $parsed->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $out[$key] = $value;
                }
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    /**
     * Keep only real table columns and normalize values that audit logs often store incorrectly
     * (e.g. Activity {@see Activity::getStatusAttribute()} display values like "Passed").
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sanitizeRestoreRowForTable(string $table, array $values): array
    {
        if (! Schema::hasTable($table)) {
            return $values;
        }

        $allowed = array_flip(Schema::getColumnListing($table));
        $values = array_intersect_key($values, $allowed);

        if ($table === 'activities' && array_key_exists('status', $values)) {
            $values['status'] = $this->normalizeActivityDbStatus(
                $values['status'],
                $values['overall_status'] ?? null
            );
        }

        return $values;
    }

    /**
     * Map audit / UI status labels to activities.status enum (draft, submitted, approved, rejected).
     */
    private function normalizeActivityDbStatus(mixed $status, mixed $overallStatus = null): string
    {
        $valid = ['draft', 'submitted', 'approved', 'rejected'];
        $normalized = strtolower(trim((string) $status));

        if (in_array($normalized, $valid, true)) {
            return $normalized;
        }

        $overall = strtolower(trim((string) ($overallStatus ?? '')));
        $overallMap = [
            'draft' => 'draft',
            'approved' => 'approved',
            'returned' => 'submitted',
            'pending' => 'submitted',
            'rejected' => 'rejected',
        ];
        if (isset($overallMap[$overall])) {
            return $overallMap[$overall];
        }

        $displayMap = [
            'passed' => 'approved',
            'pending' => 'submitted',
            'returned' => 'submitted',
            'draft' => 'draft',
        ];

        return $displayMap[$normalized] ?? 'submitted';
    }

    /**
     * Whether a string looks like an ISO 8601 or common date/datetime value (MySQL rejects these raw).
     */
    private function looksLikeIsoDate(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}(T|\s|$)/', $value);
    }

    /**
     * Perform actual data reversal based on the selected action type
     */
    private function performDataReversal($log, $modelTable, $reason, $actionType)
    {
        try {
            $entityId = $log->entity_id ?? $log->resource_id ?? null;
            
            if (!$entityId) {
                return [
                    'success' => false,
                    'message' => 'Cannot perform action: No entity ID found'
                ];
            }
            
            $oldValues = json_decode($log->old_values ?? '{}', true);
            $newValues = json_decode($log->new_values ?? '{}', true);
            
            // Perform action based on user's selection
            if ($actionType === 'delete') {
                // Delete the record
                $deleted = DB::table($modelTable)->where('id', $entityId)->delete();
                if ($deleted) {
                    return [
                        'success' => true,
                        'data_reversal' => "Deleted record with ID: {$entityId}",
                        'message' => 'Record deleted successfully'
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Record not found or already deleted'
                    ];
                }
            } elseif ($actionType === 'restore') {
                // Determine which values to use based on the original log action
                // For 'created' actions, use new_values (the data that was created)
                // For 'deleted' or 'updated' actions, use old_values (the data to restore)
                $valuesToUse = [];
                if ($log->action === 'created') {
                    // For created actions, use new_values to restore what was created
                    if (empty($newValues)) {
                        return [
                            'success' => false,
                            'message' => 'Cannot restore: No new values found (record was created but no data available)'
                        ];
                    }
                    $valuesToUse = $newValues;
                } else {
                    // For deleted or updated actions, use old_values to restore previous state
                    if (empty($oldValues)) {
                        return [
                            'success' => false,
                            'message' => 'Cannot restore: No old values found to restore'
                        ];
                    }
                    $valuesToUse = $oldValues;
                }
                
                // Check if record exists (for updated actions, restore old values; for deleted/created actions, re-insert)
                $existingRecord = DB::table($modelTable)->where('id', $entityId)->first();
                
                if ($existingRecord && $log->action === 'updated') {
                    // Record exists and was updated, restore old values
                    // Remove audit-specific fields from values
                    $cleanValues = array_diff_key($valuesToUse, [
                        'created_at' => '',
                        'updated_at' => '',
                        'id' => ''
                    ]);
                    $cleanValues = $this->sanitizeRestoreRowForTable(
                        $modelTable,
                        $this->prepareValuesForDb($cleanValues)
                    );

                    $updated = DB::table($modelTable)
                        ->where('id', $entityId)
                        ->update($cleanValues);
                    
                    if ($updated !== false) {
                        return [
                            'success' => true,
                            'data_reversal' => "Restored old values for record ID: {$entityId}",
                            'message' => 'Record restored to previous state'
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => 'Failed to restore record'
                        ];
                    }
                } else {
                    // Record doesn't exist (deleted) or was created, re-insert it
                    // Remove audit-specific fields (don't add reversal metadata to primary table)
                    $restoreData = array_diff_key($valuesToUse, [
                        'created_at' => '',
                        'updated_at' => '',
                        'id' => ''
                    ]);
                    
                    // Convert date fields to proper format
                    if (isset($restoreData['date_from']) && is_string($restoreData['date_from'])) {
                        $restoreData['date_from'] = \Carbon\Carbon::parse($restoreData['date_from'])->format('Y-m-d');
                    }
                    if (isset($restoreData['date_to']) && is_string($restoreData['date_to'])) {
                        $restoreData['date_to'] = \Carbon\Carbon::parse($restoreData['date_to'])->format('Y-m-d');
                    }
                    
                    $restoreData = $this->sanitizeRestoreRowForTable(
                        $modelTable,
                        $this->prepareValuesForDb($restoreData)
                    );
                    $restoreData['updated_at'] = now()->format('Y-m-d H:i:s');
                    $restoreData['created_at'] = isset($valuesToUse['created_at']) && is_string($valuesToUse['created_at'])
                        ? Carbon::parse($valuesToUse['created_at'])->format('Y-m-d H:i:s')
                        : now()->format('Y-m-d H:i:s');
                    
                    $restoredId = DB::table($modelTable)->insertGetId($restoreData);
                    
                    if ($restoredId) {
                        $actionDescription = $log->action === 'created' ? 'restored created record' : 'restored deleted record';
                        return [
                            'success' => true,
                            'data_reversal' => "Restored record with new ID: {$restoredId} ({$actionDescription})",
                            'message' => 'Record restored successfully'
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => 'Failed to restore record'
                        ];
                    }
                }
            } else {
                return [
                    'success' => false,
                    'message' => "Invalid action type: {$actionType}"
                ];
            }
            
        } catch (\Exception $e) {
            Log::error('Data reversal error: ' . $e->getMessage(), [
                'log_id' => $log->id,
                'model_table' => $modelTable,
                'entity_id' => $entityId ?? 'unknown'
            ]);
            
            return [
                'success' => false,
                'message' => 'Error during data reversal: ' . $e->getMessage()
            ];
        }
    }
}
