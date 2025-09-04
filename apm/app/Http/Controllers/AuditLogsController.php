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
            if ($ip && !$this->isInternalIp($ip)) {
                $isSuspicious = true;
                $suspiciousReasons[] = 'External IP';
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
        
        list($subnet, $bits) = explode('/', $range);
        
        if ($bits === null) {
            $bits = 32;
        }
        
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
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
