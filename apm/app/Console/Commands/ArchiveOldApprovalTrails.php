<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ArchiveOldApprovalTrailsJob;
use Carbon\Carbon;

class ArchiveOldApprovalTrails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'approval:archive-trails 
                            {--matrix-id= : Matrix ID to exclude from archiving (use "all" to archive all matrices)}
                            {--days=30 : Number of days old to consider for archiving}
                            {--dry-run : Perform a dry run without actually archiving}
                            {--queue : Dispatch to queue instead of running immediately}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive old approval trails for matrices and activities (ALL trails regardless of approval order and status), excluding specified matrix ID';

    /**
     * Execute the console command.
     */
    public function handle()
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

        // Validate inputs
        if ($daysOld < 1) {
            $this->error('Days must be at least 1');
            return 1;
        }

        // Show configuration
        $this->info('=== Approval Trail Archiving Configuration ===');
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
        $this->line("Cutoff Date: " . Carbon::now()->subDays($daysOld)->toDateString());
        $this->line("Dry Run: " . ($dryRun ? 'Yes' : 'No'));
        $this->line("Use Queue: " . ($useQueue ? 'Yes' : 'No'));
        $this->line('===============================================');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No trails will actually be archived');
        }
        
        $this->warn('⚠️  WARNING: This will archive ALL approval trails older than ' . $daysOld . ' days, regardless of approval order and status!');

        // Confirm before proceeding
        if (!$dryRun && !$this->confirm('Do you want to proceed with archiving?')) {
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
}
