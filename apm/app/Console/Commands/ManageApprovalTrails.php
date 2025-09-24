<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ArchiveOldApprovalTrailsJob;
use App\Models\ApprovalTrail;
use App\Models\ActivityApprovalTrail;
use App\Models\Matrix;
use Carbon\Carbon;

class ManageApprovalTrails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'approval:manage-trails 
                            {action : Action to perform (stats|archive|cleanup)}
                            {--matrix-id= : Matrix ID to exclude from archiving (use "all" to archive all matrices)}
                            {--days=30 : Number of days old to consider for archiving}
                            {--dry-run : Perform a dry run without actually archiving}
                            {--queue : Dispatch to queue instead of running immediately}
                            {--force : Force operation without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage approval trails - show statistics, archive old trails (ALL regardless of approval order and status), or cleanup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'stats':
                return $this->showStats();
            case 'archive':
                return $this->archiveTrails();
            case 'cleanup':
                return $this->cleanupTrails();
            default:
                $this->error("Invalid action: {$action}. Use: stats, archive, or cleanup");
                return 1;
        }
    }

    /**
     * Show approval trail statistics.
     */
    private function showStats(): int
    {
        $this->info('=== Approval Trail Statistics ===');
        
        // Matrix approval trails
        $matrixTotal = ApprovalTrail::where('model_type', 'App\Models\Matrix')->count();
        $matrixActive = ApprovalTrail::where('model_type', 'App\Models\Matrix')->where('is_archived', 0)->count();
        $matrixArchived = ApprovalTrail::where('model_type', 'App\Models\Matrix')->where('is_archived', 1)->count();
        
        // Activity approval trails
        $activityTotal = ActivityApprovalTrail::count();
        $activityActive = ActivityApprovalTrail::where('is_archived', 0)->count();
        $activityArchived = ActivityApprovalTrail::where('is_archived', 1)->count();
        
        // Recent trails (last 30 days)
        $recentDate = Carbon::now()->subDays(30);
        $matrixRecent = ApprovalTrail::where('model_type', 'App\Models\Matrix')
            ->where('created_at', '>=', $recentDate)->count();
        $activityRecent = ActivityApprovalTrail::where('created_at', '>=', $recentDate)->count();
        
        // Old trails (older than 30 days)
        $matrixOld = ApprovalTrail::where('model_type', 'App\Models\Matrix')
            ->where('created_at', '<', $recentDate)
            ->where('is_archived', 0)->count();
        $activityOld = ActivityApprovalTrail::where('created_at', '<', $recentDate)
            ->where('is_archived', 0)->count();
        
        $this->table(
            ['Type', 'Total', 'Active', 'Archived', 'Recent (30d)', 'Old (30d+)'],
            [
                ['Matrix Trails', $matrixTotal, $matrixActive, $matrixArchived, $matrixRecent, $matrixOld],
                ['Activity Trails', $activityTotal, $activityActive, $activityArchived, $activityRecent, $activityOld],
                ['TOTAL', $matrixTotal + $activityTotal, $matrixActive + $activityActive, $matrixArchived + $activityArchived, $matrixRecent + $activityRecent, $matrixOld + $activityOld]
            ]
        );
        
        // Show top matrices by trail count
        $this->line('');
        $this->info('Top 10 Matrices by Approval Trail Count:');
        $topMatrices = ApprovalTrail::where('model_type', 'App\Models\Matrix')
            ->selectRaw('model_id, COUNT(*) as trail_count')
            ->groupBy('model_id')
            ->orderByDesc('trail_count')
            ->limit(10)
            ->get();
            
        if ($topMatrices->count() > 0) {
            $this->table(
                ['Matrix ID', 'Trail Count'],
                $topMatrices->map(function ($item) {
                    return [$item->model_id, $item->trail_count];
                })->toArray()
            );
        }
        
        return 0;
    }

    /**
     * Archive old approval trails.
     */
    private function archiveTrails(): int
    {
        $matrixIdOption = $this->option('matrix-id');
        $excludeMatrixId = null;
        
        // Handle matrix-id option
        if ($matrixIdOption) {
            if (strtolower($matrixIdOption) === 'all') {
                $excludeMatrixId = null; // Archive all matrices
            } else {
                $excludeMatrixId = (int) $matrixIdOption;
                if ($excludeMatrixId < 1) {
                    $this->error('Matrix ID must be a positive integer or "all"');
                    return 1;
                }
            }
        }
        
        $daysOld = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $useQueue = $this->option('queue');
        $force = $this->option('force');

        // Validate inputs
        if ($daysOld < 1) {
            $this->error('Days must be at least 1');
            return 1;
        }

        // Show what will be archived
        $cutoffDate = Carbon::now()->subDays($daysOld);
        $this->info('=== Archiving Configuration ===');
        if ($matrixIdOption) {
            if (strtolower($matrixIdOption) === 'all') {
                $this->line("Archive Mode: ALL matrices (no exclusions)");
            } else {
                $this->line("Archive Mode: Exclude Matrix ID {$excludeMatrixId}");
            }
        } else {
            $this->line("Archive Mode: ALL matrices (no exclusions)");
        }
        $this->line("Days Old: {$daysOld}");
        $this->line("Cutoff Date: " . $cutoffDate->toDateString());
        $this->line("Dry Run: " . ($dryRun ? 'Yes' : 'No'));
        $this->line("Use Queue: " . ($useQueue ? 'Yes' : 'No'));
        $this->line('===============================');

        // Count what will be affected
        $matrixQuery = ApprovalTrail::where('model_type', 'App\Models\Matrix')
            ->where('is_archived', 0)
            ->where('created_at', '<', $cutoffDate);
            
        if ($excludeMatrixId) {
            $matrixQuery->where('model_id', '!=', $excludeMatrixId);
        }
        $matrixCount = $matrixQuery->count();

        $activityQuery = ActivityApprovalTrail::where('is_archived', 0)
            ->where('created_at', '<', $cutoffDate);
            
        if ($excludeMatrixId) {
            $activityQuery->where('matrix_id', '!=', $excludeMatrixId);
        }
        $activityCount = $activityQuery->count();

        $this->info("Will archive {$matrixCount} matrix trails and {$activityCount} activity trails");
        $this->line("Total: " . ($matrixCount + $activityCount) . " trails");
        
        $this->warn('⚠️  WARNING: This will archive ALL approval trails older than ' . $daysOld . ' days, regardless of approval order and status!');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No trails will actually be archived');
        }

        // Confirm before proceeding
        if (!$dryRun && !$force && !$this->confirm('Do you want to proceed with archiving?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        try {
            if ($useQueue) {
                // Dispatch to queue
                ArchiveOldApprovalTrailsJob::dispatch($excludeMatrixId, $daysOld, $dryRun);
                $this->info('Job dispatched to queue successfully!');
                $this->line('Check the logs for job execution details.');
            } else {
                // Run immediately
                $this->info('Running archiving job...');
                $job = new ArchiveOldApprovalTrailsJob($excludeMatrixId, $daysOld, $dryRun);
                $job->handle();
                $this->info('Archiving job completed!');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('Job failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Cleanup old archived trails (permanently delete them).
     */
    private function cleanupTrails(): int
    {
        $daysOld = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($daysOld < 90) {
            $this->error('Cleanup requires at least 90 days old for safety');
            return 1;
        }

        $cutoffDate = Carbon::now()->subDays($daysOld);
        
        $this->warn('=== CLEANUP MODE ===');
        $this->warn('This will PERMANENTLY DELETE archived trails older than ' . $daysOld . ' days');
        $this->line("Cutoff Date: " . $cutoffDate->toDateString());
        $this->line("Dry Run: " . ($dryRun ? 'Yes' : 'No'));
        $this->warn('========================');

        // Count what will be deleted
        $matrixCount = ApprovalTrail::where('model_type', 'App\Models\Matrix')
            ->where('is_archived', 1)
            ->where('created_at', '<', $cutoffDate)
            ->count();
            
        $activityCount = ActivityApprovalTrail::where('is_archived', 1)
            ->where('created_at', '<', $cutoffDate)
            ->count();

        $this->warn("Will DELETE {$matrixCount} archived matrix trails and {$activityCount} archived activity trails");
        $this->warn("Total: " . ($matrixCount + $activityCount) . " trails will be PERMANENTLY DELETED");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No trails will actually be deleted');
        }

        // Double confirmation for cleanup
        if (!$dryRun && !$force) {
            if (!$this->confirm('Are you absolutely sure you want to PERMANENTLY DELETE these trails?')) {
                $this->info('Cleanup cancelled.');
                return 0;
            }
            
            if (!$this->confirm('This action cannot be undone. Type "DELETE" to confirm')) {
                $this->info('Cleanup cancelled.');
                return 0;
            }
        }

        try {
            if ($dryRun) {
                $this->info('DRY RUN: Would delete ' . ($matrixCount + $activityCount) . ' archived trails');
                return 0;
            }

            // Delete old archived trails
            $deletedMatrix = ApprovalTrail::where('model_type', 'App\Models\Matrix')
                ->where('is_archived', 1)
                ->where('created_at', '<', $cutoffDate)
                ->delete();
                
            $deletedActivity = ActivityApprovalTrail::where('is_archived', 1)
                ->where('created_at', '<', $cutoffDate)
                ->delete();

            $this->info("Cleanup completed!");
            $this->line("Deleted {$deletedMatrix} matrix trails and {$deletedActivity} activity trails");
            $this->line("Total deleted: " . ($deletedMatrix + $deletedActivity) . " trails");

            return 0;

        } catch (\Exception $e) {
            $this->error('Cleanup failed: ' . $e->getMessage());
            return 1;
        }
    }
}
