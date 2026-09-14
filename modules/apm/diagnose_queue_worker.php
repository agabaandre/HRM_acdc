<?php

/**
 * Queue Worker Diagnostic Script
 * Run this on your production server: php diagnose_queue_worker.php
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

echo "=== Queue Worker Diagnostic Script ===\n\n";

try {
    // Test 1: Check queue configuration
    echo "Test 1: Queue Configuration\n";
    $queueConnection = config('queue.default');
    $queueDriver = config("queue.connections.{$queueConnection}.driver");
    
    echo "  - Default Connection: {$queueConnection}\n";
    echo "  - Driver: {$queueDriver}\n";
    
    if ($queueDriver === 'database') {
        echo "  ✅ Using database driver\n";
    } else {
        echo "  ⚠️  Using {$queueDriver} driver\n";
    }
    
    echo "\n";
    
    // Test 2: Check database tables
    echo "Test 2: Database Tables\n";
    
    try {
        $jobsCount = DB::table('jobs')->count();
        $failedJobsCount = DB::table('failed_jobs')->count();
        
        echo "  - Jobs in queue: {$jobsCount}\n";
        echo "  - Failed jobs: {$failedJobsCount}\n";
        
        if ($jobsCount > 0) {
            echo "  ✅ Jobs are in the queue\n";
        } else {
            echo "  ⚠️  No jobs in queue\n";
        }
        
    } catch (\Exception $e) {
        echo "  ❌ Database error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // Test 3: Check queue worker process
    echo "Test 3: Queue Worker Process\n";
    
    // Check if queue worker is running
    $processes = [];
    if (function_exists('exec')) {
        exec('ps aux | grep "queue:work" | grep -v grep', $processes);
    }
    
    if (count($processes) > 0) {
        echo "  ✅ Queue worker processes found:\n";
        foreach ($processes as $process) {
            echo "    - " . trim($process) . "\n";
        }
    } else {
        echo "  ❌ No queue worker processes found\n";
        echo "  - Run: php artisan queue:work\n";
    }
    
    echo "\n";
    
    // Test 4: Test job processing
    echo "Test 4: Test Job Processing\n";
    
    try {
        // Create a test job
        $testJob = new \App\Jobs\SendMatrixNotificationJob(
            \App\Models\Matrix::first(),
            \App\Models\Staff::where('active', 1)->first(),
            'test',
            'Queue worker diagnostic test'
        );
        
        // Dispatch the job
        dispatch($testJob);
        echo "  ✅ Test job dispatched successfully\n";
        
        // Try to process one job
        echo "  - Attempting to process one job...\n";
        
        $output = [];
        $returnCode = 0;
        exec('php artisan queue:work --once --timeout=30 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "  ✅ Job processed successfully\n";
            echo "  - Output: " . implode("\n", $output) . "\n";
        } else {
            echo "  ❌ Job processing failed\n";
            echo "  - Return code: {$returnCode}\n";
            echo "  - Output: " . implode("\n", $output) . "\n";
        }
        
    } catch (\Exception $e) {
        echo "  ❌ Job processing error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // Test 5: Check supervisor/systemd services
    echo "Test 5: Service Management\n";
    
    // Check supervisor
    if (function_exists('exec')) {
        exec('supervisorctl status 2>/dev/null', $supervisorOutput);
        if (!empty($supervisorOutput)) {
            echo "  - Supervisor status:\n";
            foreach ($supervisorOutput as $line) {
                echo "    " . $line . "\n";
            }
        } else {
            echo "  - Supervisor not running or not configured\n";
        }
    }
    
    // Check systemd
    if (function_exists('exec')) {
        exec('systemctl status laravel-worker 2>/dev/null', $systemdOutput);
        if (!empty($systemdOutput)) {
            echo "  - Systemd status:\n";
            foreach ($systemdOutput as $line) {
                echo "    " . $line . "\n";
            }
        } else {
            echo "  - Systemd service not found\n";
        }
    }
    
    echo "\n";
    
    // Test 6: Check logs
    echo "Test 6: Log Analysis\n";
    
    $logFile = storage_path('logs/laravel-' . date('Y-m-d') . '.log');
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $queueErrors = substr_count($logContent, 'queue');
        $jobErrors = substr_count($logContent, 'job');
        
        echo "  - Log file: {$logFile}\n";
        echo "  - Queue-related entries: {$queueErrors}\n";
        echo "  - Job-related entries: {$jobErrors}\n";
        
        // Get last few queue-related log entries
        $logLines = explode("\n", $logContent);
        $queueLogs = array_filter($logLines, function($line) {
            return stripos($line, 'queue') !== false || stripos($line, 'job') !== false;
        });
        
        $recentQueueLogs = array_slice($queueLogs, -5);
        if (!empty($recentQueueLogs)) {
            echo "  - Recent queue logs:\n";
            foreach ($recentQueueLogs as $log) {
                echo "    " . trim($log) . "\n";
            }
        }
    } else {
        echo "  - No log file found for today\n";
    }
    
    echo "\n";
    
    // Test 7: Check permissions
    echo "Test 7: File Permissions\n";
    
    $storagePath = storage_path();
    $bootstrapPath = base_path('bootstrap/cache');
    
    echo "  - Storage path: {$storagePath}\n";
    echo "  - Storage writable: " . (is_writable($storagePath) ? 'Yes' : 'No') . "\n";
    echo "  - Bootstrap cache: {$bootstrapPath}\n";
    echo "  - Bootstrap writable: " . (is_writable($bootstrapPath) ? 'Yes' : 'No') . "\n";
    
    echo "\n";
    
    // Test 8: Check environment
    echo "Test 8: Environment Check\n";
    
    echo "  - PHP Version: " . PHP_VERSION . "\n";
    echo "  - Laravel Version: " . app()->version() . "\n";
    echo "  - Environment: " . app()->environment() . "\n";
    echo "  - Debug Mode: " . (config('app.debug') ? 'On' : 'Off') . "\n";
    echo "  - Queue Connection: " . config('queue.default') . "\n";
    
    echo "\n=== Diagnostic Complete ===\n";
    echo "Common solutions:\n";
    echo "1. Start queue worker: php artisan queue:work\n";
    echo "2. Restart queue worker: php artisan queue:restart\n";
    echo "3. Clear failed jobs: php artisan queue:flush\n";
    echo "4. Check supervisor: supervisorctl restart laravel-worker\n";
    echo "5. Check systemd: systemctl restart laravel-worker\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
