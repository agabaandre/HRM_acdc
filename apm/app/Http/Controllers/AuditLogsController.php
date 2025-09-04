<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuditLogsController extends Controller
{
    /**
     * Display the audit logs index page.
     */
    public function index(Request $request): View
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
                \Log::error("Error processing audit table {$table}: " . $e->getMessage());
                continue;
            }
        }
        
        // Sort by created_at desc
        $auditLogs = $auditLogs->sortByDesc('created_at');
        
        // Resolve causer information (staff details)
        $auditLogs = $this->resolveCauserInformation($auditLogs);
        
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
        
        return view('audit-logs.index', compact('auditLogs', 'actions', 'tables', 'stats'));
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
            ->select('staff_id', 'fname', 'lname', 'work_email', 'job_name', 'position')
            ->get()
            ->keyBy('staff_id');
        
        // Add staff information to each audit log
        return $auditLogs->map(function ($log) use ($staffMembers) {
            if ($log->causer_id && isset($staffMembers[$log->causer_id])) {
                $staff = $staffMembers[$log->causer_id];
                $log->causer_name = trim($staff->fname . ' ' . $staff->lname);
                $log->causer_email = $staff->work_email;
                $log->causer_job_title = $staff->job_name ?? $staff->position ?? 'N/A';
            } else {
                $log->causer_name = 'Unknown User';
                $log->causer_email = 'N/A';
                $log->causer_job_title = 'N/A';
            }
            
            return $log;
        });
    }
}
