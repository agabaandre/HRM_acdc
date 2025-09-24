<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\ApprovalTrail;
use App\Models\ActivityApprovalTrail;
use App\Models\Matrix;
use Carbon\Carbon;

class ArchiveOldApprovalTrailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $excludeMatrixId;
    protected $daysOld;
    protected $dryRun;

    /**
     * Create a new job instance.
     *
     * @param int|null $excludeMatrixId Matrix ID to exclude from archiving
     * @param int $daysOld Number of days old to consider for archiving (default: 30)
     * @param bool $dryRun Whether to perform a dry run (default: false)
     * 
     * Note: This job archives ALL approval trails regardless of approval order and overall status
     */
    public function __construct(?int $excludeMatrixId = null, int $daysOld = 30, bool $dryRun = false)
    {
        $this->excludeMatrixId = $excludeMatrixId;
        $this->daysOld = $daysOld;
        $this->dryRun = $dryRun;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $cutoffDate = Carbon::now()->subDays($this->daysOld);
            
            Log::info('Starting ArchiveOldApprovalTrailsJob', [
                'exclude_matrix_id' => $this->excludeMatrixId,
                'days_old' => $this->daysOld,
                'cutoff_date' => $cutoffDate->toDateString(),
                'dry_run' => $this->dryRun
            ]);

            $results = [
                'matrix_approval_trails' => 0,
                'activity_approval_trails' => 0,
                'excluded_matrix_trails' => 0,
                'excluded_activity_trails' => 0,
                'total_archived' => 0
            ];

            // Archive matrix approval trails (all regardless of approval order and status)
            $matrixQuery = ApprovalTrail::where('model_type', 'App\Models\Matrix')
                ->where('is_archived', 0)
                ->where('created_at', '<', $cutoffDate);

            // Exclude specific matrix if provided
            if ($this->excludeMatrixId) {
                $excludedMatrixTrails = $matrixQuery->where('model_id', $this->excludeMatrixId)->count();
                $results['excluded_matrix_trails'] = $excludedMatrixTrails;
                
                $matrixQuery->where('model_id', '!=', $this->excludeMatrixId);
            }

            $matrixTrailsToArchive = $matrixQuery->count();
            $results['matrix_approval_trails'] = $matrixTrailsToArchive;

            if (!$this->dryRun && $matrixTrailsToArchive > 0) {
                $matrixQuery->update(['is_archived' => 1]);
            }

            // Archive activity approval trails (all regardless of approval order and status)
            $activityQuery = ActivityApprovalTrail::where('is_archived', 0)
                ->where('created_at', '<', $cutoffDate);

            // Exclude activities from specific matrix if provided
            if ($this->excludeMatrixId) {
                $excludedActivityTrails = $activityQuery->where('matrix_id', $this->excludeMatrixId)->count();
                $results['excluded_activity_trails'] = $excludedActivityTrails;
                
                $activityQuery->where('matrix_id', '!=', $this->excludeMatrixId);
            }

            $activityTrailsToArchive = $activityQuery->count();
            $results['activity_approval_trails'] = $activityTrailsToArchive;

            if (!$this->dryRun && $activityTrailsToArchive > 0) {
                $activityQuery->update(['is_archived' => 1]);
            }

            $results['total_archived'] = $results['matrix_approval_trails'] + $results['activity_approval_trails'];

            // Log results
            if ($this->dryRun) {
                Log::info('ArchiveOldApprovalTrailsJob DRY RUN completed', $results);
            } else {
                Log::info('ArchiveOldApprovalTrailsJob completed successfully', $results);
            }

            // Log summary
            $this->logSummary($results);

        } catch (\Exception $e) {
            Log::error('ArchiveOldApprovalTrailsJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'exclude_matrix_id' => $this->excludeMatrixId,
                'days_old' => $this->daysOld
            ]);
            
            throw $e;
        }
    }

    /**
     * Log a summary of the archiving results.
     */
    private function logSummary(array $results): void
    {
        $action = $this->dryRun ? 'WOULD ARCHIVE' : 'ARCHIVED';
        
        Log::info("=== APPROVAL TRAIL ARCHIVING SUMMARY ===");
        Log::info("Action: {$action}");
        Log::info("Matrix Approval Trails: {$results['matrix_approval_trails']}");
        Log::info("Activity Approval Trails: {$results['activity_approval_trails']}");
        Log::info("Total Trails: {$results['total_archived']}");
        
        if ($this->excludeMatrixId) {
            Log::info("Excluded Matrix ID: {$this->excludeMatrixId}");
            Log::info("Excluded Matrix Trails: {$results['excluded_matrix_trails']}");
            Log::info("Excluded Activity Trails: {$results['excluded_activity_trails']}");
        }
        
        Log::info("Cutoff Date: " . Carbon::now()->subDays($this->daysOld)->toDateString());
        Log::info("=======================================");
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'archive-approval-trails',
            'maintenance',
            'matrix-id-' . ($this->excludeMatrixId ?? 'all')
        ];
    }
}
