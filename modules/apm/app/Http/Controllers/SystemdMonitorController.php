<?php

namespace App\Http\Controllers;

use App\Services\DiskSpaceMonitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;

class SystemdMonitorController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function getIndexData(): array
    {
        if (! in_array(89, user_session('permissions', []))) {
            abort(403, 'Unauthorized access to systemd monitor');
        }

        $diskMonitor = app(DiskSpaceMonitorService::class);

        return [
            'queue_worker_status' => $this->getServiceStatus('laravel-queue-worker'),
            'scheduler_status' => $this->getServiceStatus('laravel-scheduler'),
            'failed_jobs_count' => $this->getFailedJobsCount(),
            'queue_size' => $this->getQueueSize(),
            'recent_queue_logs' => $this->getRecentLogs('laravel-queue-worker'),
            'recent_scheduler_logs' => $this->getRecentLogs('laravel-scheduler'),
            'last_daily_notification' => $this->getLastDailyNotificationTime(),
            'approver_count' => $this->getApproverCount(),
            'health' => $this->getSystemHealth($diskMonitor),
            'disk' => $diskMonitor->getDiskSpace(base_path()) ?: null,
        ];
    }

    /**
     * @return array{overall: string, checks: list<array<string, mixed>>}
     */
    private function getSystemHealth(DiskSpaceMonitorService $diskMonitor): array
    {
        $checks = [
            $this->buildCheck('php', 'PHP', PHP_VERSION, 'ok', 'bx-code-alt', 'SAPI: '.php_sapi_name()),
            $this->buildCheck('laravel', 'Laravel', app()->version(), 'ok', 'bx-layer', config('app.name')),
            $this->buildCheck('environment', 'Environment', config('app.env'), config('app.env') === 'production' ? 'ok' : 'warning', 'bx-globe', config('app.debug') ? 'Debug mode ON' : 'Debug mode OFF'),
            $this->checkDatabase(),
            $this->checkRedis(),
            $this->buildCheck('cache', 'Cache driver', config('cache.default', 'unknown'), 'ok', 'bx-data', 'Store: '.config('cache.stores.'.config('cache.default').'.driver', 'n/a')),
            $this->buildCheck('queue', 'Queue driver', config('queue.default', 'unknown'), 'ok', 'bx-list-ul', 'Connection: '.config('queue.connections.'.config('queue.default').'.driver', 'n/a')),
            $this->checkMemory(),
            $this->checkCpu(),
            $this->checkDisk($diskMonitor),
            $this->checkStorageWritable(),
            $this->checkOpcache(),
        ];

        $overall = 'healthy';
        foreach ($checks as $check) {
            if ($check['status'] === 'critical') {
                $overall = 'critical';
                break;
            }
            if ($check['status'] === 'warning' && $overall !== 'critical') {
                $overall = 'warning';
            }
        }

        return ['overall' => $overall, 'checks' => $checks];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCheck(string $key, string $label, string $value, string $status, string $icon, string $detail = ''): array
    {
        return compact('key', 'label', 'value', 'status', 'icon', 'detail');
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $version = DB::selectOne('select version() as version');
            $driver = config('database.default');

            return $this->buildCheck(
                'database',
                'Database',
                $driver,
                'ok',
                'bx-server',
                isset($version->version) ? strtok((string) $version->version, '-') : 'Connected'
            );
        } catch (\Throwable $e) {
            return $this->buildCheck('database', 'Database', 'Offline', 'critical', 'bx-server', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkRedis(): array
    {
        $default = config('database.redis.default.host') ? 'default' : null;
        $usesRedis = in_array(config('cache.default'), ['redis'], true)
            || in_array(config('queue.default'), ['redis'], true)
            || in_array(config('session.driver'), ['redis'], true);

        if (! $usesRedis && ! $default) {
            return $this->buildCheck('redis', 'Redis', 'Not configured', 'ok', 'bx-memory-card', 'Application not using Redis');
        }

        try {
            $connection = Redis::connection();
            $ping = $connection->ping();
            $info = method_exists($connection, 'info') ? $connection->info('server') : [];
            $version = is_array($info) ? ($info['redis_version'] ?? 'Connected') : 'Connected';

            return $this->buildCheck(
                'redis',
                'Redis',
                is_string($ping) ? strtoupper($ping) : 'PONG',
                'ok',
                'bx-memory-card',
                'Version '.$version
            );
        } catch (\Throwable $e) {
            return $this->buildCheck('redis', 'Redis', 'Unreachable', $usesRedis ? 'critical' : 'warning', 'bx-memory-card', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkMemory(): array
    {
        $used = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = ini_get('memory_limit');

        $limitBytes = $this->parseIniSize($limit);
        $percent = ($limitBytes > 0) ? round(($used / $limitBytes) * 100, 1) : null;
        $status = 'ok';
        if ($percent !== null && $percent >= 90) {
            $status = 'critical';
        } elseif ($percent !== null && $percent >= 75) {
            $status = 'warning';
        }

        return $this->buildCheck(
            'memory',
            'PHP memory',
            $this->formatBytes($used),
            $status,
            'bx-chip',
            'Peak '.$this->formatBytes($peak).($limit ? ' · Limit '.$limit : '')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function checkCpu(): array
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;

        if (is_array($load) && isset($load[0])) {
            $value = number_format($load[0], 2);
            $status = $load[0] >= 4 ? 'critical' : ($load[0] >= 2 ? 'warning' : 'ok');

            return $this->buildCheck(
                'cpu',
                'CPU load',
                $value,
                $status,
                'bx-microchip',
                '1m / 5m / 15m: '.implode(' · ', array_map(fn ($n) => number_format((float) $n, 2), $load))
            );
        }

        try {
            $result = Process::run('uptime');
            if ($result->successful()) {
                return $this->buildCheck('cpu', 'CPU load', 'Available', 'ok', 'bx-microchip', trim($result->output()));
            }
        } catch (\Throwable) {
            // fall through
        }

        return $this->buildCheck('cpu', 'CPU load', 'N/A', 'ok', 'bx-microchip', 'Load average unavailable on this host');
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDisk(DiskSpaceMonitorService $diskMonitor): array
    {
        $disk = $diskMonitor->getDiskSpace(base_path());

        if (! $disk) {
            return $this->buildCheck('disk', 'Disk space', 'Unknown', 'warning', 'bx-hdd', 'Unable to read disk metrics');
        }

        $status = match ($disk['status'] ?? 'ok') {
            'critical' => 'critical',
            'warning' => 'warning',
            default => 'ok',
        };

        return $this->buildCheck(
            'disk',
            'Disk space',
            $disk['usage_percent'].'% used',
            $status,
            'bx-hdd',
            $disk['free_formatted'].' free of '.$disk['total_formatted']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function checkStorageWritable(): array
    {
        $paths = [
            storage_path('logs'),
            storage_path('framework/cache'),
            storage_path('app'),
        ];

        $failed = [];
        foreach ($paths as $path) {
            if (! is_writable($path)) {
                $failed[] = basename($path);
            }
        }

        if ($failed !== []) {
            return $this->buildCheck('storage', 'Storage writable', 'Issues detected', 'critical', 'bx-folder', 'Not writable: '.implode(', ', $failed));
        }

        return $this->buildCheck('storage', 'Storage writable', 'OK', 'ok', 'bx-folder', 'Logs, cache & app storage');
    }

    /**
     * @return array<string, mixed>
     */
    private function checkOpcache(): array
    {
        if (! function_exists('opcache_get_status')) {
            return $this->buildCheck('opcache', 'OPcache', 'Not available', 'ok', 'bx-bolt-circle', 'Extension not loaded');
        }

        $status = @opcache_get_status(false);
        if (! is_array($status)) {
            return $this->buildCheck('opcache', 'OPcache', 'Disabled', 'warning', 'bx-bolt-circle', 'OPcache installed but inactive');
        }

        $enabled = ! empty($status['opcache_enabled']);
        $hitRate = isset($status['opcache_statistics']['opcache_hit_rate'])
            ? round((float) $status['opcache_statistics']['opcache_hit_rate'], 1).'% hit rate'
            : 'Enabled';

        return $this->buildCheck(
            'opcache',
            'OPcache',
            $enabled ? 'Enabled' : 'Disabled',
            $enabled ? 'ok' : 'warning',
            'bx-bolt-circle',
            $hitRate
        );
    }

    private function parseIniSize(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (float) $value,
        };
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 1).' '.$units[$i];
    }

    public function index()
    {
        return redirect()->route('system-configs.index', ['tab' => 'monitor']);
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
                        ->select('director_id as staff_id')
                        ->whereNotNull('director_id')
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
