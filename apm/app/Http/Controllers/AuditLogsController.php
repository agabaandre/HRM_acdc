<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AuditLogsController extends Controller
{
    /**
     * Display the audit logs index page.
     */
    public function index(Request $request): View
    {
        $query = AuditLog::with('user')->latest();

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                  ->orWhere('user_email', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('resource_type', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('url', 'like', "%{$search}%");
            });
        }

        if ($request->filled('action')) {
            $query->where('action', $request->get('action'));
        }

        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->get('resource_type'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        if ($request->filled('route_name')) {
            $query->where('route_name', 'like', "%{$request->get('route_name')}%");
        }

        // Paginate results
        $auditLogs = $query->paginate(50)->withQueryString();

        // Get filter options - handle case when no audit logs exist yet
        try {
            $actions = AuditLog::distinct()->pluck('action')->sort()->values();
            $resourceTypes = AuditLog::distinct()->pluck('resource_type')->sort()->values();
            $routeNames = AuditLog::distinct()->pluck('route_name')->filter()->sort()->values();
        } catch (\Exception $e) {
            $actions = collect();
            $resourceTypes = collect();
            $routeNames = collect();
        }
        
        $users = User::select('id', 'fname', 'lname', 'work_email')
                    ->orderBy('fname')
                    ->orderBy('lname')
                    ->get();

        // Get statistics
        $stats = $this->getStatistics($request);

        return view('audit-logs.index', compact(
            'auditLogs',
            'actions',
            'resourceTypes',
            'routeNames',
            'users',
            'stats'
        ));
    }

    /**
     * Show detailed view of a specific audit log.
     */
    public function show(AuditLog $auditLog): View
    {
        return view('audit-logs.show', compact('auditLog'));
    }

    /**
     * Get audit log statistics.
     */
    private function getStatistics(Request $request): array
    {
        try {
            $baseQuery = AuditLog::query();

            // Apply same filters as main query
            if ($request->filled('date_from')) {
                $baseQuery->whereDate('created_at', '>=', $request->get('date_from'));
            }
            if ($request->filled('date_to')) {
                $baseQuery->whereDate('created_at', '<=', $request->get('date_to'));
            }

            $totalLogs = $baseQuery->count();
            
            $actionsCount = $baseQuery->clone()
                ->select('action', DB::raw('count(*) as count'))
                ->groupBy('action')
                ->orderBy('count', 'desc')
                ->get();

            $resourceTypesCount = $baseQuery->clone()
                ->select('resource_type', DB::raw('count(*) as count'))
                ->groupBy('resource_type')
                ->orderBy('count', 'desc')
                ->get();

            $topUsers = $baseQuery->clone()
                ->select('user_name', DB::raw('count(*) as count'))
                ->whereNotNull('user_name')
                ->groupBy('user_name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            $recentActivity = $baseQuery->clone()
                ->where('created_at', '>=', Carbon::now()->subHours(24))
                ->count();

            return [
                'total_logs' => $totalLogs,
                'actions_count' => $actionsCount,
                'resource_types_count' => $resourceTypesCount,
                'top_users' => $topUsers,
                'recent_activity' => $recentActivity,
            ];
        } catch (\Exception $e) {
            // Return empty statistics if there's an error
            return [
                'total_logs' => 0,
                'actions_count' => collect(),
                'resource_types_count' => collect(),
                'top_users' => collect(),
                'recent_activity' => 0,
            ];
        }
    }

    /**
     * Export audit logs to CSV.
     */
    public function export(Request $request)
    {
        $query = AuditLog::with('user')->latest();

        // Apply same filters as index
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                  ->orWhere('user_email', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('resource_type', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('action')) {
            $query->where('action', $request->get('action'));
        }

        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->get('resource_type'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        $auditLogs = $query->limit(10000)->get(); // Limit to prevent memory issues

        $filename = 'audit_logs_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($auditLogs) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'ID',
                'User Name',
                'User Email',
                'Action',
                'Resource Type',
                'Resource ID',
                'Route Name',
                'URL',
                'Method',
                'IP Address',
                'Description',
                'Created At'
            ]);

            // CSV data
            foreach ($auditLogs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->user_name,
                    $log->user_email,
                    $log->action,
                    $log->resource_type,
                    $log->resource_id,
                    $log->route_name,
                    $log->url,
                    $log->method,
                    $log->ip_address,
                    $log->description,
                    $log->created_at->format('Y-m-d H:i:s')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Clean up old audit logs based on retention period.
     */
    public function cleanup()
    {
        $retentionDays = config('audit.retention_days', 60); // Default 2 months
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $deletedCount = AuditLog::where('created_at', '<', $cutoffDate)->delete();

        return response()->json([
            'success' => true,
            'message' => "Cleaned up {$deletedCount} audit logs older than {$retentionDays} days.",
            'deleted_count' => $deletedCount
        ]);
    }
}
