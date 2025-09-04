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
            $tableLogs = DB::table($table)
                ->select('*')
                ->addSelect(DB::raw("'{$table}' as source_table"))
                ->orderBy('created_at', 'desc')
                ->limit(100) // Limit per table to prevent memory issues
                ->get();
                
            $auditLogs = $auditLogs->merge($tableLogs);
        }
        
        // Sort by created_at desc
        $auditLogs = $auditLogs->sortByDesc('created_at');
        
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
            if (strpos($tableName, 'audit_') === 0 && strpos($tableName, '_logs') !== false) {
                $auditTables[] = $tableName;
            }
        }
        
        return $auditTables;
    }
}
