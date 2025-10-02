<?php

/**
 * Queue Worker Management Script
 * Run this on your production server: php manage_queue_worker.php [start|stop|restart|status]
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$action = $argv[1] ?? 'status';

echo "=== Queue Worker Management ===\n\n";

try {
    switch ($action) {
        case 'start':
            echo "Starting queue worker...\n";
            
            // Check if already running
            exec('ps aux | grep "queue:work" | grep -v grep', $processes);
            if (count($processes) > 0) {
                echo "⚠️  Queue worker is already running\n";
                break;
            }
            
            // Start queue worker in background
            $command = 'php artisan queue:work --daemon --tries=3 --timeout=60 > /dev/null 2>&1 &';
            exec($command);
            
            sleep(2); // Wait a moment
            
            // Check if started successfully
            exec('ps aux | grep "queue:work" | grep -v grep', $processes);
            if (count($processes) > 0) {
                echo "✅ Queue worker started successfully\n";
            } else {
                echo "❌ Failed to start queue worker\n";
            }
            break;
            
        case 'stop':
            echo "Stopping queue worker...\n";
            
            // Find and kill queue worker processes
            exec('ps aux | grep "queue:work" | grep -v grep | awk \'{print $2}\'', $pids);
            
            if (empty($pids)) {
                echo "⚠️  No queue worker processes found\n";
            } else {
                foreach ($pids as $pid) {
                    exec("kill {$pid}");
                    echo "✅ Stopped process {$pid}\n";
                }
            }
            break;
            
        case 'restart':
            echo "Restarting queue worker...\n";
            
            // Stop first
            exec('ps aux | grep "queue:work" | grep -v grep | awk \'{print $2}\'', $pids);
            foreach ($pids as $pid) {
                exec("kill {$pid}");
            }
            
            sleep(2);
            
            // Start again
            $command = 'php artisan queue:work --daemon --tries=3 --timeout=60 > /dev/null 2>&1 &';
            exec($command);
            
            sleep(2);
            
            // Check if restarted successfully
            exec('ps aux | grep "queue:work" | grep -v grep', $processes);
            if (count($processes) > 0) {
                echo "✅ Queue worker restarted successfully\n";
            } else {
                echo "❌ Failed to restart queue worker\n";
            }
            break;
            
        case 'status':
        default:
            echo "Queue Worker Status:\n";
            
            // Check processes
            exec('ps aux | grep "queue:work" | grep -v grep', $processes);
            
            if (count($processes) > 0) {
                echo "✅ Queue worker is running:\n";
                foreach ($processes as $process) {
                    echo "  " . trim($process) . "\n";
                }
            } else {
                echo "❌ Queue worker is not running\n";
            }
            
            // Check queue status
            $jobsCount = DB::table('jobs')->count();
            $failedJobsCount = DB::table('failed_jobs')->count();
            
            echo "\nQueue Status:\n";
            echo "  - Pending jobs: {$jobsCount}\n";
            echo "  - Failed jobs: {$failedJobsCount}\n";
            
            if ($jobsCount > 0 && count($processes) === 0) {
                echo "\n⚠️  There are {$jobsCount} jobs waiting but no worker is running!\n";
                echo "   Run: php manage_queue_worker.php start\n";
            }
            break;
    }
    
    echo "\n=== Management Complete ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
