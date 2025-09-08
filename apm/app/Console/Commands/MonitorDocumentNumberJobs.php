<?php

namespace App\Console\Commands;

use App\Jobs\AssignDocumentNumberJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class MonitorDocumentNumberJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:document-jobs {--watch : Watch mode - refresh every 5 seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor document number assignment jobs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isWatchMode = $this->option('watch');
        
        do {
            $this->displayJobStatus();
            
            if ($isWatchMode) {
                sleep(5);
                $this->output->write("\033[2J\033[H"); // Clear screen
            }
        } while ($isWatchMode);
        
        return 0;
    }
    
    private function displayJobStatus()
    {
        $this->info('Document Number Job Monitor');
        $this->info('==========================');
        $this->newLine();
        
        // Queue status
        $this->displayQueueStatus();
        $this->newLine();
        
        // Job statistics
        $this->displayJobStatistics();
        $this->newLine();
        
        // Recent jobs
        $this->displayRecentJobs();
        $this->newLine();
        
        // Documents without numbers
        $this->displayDocumentsWithoutNumbers();
    }
    
    private function displayQueueStatus()
    {
        $this->info('Queue Status:');
        
        try {
            $queueSize = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            
            $this->line("  Pending Jobs: {$queueSize}");
            $this->line("  Failed Jobs: {$failedJobs}");
            
            if ($queueSize > 0) {
                $this->warn("  ⚠️  There are {$queueSize} jobs waiting to be processed");
            } else {
                $this->info("  ✅ No pending jobs");
            }
            
            if ($failedJobs > 0) {
                $this->error("  ❌ {$failedJobs} jobs have failed");
            }
            
        } catch (\Exception $e) {
            $this->error("  ❌ Could not check queue status: " . $e->getMessage());
        }
    }
    
    private function displayJobStatistics()
    {
        $this->info('Job Statistics (Last 24 hours):');
        
        try {
            $stats = DB::table('jobs')
                ->where('created_at', '>=', now()->subDay())
                ->where('payload', 'like', '%AssignDocumentNumberJob%')
                ->selectRaw('COUNT(*) as total_jobs')
                ->first();
                
            $totalJobs = $stats ? $stats->total_jobs : 0;
            $this->line("  Document Number Jobs Created: {$totalJobs}");
            
            // Check for recent failures
            $recentFailures = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subHour())
                ->where('payload', 'like', '%AssignDocumentNumberJob%')
                ->count();
                
            if ($recentFailures > 0) {
                $this->error("  Recent Failures: {$recentFailures}");
            } else {
                $this->info("  Recent Failures: 0");
            }
            
        } catch (\Exception $e) {
            $this->error("  ❌ Could not get job statistics: " . $e->getMessage());
        }
    }
    
    private function displayRecentJobs()
    {
        $this->info('Recent Jobs:');
        
        try {
            $recentJobs = DB::table('jobs')
                ->where('payload', 'like', '%AssignDocumentNumberJob%')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'created_at', 'attempts']);
                
            if ($recentJobs->isEmpty()) {
                $this->line("  No recent jobs found");
                return;
            }
            
            foreach ($recentJobs as $job) {
                $timeAgo = $job->created_at ? now()->diffForHumans($job->created_at) : 'Unknown';
                $this->line("  Job #{$job->id} - Created: {$timeAgo} - Attempts: {$job->attempts}");
            }
            
        } catch (\Exception $e) {
            $this->error("  ❌ Could not get recent jobs: " . $e->getMessage());
        }
    }
    
    private function displayDocumentsWithoutNumbers()
    {
        $this->info('Documents Without Numbers:');
        
        $tables = [
            'matrices' => 'Matrix',
            'activities' => 'Activity', 
            'non_travel_memos' => 'NonTravelMemo',
            'special_memos' => 'SpecialMemo',
            'service_requests' => 'ServiceRequest',
            'request_arfs' => 'RequestARF'
        ];
        
        $totalWithoutNumbers = 0;
        
        foreach ($tables as $table => $model) {
            try {
                $count = DB::table($table)->whereNull('document_number')->count();
                $totalWithoutNumbers += $count;
                
                if ($count > 0) {
                    $this->warn("  {$model}: {$count} records without document numbers");
                } else {
                    $this->info("  {$model}: ✅ All records have document numbers");
                }
            } catch (\Exception $e) {
                $this->error("  {$model}: ❌ Could not check - " . $e->getMessage());
            }
        }
        
        if ($totalWithoutNumbers > 0) {
            $this->newLine();
            $this->warn("⚠️  Total: {$totalWithoutNumbers} records need document numbers");
            $this->info("Run: php artisan assign:document-numbers to assign them");
        } else {
            $this->newLine();
            $this->info("✅ All documents have document numbers!");
        }
    }
}