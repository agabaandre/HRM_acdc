<?php

/**
 * Queue Job Management Script
 * Run this on your production server: php manage_queue_jobs.php [status|clear|process|retry]
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$action = $argv[1] ?? 'status';

echo "=== Queue Job Management ===\n\n";

try {
    switch ($action) {
        case 'status':
            echo "Current Queue Status:\n";
            
            $jobsCount = DB::table('jobs')->count();
            $failedJobsCount = DB::table('failed_jobs')->count();
            
            echo "  - Pending jobs: {$jobsCount}\n";
            echo "  - Failed jobs: {$failedJobsCount}\n";
            
            if ($jobsCount > 0) {
                echo "\nJob Types in Queue:\n";
                
                // Get job types
                $jobTypes = DB::table('jobs')
                    ->select(DB::raw('SUBSTRING_INDEX(SUBSTRING_INDEX(payload, \'"displayName":"\', -1), \'"\', 1) as job_type'))
                    ->selectRaw('COUNT(*) as count')
                    ->groupBy('job_type')
                    ->get();
                
                foreach ($jobTypes as $jobType) {
                    echo "  - {$jobType->job_type}: {$jobType->count} jobs\n";
                }
                
                // Show sample jobs
                echo "\nSample Jobs:\n";
                $sampleJobs = DB::table('jobs')
                    ->select('id', 'payload', 'created_at')
                    ->limit(3)
                    ->get();
                
                foreach ($sampleJobs as $job) {
                    $payload = json_decode($job->payload, true);
                    $jobName = $payload['displayName'] ?? 'Unknown';
                    $attempts = $payload['attempts'] ?? 0;
                    echo "  - ID: {$job->id} | Type: {$jobName} | Attempts: {$attempts} | Created: {$job->created_at}\n";
                }
            }
            break;
            
        case 'clear':
            echo "Clearing all jobs from queue...\n";
            
            $jobsCount = DB::table('jobs')->count();
            echo "  - Jobs to clear: {$jobsCount}\n";
            
            if ($jobsCount > 0) {
                DB::table('jobs')->truncate();
                echo "  ✅ All jobs cleared\n";
            } else {
                echo "  ⚠️  No jobs to clear\n";
            }
            break;
            
        case 'process':
            echo "Processing jobs...\n";
            
            $jobsCount = DB::table('jobs')->count();
            echo "  - Jobs to process: {$jobsCount}\n";
            
            if ($jobsCount > 0) {
                echo "  - Starting queue worker...\n";
                
                // Process jobs for a limited time
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
            } else {
                echo "  ⚠️  No jobs to process\n";
            }
            break;
            
        case 'retry':
            echo "Retrying failed jobs...\n";
            
            $failedJobsCount = DB::table('failed_jobs')->count();
            echo "  - Failed jobs to retry: {$failedJobsCount}\n";
            
            if ($failedJobsCount > 0) {
                $output = [];
                $returnCode = 0;
                exec('php artisan queue:retry all 2>&1', $output, $returnCode);
                
                if ($returnCode === 0) {
                    echo "  ✅ Failed jobs retried\n";
                    echo "  - Output: " . implode("\n", $output) . "\n";
                } else {
                    echo "  ❌ Failed to retry jobs\n";
                    echo "  - Return code: {$returnCode}\n";
                    echo "  - Output: " . implode("\n", $output) . "\n";
                }
            } else {
                echo "  ⚠️  No failed jobs to retry\n";
            }
            break;
            
        case 'clean':
            echo "Cleaning queue completely...\n";
            
            $jobsCount = DB::table('jobs')->count();
            $failedJobsCount = DB::table('failed_jobs')->count();
            
            echo "  - Pending jobs: {$jobsCount}\n";
            echo "  - Failed jobs: {$failedJobsCount}\n";
            
            // Clear all jobs
            DB::table('jobs')->truncate();
            DB::table('failed_jobs')->truncate();
            
            echo "  ✅ All jobs cleared\n";
            echo "  ✅ All failed jobs cleared\n";
            break;
            
        case 'monitor':
            echo "Monitoring queue (press Ctrl+C to stop)...\n";
            
            while (true) {
                $jobsCount = DB::table('jobs')->count();
                $failedJobsCount = DB::table('failed_jobs')->count();
                
                echo "[" . date('Y-m-d H:i:s') . "] Pending: {$jobsCount} | Failed: {$failedJobsCount}\n";
                
                sleep(5);
            }
            break;
            
        default:
            echo "Usage: php manage_queue_jobs.php [status|clear|process|retry|clean|monitor]\n";
            echo "\nCommands:\n";
            echo "  status   - Show current queue status\n";
            echo "  clear    - Clear all pending jobs\n";
            echo "  process  - Process one job\n";
            echo "  retry    - Retry all failed jobs\n";
            echo "  clean    - Clear all jobs and failed jobs\n";
            echo "  monitor  - Monitor queue in real-time\n";
            break;
    }
    
    echo "\n=== Management Complete ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
