<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AuditLogsController extends Controller
{
    /**
     * Display the audit logs index page.
     */
    public function index(Request $request)
    {
        // Get all audit tables
        $auditTables = $this->getAuditTables();
        
        // Get audit logs from all tables
        $auditLogs = collect();
        
        foreach ($auditTables as $table) {
            try {
                // Check if table has entity_id or resource_id column
                $columns = DB::select("SHOW COLUMNS FROM {$table}");
                $hasEntityId = collect($columns)->contains('Field', 'entity_id');
                $hasResourceId = collect($columns)->contains('Field', 'resource_id');
                
                if ($hasEntityId) {
                    // Table has entity_id column
                    $tableLogs = DB::table($table)
                        ->select('*')
                        ->addSelect(DB::raw("'{$table}' as source_table"))
                        ->orderBy('created_at', 'desc')
                        ->limit(100)
                        ->get();
                } elseif ($hasResourceId) {
                    // Table has resource_id column, map it to entity_id
                    $tableLogs = DB::table($table)
                        ->select('*')
                        ->addSelect(DB::raw("'{$table}' as source_table"))
                        ->addSelect(DB::raw("CAST(resource_id AS CHAR) as entity_id"))
                        ->orderBy('created_at', 'desc')
                        ->limit(100)
                        ->get();
                } else {
                    // Table has neither, use id as entity_id
                    $tableLogs = DB::table($table)
                        ->select('*')
                        ->addSelect(DB::raw("'{$table}' as source_table"))
                        ->addSelect(DB::raw("CAST(id AS CHAR) as entity_id"))
                        ->orderBy('created_at', 'desc')
                        ->limit(100)
                        ->get();
                }
                    
                $auditLogs = $auditLogs->merge($tableLogs);
            } catch (\Exception $e) {
                // Log error and continue with other tables
                Log::error("Error processing audit table {$table}: " . $e->getMessage());
                continue;
            }
        }
        
        // Sort by created_at desc
        $auditLogs = $auditLogs->sortByDesc('created_at');
        
        // Resolve causer information (staff details)
        $auditLogs = $this->resolveCauserInformation($auditLogs);
        
        // Mark suspicious activities
        $auditLogs = $this->markSuspiciousActivities($auditLogs);
        
        // Apply filters
        if ($request->filled('search')) {
            $search = $request->get('search');
            $auditLogs = $auditLogs->filter(function ($log) use ($search) {
                return str_contains(strtolower($log->action ?? ''), strtolower($search)) ||
                       str_contains(strtolower($log->source_table ?? ''), strtolower($search)) ||
                       str_contains(strtolower($log->entity_id ?? ''), strtolower($search));
            });
        }

        if ($request->filled('action')) {
            $auditLogs = $auditLogs->where('action', $request->get('action'));
        }

        if ($request->filled('date_from')) {
            $dateFrom = Carbon::parse($request->get('date_from'))->startOfDay();
            $auditLogs = $auditLogs->filter(function ($log) use ($dateFrom) {
                return Carbon::parse($log->created_at)->gte($dateFrom);
            });
        }

        if ($request->filled('date_to')) {
            $dateTo = Carbon::parse($request->get('date_to'))->endOfDay();
            $auditLogs = $auditLogs->filter(function ($log) use ($dateTo) {
                return Carbon::parse($log->created_at)->lte($dateTo);
            });
        }
        
        if ($request->filled('table')) {
            $auditLogs = $auditLogs->where('source_table', $request->get('table'));
        }
        
        if ($request->filled('suspicious')) {
            $suspiciousFilter = $request->get('suspicious');
            if ($suspiciousFilter === '1') {
                $auditLogs = $auditLogs->filter(function ($log) {
                    return $log->is_suspicious ?? false;
                });
            } elseif ($suspiciousFilter === '0') {
                $auditLogs = $auditLogs->filter(function ($log) {
                    return !($log->is_suspicious ?? false);
                });
            }
        }
        
        // Get filter options
        $actions = $auditLogs->pluck('action')->unique()->sort()->values();
        $tables = $auditLogs->pluck('source_table')->unique()->sort()->values();
        
        // Get statistics
        $stats = [
            'total_logs' => $auditLogs->count(),
            'actions_count' => $auditLogs->groupBy('action')->map->count()->sortDesc(),
            'tables_count' => $auditLogs->groupBy('source_table')->map->count()->sortDesc(),
            'recent_activity' => $auditLogs->filter(function ($log) {
                return Carbon::parse($log->created_at)->gte(Carbon::now()->subHours(24));
            })->count(),
        ];
        
        // Handle export request
        if ($request->has('export')) {
            return $this->exportAuditLogs($auditLogs);
        }
        
        // Paginate results
        $perPage = 25;
        $currentPage = $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $paginatedLogs = $auditLogs->slice($offset, $perPage)->values();
        
        // Create pagination data
        $total = $auditLogs->count();
        $lastPage = ceil($total / $perPage);
        
        $pagination = [
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total),
        ];
        
        return view('audit-logs.index', compact('paginatedLogs', 'actions', 'tables', 'stats', 'pagination'));
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
        $auditTables = $this->getAuditTables();
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
            
            $auditTables = $this->getAuditTables();
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
            'reason' => 'required|string|min:10|max:500'
        ]);
        
        try {
            $logId = $request->input('log_id');
            $table = $request->input('table');
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
                'old_values' => json_encode(['original_log_id' => $logId, 'original_action' => $log->action]),
                'new_values' => json_encode(['reversal_reason' => $reason]),
                'causer_type' => 'App\\Models\\Staff',
                'causer_id' => user_session('staff_id'),
                'metadata' => json_encode([
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'reversal_timestamp' => now()->toISOString(),
                    'original_log_created_at' => $log->created_at
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
                $reversalData['description'] = "Reversed audit log action: {$log->action}";
            } else {
                // For audit_funders_logs, audit_users_logs, etc. (standard structure)
                $reversalData['entity_id'] = $log->entity_id ?? $log->resource_id ?? null;
            }
            
            // Add the table name to metadata for tracking
            $reversalData['metadata'] = json_encode(array_merge(
                json_decode($reversalData['metadata'], true),
                ['reversed_table' => $table]
            ));
            
            // Get the actual model table name (remove audit_ prefix)
            $modelTable = str_replace('audit_', '', $table);
            $modelTable = str_replace('_logs', '', $modelTable);
            
            // Perform actual data reversal based on the original action
            $reversalResult = $this->performDataReversal($log, $modelTable, $reason);
            
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
     * Perform actual data reversal based on the original audit log action
     */
    private function performDataReversal($log, $modelTable, $reason)
    {
        try {
            $entityId = $log->entity_id ?? $log->resource_id ?? null;
            
            if (!$entityId) {
                return [
                    'success' => false,
                    'message' => 'Cannot reverse: No entity ID found'
                ];
            }
            
            $oldValues = json_decode($log->old_values ?? '{}', true);
            $newValues = json_decode($log->new_values ?? '{}', true);
            
            switch ($log->action) {
                case 'created':
                    // For created actions, delete the record
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
                    
                case 'updated':
                    // For updated actions, restore the old values
                    if (empty($oldValues)) {
                        return [
                            'success' => false,
                            'message' => 'Cannot reverse: No old values found'
                        ];
                    }
                    
                    // Remove audit-specific fields from old values
                    $cleanOldValues = array_diff_key($oldValues, [
                        'created_at' => '',
                        'updated_at' => '',
                        'id' => ''
                    ]);
                    
                    $updated = DB::table($modelTable)
                        ->where('id', $entityId)
                        ->update($cleanOldValues);
                    
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
                    
                case 'deleted':
                    // For deleted actions, restore the record
                    if (empty($oldValues)) {
                        return [
                            'success' => false,
                            'message' => 'Cannot reverse: No old values found to restore'
                        ];
                    }
                    
                    // Remove audit-specific fields (don't add reversal metadata to primary table)
                    $restoreData = array_diff_key($oldValues, [
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
                    
                    $restoreData['updated_at'] = now();
                    
                    $restoredId = DB::table($modelTable)->insertGetId($restoreData);
                    
                    if ($restoredId) {
                        return [
                            'success' => true,
                            'data_reversal' => "Restored deleted record with new ID: {$restoredId}",
                            'message' => 'Record restored successfully'
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => 'Failed to restore deleted record'
                        ];
                    }
                    
                default:
                    return [
                        'success' => false,
                        'message' => "Cannot reverse action: {$log->action}"
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
    
    /**
     * Mark suspicious activities based on external sources and unknown users
     */
    private function markSuspiciousActivities($auditLogs)
    {
        // Group logs by IP address and time window to detect patterns
        $logsByIp = $auditLogs->groupBy(function ($log) {
            // Extract IP from metadata if available
            $metadata = json_decode($log->metadata ?? '{}', true);
            return $metadata['ip'] ?? 'unknown';
        });
        
        // Group logs by causer_id to detect unknown user patterns
        $logsByCauser = $auditLogs->groupBy('causer_id');
        
        return $auditLogs->map(function ($log) use ($logsByIp, $logsByCauser) {
            $isSuspicious = false;
            $suspiciousReasons = [];
            
            // Check for external source
            $metadata = json_decode($log->metadata ?? '{}', true);
            $ip = $metadata['ip'] ?? null;
            $userAgent = $metadata['user_agent'] ?? null;
            
            // Mark as suspicious if from external IP (not local/private)
            if ($ip && $ip !== 'unknown' && filter_var($ip, FILTER_VALIDATE_IP)) {
                try {
                    if (!$this->isInternalIp($ip)) {
                        $isSuspicious = true;
                        $suspiciousReasons[] = 'External IP';
                    }
                } catch (\Exception $e) {
                    // Log the error but don't mark as suspicious due to IP parsing error
                    Log::warning('Error checking IP for suspicious activity: ' . $e->getMessage(), [
                        'ip' => $ip,
                        'log_id' => $log->id
                    ]);
                }
            }
            
            // Mark as suspicious if unknown user (no causer_id or causer_name is "Unknown User")
            if (!$log->causer_id || $log->causer_name === 'Unknown User') {
                $isSuspicious = true;
                $suspiciousReasons[] = 'Unknown User';
            }
            
            // Mark as suspicious if multiple activities from same IP in short time
            if ($ip && $ip !== 'unknown') {
                $ipLogs = $logsByIp->get($ip, collect());
                $recentLogs = $ipLogs->filter(function ($ipLog) use ($log) {
                    $logTime = Carbon::parse($log->created_at);
                    $ipLogTime = Carbon::parse($ipLog->created_at);
                    return $ipLogTime->diffInMinutes($logTime) <= 30; // Within 30 minutes
                });
                
                if ($recentLogs->count() > 5) {
                    $isSuspicious = true;
                    $suspiciousReasons[] = 'Multiple activities from same IP';
                }
            }
            
            // Mark as suspicious if user has many unknown activities
            if ($log->causer_id) {
                $userLogs = $logsByCauser->get($log->causer_id, collect());
                $unknownLogs = $userLogs->filter(function ($userLog) {
                    return !$userLog->causer_id || $userLog->causer_name === 'Unknown User';
                });
                
                if ($unknownLogs->count() > 3) {
                    $isSuspicious = true;
                    $suspiciousReasons[] = 'Multiple unknown activities';
                }
            }
            
            // Mark as suspicious if unusual user agent
            if ($userAgent && $this->isSuspiciousUserAgent($userAgent)) {
                $isSuspicious = true;
                $suspiciousReasons[] = 'Suspicious User Agent';
            }
            
            $log->is_suspicious = $isSuspicious;
            $log->suspicious_reasons = implode(', ', $suspiciousReasons);
            
            return $log;
        });
    }
    
    /**
     * Check if IP address is internal/private
     */
    private function isInternalIp($ip)
    {
        // Check for private IP ranges
        $privateRanges = [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '127.0.0.0/8',
            '::1/128'
        ];
        
        foreach ($privateRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if IP is in CIDR range
     */
    private function ipInRange($ip, $range)
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        $parts = explode('/', $range);
        if (count($parts) !== 2) {
            return false;
        }
        
        $subnet = $parts[0];
        $bits = (int) $parts[1];
        
        // Check if bits is empty or invalid
        if (empty($parts[1]) || $bits <= 0) {
            return false;
        }
        
        // Validate bits value
        if ($bits < 0 || $bits > 32) {
            return false;
        }
        
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        
        // Handle invalid IP addresses
        if ($ip === false || $subnet === false) {
            return false;
        }
        
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        
        return ($ip & $mask) === $subnet;
    }
    
    /**
     * Check if user agent is suspicious
     */
    private function isSuspiciousUserAgent($userAgent)
    {
        $suspiciousPatterns = [
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget',
            'python', 'java', 'php', 'perl', 'ruby', 'go-http',
            'postman', 'insomnia', 'httpie'
        ];
        
        $userAgentLower = strtolower($userAgent);
        
        foreach ($suspiciousPatterns as $pattern) {
            if (strpos($userAgentLower, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get all audit tables from the database.
     */
    private function getAuditTables(): array
    {
        $tables = DB::select('SHOW TABLES');
        $auditTables = [];
        
        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            // Include tables that start with 'audit_' and end with '_logs', or are named 'audit_logs'
            if ((strpos($tableName, 'audit_') === 0 && strpos($tableName, '_logs') !== false) || 
                $tableName === 'audit_logs' ||
                strpos($tableName, '_audit') !== false) {
                $auditTables[] = $tableName;
            }
        }
        
        return $auditTables;
    }
    
    /**
     * Resolve causer information by matching causer_id with staff table
     */
    private function resolveCauserInformation($auditLogs)
    {
        // Get all unique causer_ids that are not null
        $causerIds = $auditLogs->whereNotNull('causer_id')
                              ->pluck('causer_id')
                              ->unique()
                              ->filter()
                              ->values();
        
        if ($causerIds->isEmpty()) {
            return $auditLogs;
        }
        
        // Fetch staff information for all causer_ids
        $staffMembers = DB::table('staff')
            ->whereIn('staff_id', $causerIds)
            ->select('staff_id', 'fname', 'lname', 'work_email', 'job_name', 'division_name', 'duty_station_name')
            ->get()
            ->keyBy('staff_id');
        
        // Add staff information to each audit log
        return $auditLogs->map(function ($log) use ($staffMembers) {
            if ($log->causer_id && isset($staffMembers[$log->causer_id])) {
                $staff = $staffMembers[$log->causer_id];
                $log->causer_name = trim($staff->fname . ' ' . $staff->lname);
                $log->causer_email = $staff->work_email;
                $log->causer_job_title = $staff->job_name ?? 'N/A';
                $log->causer_division_name = $staff->division_name ?? 'N/A';
                $log->causer_duty_station_name = $staff->duty_station_name ?? 'N/A';
            } else {
                $log->causer_name = 'Unknown User';
                $log->causer_email = 'N/A';
                $log->causer_job_title = 'N/A';
                $log->causer_division_name = 'N/A';
                $log->causer_duty_station_name = 'N/A';
            }
            
            return $log;
        });
    }
}
