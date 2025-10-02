<?php

/**
 * Laravel 12 Queue Fix Script
 * Run this on your production server: php laravel12_queue_fix.php
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

echo "=== Laravel 12 Queue Fix ===\n\n";

try {
    // Test 1: Check Laravel version
    echo "Test 1: Laravel Version Check\n";
    $version = app()->version();
    echo "  - Laravel Version: {$version}\n";
    
    if (version_compare($version, '12.0.0', '>=')) {
        echo "  ✅ Laravel 12+ detected\n";
    } else {
        echo "  ⚠️  Older Laravel version detected\n";
    }
    
    echo "\n";
    
    // Test 2: Check maintenance mode
    echo "Test 2: Maintenance Mode Check\n";
    
    $maintenanceFile = storage_path('framework/down');
    if (file_exists($maintenanceFile)) {
        echo "  ❌ Application is in maintenance mode\n";
        echo "  - Run: php artisan up\n";
    } else {
        echo "  ✅ Application is not in maintenance mode\n";
    }
    
    echo "\n";
    
    // Test 3: Clear caches (Laravel 12 requirement)
    echo "Test 3: Cache Clearing\n";
    
    echo "  - Clearing config cache...\n";
    \Artisan::call('config:clear');
    
    echo "  - Clearing application cache...\n";
    \Artisan::call('cache:clear');
    
    echo "  - Clearing route cache...\n";
    \Artisan::call('route:clear');
    
    echo "  - Clearing view cache...\n";
    \Artisan::call('view:clear');
    
    echo "  ✅ All caches cleared\n";
    
    echo "\n";
    
    // Test 4: Check queue configuration
    echo "Test 4: Queue Configuration\n";
    
    $defaultConnection = config('queue.default');
    $queueDriver = config("queue.connections.{$defaultConnection}.driver");
    
    echo "  - Default connection: {$defaultConnection}\n";
    echo "  - Driver: {$queueDriver}\n";
    
    if ($queueDriver === 'database') {
        echo "  ✅ Using database driver\n";
        
        // Check if jobs table exists
        try {
            $jobsCount = DB::table('jobs')->count();
            echo "  - Jobs in queue: {$jobsCount}\n";
        } catch (Exception $e) {
            echo "  ❌ Jobs table error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "  ⚠️  Using {$queueDriver} driver\n";
    }
    
    echo "\n";
    
    // Test 5: Test job processing with Laravel 12 syntax
    echo "Test 5: Job Processing Test\n";
    
    try {
        // Use Laravel 12 compatible queue:work command
        $output = [];
        $returnCode = 0;
        
        // Try the new Laravel 12 approach
        exec('php artisan queue:work --once --timeout=30 --tries=3 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "  ✅ Job processing works\n";
            echo "  - Output: " . implode("\n", $output) . "\n";
        } else {
            echo "  ❌ Job processing failed\n";
            echo "  - Return code: {$returnCode}\n";
            echo "  - Output: " . implode("\n", $output) . "\n";
        }
    } catch (Exception $e) {
        echo "  ❌ Job processing error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // Test 6: Check for Laravel 12 specific issues
    echo "Test 6: Laravel 12 Specific Checks\n";
    
    // Check if queue:restart works
    echo "  - Testing queue:restart...\n";
    try {
        \Artisan::call('queue:restart');
        echo "    ✅ queue:restart works\n";
    } catch (Exception $e) {
        echo "    ❌ queue:restart failed: " . $e->getMessage() . "\n";
    }
    
    // Check cache configuration for queue:restart
    $cacheDriver = config('cache.default');
    echo "  - Cache driver: {$cacheDriver}\n";
    
    if ($cacheDriver === 'file') {
        $cachePath = storage_path('framework/cache');
        if (is_writable($cachePath)) {
            echo "    ✅ Cache directory is writable\n";
        } else {
            echo "    ❌ Cache directory is not writable\n";
            echo "    - Fix: chmod -R 775 {$cachePath}\n";
        }
    }
    
    echo "\n";
    
    // Test 7: Create Laravel 12 compatible systemd service
    echo "Test 7: Laravel 12 Systemd Service\n";
    
    $currentDir = getcwd();
    $phpPath = PHP_BINARY;
    
    $serviceContent = "[Unit]
Description=Laravel 12 Queue Worker for Africa CDC APM
After=network.target

[Service]
Type=simple
User=andrew
Group=www-data
WorkingDirectory={$currentDir}
ExecStart={$phpPath} artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=60
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal
SyslogIdentifier=laravel-queue-apm

# Environment variables
Environment=APP_ENV=production
Environment=APP_DEBUG=false

# Resource limits
LimitNOFILE=65536
MemoryMax=512M

[Install]
WantedBy=multi-user.target";

    $serviceFile = '/tmp/laravel12-queue-apm.service';
    file_put_contents($serviceFile, $serviceContent);
    
    echo "  ✅ Laravel 12 compatible service file created: {$serviceFile}\n";
    echo "  - Install with: sudo cp {$serviceFile} /etc/systemd/system/laravel-queue-apm.service\n";
    echo "  - Then: sudo systemctl daemon-reload && sudo systemctl restart laravel-queue-apm.service\n";
    
    echo "\n";
    
    // Test 8: Process jobs with Laravel 12 approach
    echo "Test 8: Process Jobs with Laravel 12 Approach\n";
    
    $jobsCount = DB::table('jobs')->count();
    echo "  - Jobs to process: {$jobsCount}\n";
    
    if ($jobsCount > 0) {
        echo "  - Starting Laravel 12 queue worker...\n";
        
        // Use the new Laravel 12 queue:work approach
        $command = "php artisan queue:work --daemon --tries=3 --timeout=60 --max-time=3600";
        echo "  - Command: {$command}\n";
        
        // Start in background
        $pid = exec("nohup {$command} > /dev/null 2>&1 & echo $!");
        echo "  - Started with PID: {$pid}\n";
        
        // Wait a moment
        sleep(5);
        
        // Check if jobs are being processed
        $newJobsCount = DB::table('jobs')->count();
        if ($newJobsCount < $jobsCount) {
            echo "  ✅ Jobs are being processed ({$jobsCount} -> {$newJobsCount})\n";
        } else {
            echo "  ⚠️  Jobs may not be processing\n";
        }
    }
    
    echo "\n";
    
    // Recommendations
    echo "=== Laravel 12 Recommendations ===\n";
    echo "1. Use the new service file with proper timeout settings\n";
    echo "2. Ensure cache is properly configured for queue:restart\n";
    echo "3. Use --timeout=60 and --max-time=3600 for better job handling\n";
    echo "4. Monitor with: sudo journalctl -u laravel-queue-apm.service -f\n";
    echo "5. Restart workers after code changes: php artisan queue:restart\n";
    
    echo "\n=== Laravel 12 Fix Complete ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
