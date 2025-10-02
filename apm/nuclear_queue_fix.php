<?php

/**
 * Nuclear Queue Fix - Clears everything and starts fresh
 * Use this if the specific job fix doesn't work
 * Run this on your production server: php nuclear_queue_fix.php
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== Nuclear Queue Fix Script ===\n";
echo "⚠️  WARNING: This will clear ALL jobs and failed jobs!\n\n";

// Ask for confirmation
echo "Are you sure you want to proceed? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim(strtolower($line)) !== 'yes') {
    echo "Operation cancelled.\n";
    exit(0);
}

try {
    echo "\nProceeding with nuclear fix...\n\n";
    
    // Step 1: Get counts before clearing
    $jobsCount = DB::table('jobs')->count();
    $failedCount = DB::table('failed_jobs')->count();
    
    echo "Before cleanup:\n";
    echo "  - Jobs in queue: {$jobsCount}\n";
    echo "  - Failed jobs: {$failedCount}\n\n";
    
    // Step 2: Clear everything
    echo "Clearing all jobs...\n";
    DB::table('jobs')->truncate();
    echo "✅ Cleared all jobs from queue\n";
    
    echo "Clearing all failed jobs...\n";
    DB::table('failed_jobs')->truncate();
    echo "✅ Cleared all failed jobs\n\n";
    
    // Step 3: Restart queue workers (if possible)
    echo "Restarting queue workers...\n";
    try {
        // This might not work in all environments, but it's worth trying
        exec('php artisan queue:restart 2>&1', $output, $returnCode);
        if ($returnCode === 0) {
            echo "✅ Queue workers restarted\n";
        } else {
            echo "⚠️  Could not restart queue workers automatically\n";
            echo "   Please run: php artisan queue:restart\n";
        }
    } catch (Exception $e) {
        echo "⚠️  Could not restart queue workers: " . $e->getMessage() . "\n";
        echo "   Please run: php artisan queue:restart\n";
    }
    
    echo "\n";
    
    // Step 4: Verify cleanup
    $finalJobs = DB::table('jobs')->count();
    $finalFailed = DB::table('failed_jobs')->count();
    
    echo "After cleanup:\n";
    echo "  - Jobs in queue: {$finalJobs}\n";
    echo "  - Failed jobs: {$finalFailed}\n\n";
    
    if ($finalJobs === 0 && $finalFailed === 0) {
        echo "✅ Queue is completely clean!\n\n";
    } else {
        echo "⚠️  Some jobs still remain - this might indicate a database issue\n";
    }
    
    // Step 5: Test with a simple job
    echo "Testing queue functionality...\n";
    try {
        // Create a simple test job
        \App\Jobs\AssignDocumentNumberJob::dispatch(
            \App\Models\Matrix::first(), // Use an existing model
            'test'
        );
        
        echo "✅ Test job created successfully\n";
        
        // Process it immediately
        exec('php artisan queue:work --once --timeout=10 2>&1', $testOutput, $testReturnCode);
        
        if ($testReturnCode === 0) {
            echo "✅ Test job processed successfully\n";
        } else {
            echo "⚠️  Test job failed to process\n";
            echo "Output: " . implode("\n", $testOutput) . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Test job failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== Nuclear fix completed ===\n";
    echo "The queue is now clean and ready for use.\n";
    echo "You can now run:\n";
    echo "  - php artisan queue:work\n";
    echo "  - php artisan queue:retry all (should work now)\n";
    
    // Log the nuclear cleanup
    Log::info('Nuclear queue cleanup completed', [
        'jobs_cleared' => $jobsCount,
        'failed_cleared' => $failedCount,
        'final_jobs' => $finalJobs,
        'final_failed' => $finalFailed
    ]);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
