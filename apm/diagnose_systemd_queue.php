<?php

/**
 * Systemd Queue Service Diagnostic Script
 * Run this on your production server: php diagnose_systemd_queue.php
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Systemd Queue Service Diagnostic ===\n\n";

try {
    // Test 1: Check systemd service status
    echo "Test 1: Systemd Service Status\n";
    
    $serviceName = 'laravel-queue-apm.service';
    $statusOutput = [];
    exec("systemctl status {$serviceName} 2>&1", $statusOutput);
    
    foreach ($statusOutput as $line) {
        echo "  " . $line . "\n";
    }
    
    // Check if service is active
    $isActive = false;
    foreach ($statusOutput as $line) {
        if (strpos($line, 'Active: active (running)') !== false) {
            $isActive = true;
            break;
        }
    }
    
    if ($isActive) {
        echo "  ✅ Service is running\n";
    } else {
        echo "  ❌ Service is not running\n";
    }
    
    echo "\n";
    
    // Test 2: Check queue worker processes
    echo "Test 2: Queue Worker Processes\n";
    
    $processes = [];
    exec('ps aux | grep "queue:work" | grep -v grep', $processes);
    
    if (count($processes) > 0) {
        echo "  ✅ Queue worker processes found:\n";
        foreach ($processes as $process) {
            echo "    " . trim($process) . "\n";
        }
    } else {
        echo "  ❌ No queue worker processes found\n";
    }
    
    echo "\n";
    
    // Test 3: Check queue status
    echo "Test 3: Queue Status\n";
    
    $jobsCount = DB::table('jobs')->count();
    $failedJobsCount = DB::table('failed_jobs')->count();
    
    echo "  - Pending jobs: {$jobsCount}\n";
    echo "  - Failed jobs: {$failedJobsCount}\n";
    
    if ($jobsCount > 0) {
        echo "  ⚠️  Jobs are waiting but not being processed\n";
    }
    
    echo "\n";
    
    // Test 4: Test manual job processing
    echo "Test 4: Manual Job Processing Test\n";
    
    try {
        $output = [];
        $returnCode = 0;
        exec('php artisan queue:work --once --timeout=30 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "  ✅ Manual job processing works\n";
            echo "  - Output: " . implode("\n", $output) . "\n";
        } else {
            echo "  ❌ Manual job processing failed\n";
            echo "  - Return code: {$returnCode}\n";
            echo "  - Output: " . implode("\n", $output) . "\n";
        }
    } catch (Exception $e) {
        echo "  ❌ Manual job processing error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // Test 5: Check service logs
    echo "Test 5: Service Logs\n";
    
    $logOutput = [];
    exec("journalctl -u {$serviceName} --no-pager -n 20 2>&1", $logOutput);
    
    if (!empty($logOutput)) {
        echo "  Recent service logs:\n";
        foreach ($logOutput as $line) {
            echo "    " . $line . "\n";
        }
    } else {
        echo "  ⚠️  No service logs found\n";
    }
    
    echo "\n";
    
    // Test 6: Check service configuration
    echo "Test 6: Service Configuration\n";
    
    $serviceFile = "/etc/systemd/system/{$serviceName}";
    if (file_exists($serviceFile)) {
        echo "  ✅ Service file exists: {$serviceFile}\n";
        
        $serviceContent = file_get_contents($serviceFile);
        echo "  Service configuration:\n";
        echo "  ---\n";
        echo $serviceContent;
        echo "  ---\n";
    } else {
        echo "  ❌ Service file not found: {$serviceFile}\n";
    }
    
    echo "\n";
    
    // Test 7: Check permissions
    echo "Test 7: Permissions Check\n";
    
    $currentUser = get_current_user();
    $currentDir = getcwd();
    
    echo "  - Current user: {$currentUser}\n";
    echo "  - Current directory: {$currentDir}\n";
    echo "  - Directory writable: " . (is_writable($currentDir) ? 'Yes' : 'No') . "\n";
    
    // Check if user can run artisan commands
    $artisanTest = [];
    exec('php artisan --version 2>&1', $artisanTest);
    if (!empty($artisanTest)) {
        echo "  - Artisan accessible: Yes\n";
        echo "    " . implode("\n", $artisanTest) . "\n";
    } else {
        echo "  - Artisan accessible: No\n";
    }
    
    echo "\n";
    
    // Test 8: Check for common issues
    echo "Test 8: Common Issues Check\n";
    
    // Check if service is enabled
    $enabledOutput = [];
    exec("systemctl is-enabled {$serviceName} 2>&1", $enabledOutput);
    $isEnabled = !empty($enabledOutput) && $enabledOutput[0] === 'enabled';
    
    echo "  - Service enabled: " . ($isEnabled ? 'Yes' : 'No') . "\n";
    
    // Check if there are any failed jobs blocking the queue
    $blockingJobs = DB::table('jobs')
        ->where('attempts', '>', 0)
        ->count();
    
    echo "  - Jobs with attempts: {$blockingJobs}\n";
    
    // Check if jobs are too old
    $oldJobs = DB::table('jobs')
        ->where('created_at', '<', now()->subHours(24))
        ->count();
    
    echo "  - Jobs older than 24h: {$oldJobs}\n";
    
    echo "\n";
    
    // Recommendations
    echo "=== Recommendations ===\n";
    
    if (!$isActive) {
        echo "1. Start the service: sudo systemctl start {$serviceName}\n";
    }
    
    if (!$isEnabled) {
        echo "2. Enable the service: sudo systemctl enable {$serviceName}\n";
    }
    
    if (count($processes) === 0) {
        echo "3. No queue worker processes found - service may not be starting properly\n";
        echo "   Check logs: sudo journalctl -u {$serviceName} -f\n";
    }
    
    if ($jobsCount > 0 && count($processes) === 0) {
        echo "4. Jobs are waiting but no worker is running\n";
        echo "   Restart service: sudo systemctl restart {$serviceName}\n";
    }
    
    if ($oldJobs > 0) {
        echo "5. Consider clearing old jobs: php artisan queue:clear\n";
    }
    
    echo "\n=== Diagnostic Complete ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
