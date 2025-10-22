<?php

/**
 * Fix specific failing job and clean all problematic jobs
 * Run this on your production server: php fix_specific_job.php
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== Specific Job Fix Script ===\n\n";

try {
    // Step 1: Check current queue status
    $totalJobs = DB::table('jobs')->count();
    $failedJobs = DB::table('failed_jobs')->count();
    
    echo "Current Status:\n";
    echo "  - Jobs in queue: {$totalJobs}\n";
    echo "  - Failed jobs: {$failedJobs}\n\n";
    
    // Step 2: Remove the specific failing job if it exists
    $specificJobId = '0cbc3fe9-3463-40bd-855e-d3472904a53f';
    
    // Check if it's in the jobs table
    $jobInQueue = DB::table('jobs')->where('id', $specificJobId)->first();
    if ($jobInQueue) {
        DB::table('jobs')->where('id', $specificJobId)->delete();
        echo "✅ Removed specific job {$specificJobId} from queue\n";
    }
    
    // Check if it's in failed_jobs table
    $jobInFailed = DB::table('failed_jobs')->where('uuid', $specificJobId)->first();
    if ($jobInFailed) {
        DB::table('failed_jobs')->where('uuid', $specificJobId)->delete();
        echo "✅ Removed specific job {$specificJobId} from failed jobs\n";
    }
    
    if (!$jobInQueue && !$jobInFailed) {
        echo "⚠️  Job {$specificJobId} not found in queue or failed jobs\n";
    }
    
    echo "\n";
    
    // Step 3: Clean all AssignDocumentNumberJob jobs that might be problematic
    echo "Cleaning all AssignDocumentNumberJob jobs...\n";
    
    $jobs = DB::table('jobs')->get();
    $removedCount = 0;
    
    foreach ($jobs as $job) {
        try {
            $payload = json_decode($job->payload, true);
            
            if (isset($payload['data']['command'])) {
                $command = unserialize($payload['data']['command']);
                
                if ($command instanceof \App\Jobs\AssignDocumentNumberJob) {
                    // Remove all AssignDocumentNumberJob jobs to be safe
                    DB::table('jobs')->where('id', $job->id)->delete();
                    $removedCount++;
                    echo "  - Removed AssignDocumentNumberJob {$job->id}\n";
                }
            }
        } catch (Exception $e) {
            // If we can't unserialize, it's likely problematic - remove it
            DB::table('jobs')->where('id', $job->id)->delete();
            $removedCount++;
            echo "  - Removed problematic job {$job->id} (unserialize error)\n";
        }
    }
    
    echo "✅ Removed {$removedCount} potentially problematic jobs\n\n";
    
    // Step 4: Clear all failed jobs to start fresh
    echo "Clearing all failed jobs...\n";
    $failedCount = DB::table('failed_jobs')->count();
    DB::table('failed_jobs')->truncate();
    echo "✅ Cleared {$failedCount} failed jobs\n\n";
    
    // Step 5: Final status check
    $finalJobs = DB::table('jobs')->count();
    $finalFailed = DB::table('failed_jobs')->count();
    
    echo "Final Status:\n";
    echo "  - Jobs in queue: {$finalJobs}\n";
    echo "  - Failed jobs: {$finalFailed}\n\n";
    
    // Step 6: Test queue processing
    if ($finalJobs > 0) {
        echo "Testing queue processing...\n";
        echo "Run: php artisan queue:work --once --timeout=30\n";
        echo "This will process one job to verify everything is working.\n";
    } else {
        echo "✅ Queue is clean - no jobs to process\n";
    }
    
    echo "\n=== Fix completed ===\n";
    echo "You can now safely run:\n";
    echo "  - php artisan queue:retry all\n";
    echo "  - php artisan queue:work\n";
    
    // Log the cleanup
    Log::info('Queue cleanup completed - specific job fix', [
        'removed_jobs' => $removedCount,
        'cleared_failed' => $failedCount,
        'remaining_jobs' => $finalJobs,
        'specific_job_id' => $specificJobId
    ]);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
