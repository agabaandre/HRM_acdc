<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class SystemdMonitorController extends Controller
{
    public function index()
    {
        // Check if user has permission to access systemd monitor
        if (!in_array(89, user_session('permissions', []))) {
            abort(403, 'Unauthorized access to systemd monitor');
        }

        $data = [
            'queue_worker_status' => $this->getServiceStatus('laravel-queue-worker'),
            'scheduler_status' => $this->getServiceStatus('laravel-scheduler'),
            'failed_jobs_count' => $this->getFailedJobsCount(),
            'queue_size' => $this->getQueueSize(),
            'recent_queue_logs' => $this->getRecentLogs('laravel-queue-worker'),
            'recent_scheduler_logs' => $this->getRecentLogs('laravel-scheduler'),
            'last_daily_notification' => $this->getLastDailyNotificationTime(),
            'approver_count' => $this->getApproverCount(),
        ];

        return view('systemd-monitor.index', $data);
    }

    private function getServiceStatus($serviceName)
    {
        try {
            $result = Process::run("systemctl is-active {$serviceName}");
            return [
                'status' => trim($result->output()),
                'is_running' => trim($result->output()) === 'active'
            ];
        } catch (\Exception $e) {
            Log::error("Failed to check service status for {$serviceName}: " . $e->getMessage());
            return [
                'status' => 'unknown',
                'is_running' => false
            ];
        }
    }

    private function getFailedJobsCount()
    {
        try {
            $result = Process::run('php artisan queue:failed');
            $output = $result->output();
            return substr_count($output, 'database@default');
        } catch (\Exception $e) {
            Log::error("Failed to get failed jobs count: " . $e->getMessage());
            return 0;
        }
    }

    private function getQueueSize()
    {
        try {
            $result = Process::run('php artisan tinker --execute="echo \\Illuminate\\Support\\Facades\\DB::table(\'jobs\')->count();"');
            $output = trim($result->output());
            return is_numeric($output) ? (int)$output : 0;
        } catch (\Exception $e) {
            Log::error("Failed to get queue size: " . $e->getMessage());
            return 0;
        }
    }

    private function getRecentLogs($serviceName)
    {
        try {
            $result = Process::run("journalctl -u {$serviceName} --since '5 minutes ago' --no-pager | tail -10");
            return $result->output();
        } catch (\Exception $e) {
            Log::error("Failed to get recent logs for {$serviceName}: " . $e->getMessage());
            return "Unable to retrieve logs: " . $e->getMessage();
        }
    }

    private function getLastDailyNotificationTime()
    {
        try {
            // Check logs for the last daily notification
            $result = Process::run("grep -i 'daily pending approvals notification job' /opt/homebrew/var/www/staff/apm/storage/logs/laravel-$(date +%Y-%m-%d).log | tail -1 | cut -d' ' -f1-2");
            $output = trim($result->output());
            
            if (empty($output)) {
                // Try yesterday's log
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $result = Process::run("grep -i 'daily pending approvals notification job' /opt/homebrew/var/www/staff/apm/storage/logs/laravel-{$yesterday}.log | tail -1 | cut -d' ' -f1-2");
                $output = trim($result->output());
            }
            
            return $output ?: 'Never';
        } catch (\Exception $e) {
            Log::error("Failed to get last daily notification time: " . $e->getMessage());
            return 'Unknown';
        }
    }

    private function getApproverCount()
    {
        try {
            // Get count of staff who are approvers (similar to the job logic)
            $divisionApprovers = \Illuminate\Support\Facades\DB::table('divisions')
                ->select('division_head as staff_id')
                ->whereNotNull('division_head')
                ->union(
                    \Illuminate\Support\Facades\DB::table('divisions')
                        ->select('focal_person as staff_id')
                        ->whereNotNull('focal_person')
                )
                ->union(
                    \Illuminate\Support\Facades\DB::table('divisions')
                        ->select('admin_assistant as staff_id')
                        ->whereNotNull('admin_assistant')
                )
                ->union(
                    \Illuminate\Support\Facades\DB::table('divisions')
                        ->select('finance_officer as staff_id')
                        ->whereNotNull('finance_officer')
                )
                ->get()
                ->pluck('staff_id')
                ->unique()
                ->count();
            
            return $divisionApprovers;
        } catch (\Exception $e) {
            Log::error("Failed to get approver count: " . $e->getMessage());
            return 0;
        }
    }

    public function executeCommand(Request $request)
    {
        // Check if user has permission
        if (!in_array(89, user_session('permissions', []))) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $command = $request->input('command');
        $allowedCommands = [
            'restart-queue-worker',
            'restart-scheduler',
            'start-queue-worker',
            'start-scheduler',
            'stop-queue-worker',
            'stop-scheduler',
            'clear-failed-jobs',
            'retry-failed-jobs',
            'send-daily-notifications'
        ];

        if (!in_array($command, $allowedCommands)) {
            return response()->json(['error' => 'Invalid command'], 400);
        }

        try {
            if ($command === 'send-daily-notifications') {
                // Dispatch the daily notifications job
                \App\Jobs\SendDailyPendingApprovalsNotificationJob::dispatch();
                
                return response()->json([
                    'success' => true,
                    'output' => 'Daily notifications job dispatched successfully',
                    'error' => ''
                ]);
            } else {
                $systemdCommand = $this->getSystemdCommand($command);
                $result = Process::run($systemdCommand);
                
                return response()->json([
                    'success' => true,
                    'output' => $result->output(),
                    'error' => $result->errorOutput()
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to execute command {$command}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getSystemdCommand($command)
    {
        switch ($command) {
            case 'restart-queue-worker':
                return 'sudo systemctl restart laravel-queue-worker';
            case 'restart-scheduler':
                return 'sudo systemctl restart laravel-scheduler';
            case 'start-queue-worker':
                return 'sudo systemctl start laravel-queue-worker';
            case 'start-scheduler':
                return 'sudo systemctl start laravel-scheduler';
            case 'stop-queue-worker':
                return 'sudo systemctl stop laravel-queue-worker';
            case 'stop-scheduler':
                return 'sudo systemctl stop laravel-scheduler';
            case 'clear-failed-jobs':
                return 'php artisan queue:flush';
            case 'retry-failed-jobs':
                return 'php artisan queue:retry all';
            default:
                throw new \Exception('Unknown command');
        }
    }
}
