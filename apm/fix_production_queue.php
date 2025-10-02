<?php

/**
 * Production Queue Fix Script
 * Run this on your production server: php fix_production_queue.php
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== Production Queue Fix Script ===\n\n";

try {
    // Get all jobs from the queue
    $jobs = DB::table('jobs')->get();
    echo "Total jobs in queue: " . $jobs->count() . "\n\n";
    
    $problematicJobs = [];
    $validJobs = [];
    
    foreach ($jobs as $job) {
        try {
            // Try to unserialize the job payload
            $payload = json_decode($job->payload, true);
            
            if (isset($payload['data']['command'])) {
                // This is a job with a command
                $command = unserialize($payload['data']['command']);
                
                // Check if it's an AssignDocumentNumberJob
                if ($command instanceof \App\Jobs\AssignDocumentNumberJob) {
                    // Use reflection to get the model type and ID
                    $reflection = new ReflectionClass($command);
                    $modelTypeProperty = $reflection->getProperty('modelType');
                    $modelTypeProperty->setAccessible(true);
                    $modelIdProperty = $reflection->getProperty('modelId');
                    $modelIdProperty->setAccessible(true);
                    
                    $modelType = $modelTypeProperty->getValue($command);
                    $modelId = $modelIdProperty->getValue($command);
                    
                    // Check if the model exists
                    if (class_exists($modelType)) {
                        $model = $modelType::find($modelId);
                        if (!$model) {
                            echo "❌ Missing model: {$modelType} ID {$modelId}\n";
                            $problematicJobs[] = $job->id;
                        } else {
                            $validJobs[] = $job->id;
                        }
                    } else {
                        echo "❌ Invalid model class: {$modelType}\n";
                        $problematicJobs[] = $job->id;
                    }
                } else {
                    $validJobs[] = $job->id;
                }
            } else {
                $validJobs[] = $job->id;
            }
        } catch (Exception $e) {
            echo "❌ Error processing job {$job->id}: " . $e->getMessage() . "\n";
            $problematicJobs[] = $job->id;
        }
    }
    
    echo "\nSummary:\n";
    echo "  - Valid jobs: " . count($validJobs) . "\n";
    echo "  - Problematic jobs: " . count($problematicJobs) . "\n";
    
    if (count($problematicJobs) > 0) {
        echo "\nRemoving problematic jobs...\n";
        
        // Remove problematic jobs
        $deleted = DB::table('jobs')->whereIn('id', $problematicJobs)->delete();
        echo "✅ Removed {$deleted} problematic jobs\n";
        
        // Log the cleanup
        Log::info('Queue cleanup completed', [
            'removed_jobs' => $deleted,
            'remaining_jobs' => count($validJobs)
        ]);
    }
    
    echo "\n=== Queue cleanup completed ===\n";
    echo "You can now safely run: php artisan queue:retry all\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
