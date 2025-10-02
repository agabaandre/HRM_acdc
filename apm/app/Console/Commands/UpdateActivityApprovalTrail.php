<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Activity;
use App\Models\ActivityApprovalTrail;
use App\Models\Staff;
use Carbon\Carbon;

class UpdateActivityApprovalTrail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activity:update-approval-trail 
                            {activities : Comma-separated list of activity IDs (use "all" for all activities in matrix)}
                            {matrix_id : Matrix ID for the activities}
                            {approver_id : Staff ID of the approver}
                            {action : Action taken (approved, rejected, returned, passed)}
                            {--comments= : Optional comments for the approval trail}
                            {--force : Force update even if activity is already approved}
                            {--approval-order= : Approval order for workflow (required when using "all")}
                            {--exclude-single-memos : Exclude single memos when processing all activities}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update approval trail for multiple activities with a specific approver';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $activityIds = $this->argument('activities');
        $matrixId = $this->argument('matrix_id');
        $approverId = $this->argument('approver_id');
        $action = $this->argument('action');
        $comments = $this->option('comments');
        $force = $this->option('force');

        // Validate matrix exists
        $matrix = \App\Models\Matrix::find($matrixId);
        if (!$matrix) {
            $this->error("Matrix with ID {$matrixId} not found.");
            return 1;
        }

        // Validate approver exists
        $approver = Staff::find($approverId);
        if (!$approver) {
            $this->error("Staff with ID {$approverId} not found.");
            return 1;
        }

        // Parse activity IDs or get all activities from matrix
        if (strtolower($activityIds) === 'all') {
            $approvalOrder = $this->option('approval-order');
            $excludeSingleMemos = $this->option('exclude-single-memos');
            
            if (!$approvalOrder) {
                $this->error("Approval order is required when using 'all' activities. Use --approval-order option.");
                return 1;
            }

            // Get all activities from the matrix
            $query = Activity::where('matrix_id', $matrixId);
            
            if ($excludeSingleMemos) {
                $query->where('is_single_memo', 0);
            }
            
            $activities = $query->get();
            $activityIdArray = $activities->pluck('id')->toArray();
            
            if (empty($activityIdArray)) {
                $this->error("No activities found in matrix {$matrixId}.");
                return 1;
            }
            
            $this->info("Found " . count($activityIdArray) . " activities in matrix {$matrixId}");
            if ($excludeSingleMemos) {
                $this->info("Excluding single memos as requested");
            }
        } else {
            $activityIdArray = array_map('trim', explode(',', $activityIds));
            $activityIdArray = array_filter($activityIdArray, 'is_numeric');

            if (empty($activityIdArray)) {
                $this->error("No valid activity IDs provided.");
                return 1;
            }
        }

        // Validate action
        $validActions = ['approved', 'rejected', 'returned', 'passed'];
        if (!in_array(strtolower($action), $validActions)) {
            $this->error("Invalid action. Must be one of: " . implode(', ', $validActions));
            return 1;
        }

        $this->info("Processing " . count($activityIdArray) . " activities...");
        $this->info("Matrix: {$matrix->title} (ID: {$matrixId})");
        $this->info("Approver: {$approver->fname} {$approver->lname} (ID: {$approverId})");
        $this->info("Action: " . ucfirst($action));
        $this->newLine();

        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        foreach ($activityIdArray as $activityId) {
            try {
                $activity = Activity::find($activityId);
                
                if (!$activity) {
                    $this->error("Activity ID {$activityId}: Not found");
                    $errorCount++;
                    continue;
                }

                // Check if activity is already approved (unless force is used)
                if (!$force && $activity->overall_status === 'approved') {
                    $this->warn("Activity ID {$activityId}: Already approved (use --force to override)");
                    $skippedCount++;
                    continue;
                }

                // Get approval order if provided
                $approvalOrder = $this->option('approval-order');
                
                // Create approval trail entry
                $approvalTrailData = [
                    'matrix_id' => $matrixId,
                    'activity_id' => $activity->id,
                    'staff_id' => $approverId,
                    'action' => strtolower($action),
                    'remarks' => $comments ?: "Updated via command line",
                    'is_archived' => 0,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
                
                if ($approvalOrder) {
                    $approvalTrailData['approval_order'] = $approvalOrder;
                }
                
                $approvalTrail = ActivityApprovalTrail::create($approvalTrailData);

                // Update activity status based on action
                $this->updateActivityStatus($activity, strtolower($action));

                $this->info("✓ Activity ID {$activityId}: Updated successfully");
                $successCount++;

            } catch (\Exception $e) {
                $this->error("Activity ID {$activityId}: Error - " . $e->getMessage());
                $errorCount++;
            }
        }

        $this->newLine();
        $this->info("=== Summary ===");
        $this->info("Successfully updated: {$successCount}");
        $this->info("Errors: {$errorCount}");
        $this->info("Skipped: {$skippedCount}");

        return 0;
    }

    /**
     * Update activity status based on the action
     */
    private function updateActivityStatus(Activity $activity, string $action)
    {
        switch ($action) {
            case 'approved':
                $activity->update(['overall_status' => 'approved']);
                break;
            case 'rejected':
                $activity->update(['overall_status' => 'rejected']);
                break;
            case 'returned':
                $activity->update(['overall_status' => 'returned']);
                break;
            case 'passed':
                // For 'passed', we don't change the overall status
                // This is typically used for intermediate approval steps
                break;
        }
    }
}