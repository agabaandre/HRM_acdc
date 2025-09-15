<?php

namespace App\Services;

use App\Models\Matrix;
use App\Models\Activity;
use App\Models\SpecialMemo;
use App\Models\NonTravelMemo;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowModel;
use App\Models\Approver;
use App\Models\Staff;
use App\Models\Division;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class PendingApprovalsService
{
    protected $currentStaffId;
    protected $currentDivisionId;
    protected $userPermissions;
    protected $sessionData;

    public function __construct(?array $sessionData = null)
    {
        $this->sessionData = $sessionData ?? [
            'staff_id' => user_session('staff_id'),
            'division_id' => user_session('division_id'),
            'permissions' => user_session('permissions', []),
            'name' => user_session('name'),
            'email' => user_session('email'),
            'base_url' => user_session('base_url')
        ];
        
        $this->currentStaffId = $this->sessionData['staff_id'];
        $this->currentDivisionId = $this->sessionData['division_id'];
        $this->userPermissions = $this->sessionData['permissions'];
    }

    /**
     * Get all pending approval items for the current user
     */
    public function getPendingApprovals(): array
    {
        $pendingItems = collect();

        // Get pending matrices
        $pendingItems = $pendingItems->merge($this->getPendingMatrices());

        // Get pending special memos
        $pendingItems = $pendingItems->merge($this->getPendingSpecialMemos());

        // Get pending non-travel memos
        $pendingItems = $pendingItems->merge($this->getPendingNonTravelMemos());

        // Get pending single memos (activities with is_single_memo = true)
        $pendingItems = $pendingItems->merge($this->getPendingSingleMemos());

        // Group by category and sort by date received
        return $this->groupByCategory($pendingItems);
    }

    /**
     * Get pending matrices
     */
    protected function getPendingMatrices(): Collection
    {
        $query = Matrix::with(['division', 'focalPerson', 'matrixApprovalTrails.staff'])
            ->where('overall_status', 'pending');

        // Get all approval levels for this user (both division-specific and non-division-specific)
        $approvalLevels = $this->getUserApprovalLevels('Matrix');
        
        if (!empty($approvalLevels)) {
            $query->whereIn('approval_level', $approvalLevels);
        } else {
            // If no approval levels, return empty collection
            return collect();
        }

        // For division-specific approvers, only show items from their division
        if ($this->isDivisionSpecificApprover()) {
            $query->where('division_id', $this->currentDivisionId);
        }

        return $query->get()->filter(function ($matrix) {
            // Check if the current user is actually the current approver for this item
            return $this->isCurrentApprover($matrix);
        })->map(function ($matrix) {
            return $this->formatPendingItem($matrix, 'Matrix', [
                'title' => "Matrix - {$matrix->quarter} {$matrix->year}",
                'division' => $matrix->division->division_name ?? 'N/A',
                'submitted_by' => $matrix->focalPerson->fname . ' ' . $matrix->focalPerson->lname ?? 'N/A',
                'date_received' => $this->getDateReceivedToCurrentLevel($matrix),
                'view_url' => route('matrices.show', $matrix),
                'approval_level' => $matrix->approval_level,
                'workflow_role' => $this->getWorkflowRole($matrix),
                'item_id' => $matrix->id,
                'item_type' => 'Matrix'
            ]);
        });
    }

    /**
     * Get pending special memos
     */
    protected function getPendingSpecialMemos(): Collection
    {
        $query = SpecialMemo::with(['staff', 'division', 'approvalTrails.staff'])
            ->where('overall_status', 'pending');

        // Get all approval levels for this user (both division-specific and non-division-specific)
        $approvalLevels = $this->getUserApprovalLevels('SpecialMemo');
        
        if (!empty($approvalLevels)) {
            $query->whereIn('approval_level', $approvalLevels);
        } else {
            // If no approval levels, return empty collection
            return collect();
        }

        // For division-specific approvers, only show items from their division
        if ($this->isDivisionSpecificApprover()) {
            $query->where('division_id', $this->currentDivisionId);
        }

        return $query->get()->filter(function ($memo) {
            // Check if the current user is actually the current approver for this item
            return $this->isCurrentApprover($memo);
        })->map(function ($memo) {
            return $this->formatPendingItem($memo, 'Special Memo', [
                'title' => $memo->activity_title ?? 'Special Memo',
                'division' => $memo->division->division_name ?? 'N/A',
                'submitted_by' => $memo->staff->fname . ' ' . $memo->staff->lname ?? 'N/A',
                'date_received' => $this->getDateReceivedToCurrentLevel($memo),
                'view_url' => route('special-memo.show', $memo),
                'approval_level' => $memo->approval_level,
                'workflow_role' => $this->getWorkflowRole($memo),
                'item_id' => $memo->id,
                'item_type' => 'SpecialMemo'
            ]);
        });
    }

    /**
     * Get pending non-travel memos
     */
    protected function getPendingNonTravelMemos(): Collection
    {
        $query = NonTravelMemo::with(['staff', 'division', 'approvalTrails.staff'])
            ->where('overall_status', 'pending');

        // Get all approval levels for this user (both division-specific and non-division-specific)
        $approvalLevels = $this->getUserApprovalLevels('NonTravelMemo');
        
        if (!empty($approvalLevels)) {
            $query->whereIn('approval_level', $approvalLevels);
        } else {
            // If no approval levels, return empty collection
            return collect();
        }

        // For division-specific approvers, only show items from their division
        if ($this->isDivisionSpecificApprover()) {
            $query->where('division_id', $this->currentDivisionId);
        }

        return $query->get()->filter(function ($memo) {
            // Check if the current user is actually the current approver for this item
            return $this->isCurrentApprover($memo);
        })->map(function ($memo) {
            return $this->formatPendingItem($memo, 'Non-Travel Memo', [
                'title' => $memo->activity_title ?? 'Non-Travel Memo',
                'division' => $memo->division->division_name ?? 'N/A',
                'submitted_by' => $memo->staff->fname . ' ' . $memo->staff->lname ?? 'N/A',
                'date_received' => $this->getDateReceivedToCurrentLevel($memo),
                'view_url' => route('non-travel.show', $memo),
                'approval_level' => $memo->approval_level,
                'workflow_role' => $this->getWorkflowRole($memo),
                'item_id' => $memo->id,
                'item_type' => 'NonTravelMemo'
            ]);
        });
    }

    /**
     * Get pending single memos (activities with is_single_memo = true)
     */
    protected function getPendingSingleMemos(): Collection
    {
        $query = Activity::with(['staff', 'division', 'approvalTrails.staff'])
            ->where('is_single_memo', true)
            ->where('overall_status', 'pending');

        // Get all approval levels for this user (both division-specific and non-division-specific)
        $approvalLevels = $this->getUserApprovalLevels('Activity');
        
        if (!empty($approvalLevels)) {
            $query->whereIn('approval_level', $approvalLevels);
        } else {
            // If no approval levels, return empty collection
            return collect();
        }

        // For division-specific approvers, only show items from their division
        if ($this->isDivisionSpecificApprover()) {
            $query->where('division_id', $this->currentDivisionId);
        }

        return $query->get()->filter(function ($activity) {
            // Check if the current user is actually the current approver for this item
            return $this->isCurrentApprover($activity);
        })->map(function ($activity) {
            return $this->formatPendingItem($activity, 'Single Memo', [
                'title' => $activity->activity_title ?? 'Single Memo',
                'division' => $activity->division->division_name ?? 'N/A',
                'submitted_by' => $activity->staff->fname . ' ' . $activity->staff->lname ?? 'N/A',
                'date_received' => $this->getDateReceivedToCurrentLevel($activity),
                'view_url' => route('activities.single-memos.show', $activity),
                'approval_level' => $activity->approval_level,
                'workflow_role' => $this->getWorkflowRole($activity),
                'item_id' => $activity->id,
                'item_type' => 'Activity'
            ]);
        });
    }

    /**
     * Check if the current user is a division-specific approver (defined in division table)
     */
    protected function isDivisionSpecificApprover(): bool
    {
        if (!$this->currentStaffId || !$this->currentDivisionId) {
            return false;
        }

        // Check if user is a division approver according to the Division table
        $division = Division::find($this->currentDivisionId);
        if (!$division) {
            return false;
        }

        // Check if user is division head, focal person, admin assistant, or finance officer
        $primaryApprovers = [
            $division->division_head,
            $division->focal_person,
            $division->admin_assistant,
            $division->finance_officer
        ];
        
        if (in_array($this->currentStaffId, $primaryApprovers)) {
            return true;
        }
        
        // Also check if user is an active OIC for any of these roles
        $today = Carbon::today();
        
        // Check head OIC
        if ($division->head_oic_id == $this->currentStaffId) {
            $isOicActive = true;
            if ($division->head_oic_start_date) {
                $isOicActive = $isOicActive && $division->head_oic_start_date <= $today;
            }
            if ($division->head_oic_end_date) {
                $isOicActive = $isOicActive && $division->head_oic_end_date >= $today;
            }
            if ($isOicActive) {
                return true;
            }
        }
        
        // Check director OIC
        if ($division->director_oic_id == $this->currentStaffId) {
            $isOicActive = true;
            if ($division->director_oic_start_date) {
                $isOicActive = $isOicActive && $division->director_oic_start_date <= $today;
            }
            if ($division->director_oic_end_date) {
                $isOicActive = $isOicActive && $division->director_oic_end_date >= $today;
            }
            if ($isOicActive) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get approval levels the current user can approve
     */
    protected function getUserApprovalLevels(string $modelType): array
    {
        // Get workflow ID for this model type from WorkflowModel table
        $workflowId = \App\Models\WorkflowModel::getWorkflowIdForModel($modelType);
        
        if (!$workflowId) {
            return []; // No workflow assigned to this model type
        }

        $approvalLevels = [];

        // 1. Get approval levels from approvers table (non-division-specific)
        $approvers = Approver::where('staff_id', $this->currentStaffId)->get();
        $workflowDfnIds = $approvers->pluck('workflow_dfn_id')->toArray();
        
        if (!empty($workflowDfnIds)) {
            $workflowDefinitions = WorkflowDefinition::whereIn('id', $workflowDfnIds)
                ->where('workflow_id', $workflowId)
                ->where('is_division_specific', 0) // Only non-division-specific
                ->get();
            
            $approvalLevels = array_merge($approvalLevels, $workflowDefinitions->pluck('approval_order')->toArray());
        }

        // 2. If user is division-specific approver, also get division-specific levels
        if ($this->isDivisionSpecificApprover()) {
            $divisionLevels = $this->getDivisionSpecificApprovalLevels($modelType);
            $approvalLevels = array_merge($approvalLevels, $divisionLevels);
        }

        return array_unique($approvalLevels);
    }

    /**
     * Get division-specific approval levels for the current user
     */
    protected function getDivisionSpecificApprovalLevels(string $modelType): array
    {
        if (!$this->isDivisionSpecificApprover()) {
            return [];
        }

        // Get workflow ID for this model type
        $workflowId = \App\Models\WorkflowModel::getWorkflowIdForModel($modelType);
        
        if (!$workflowId) {
            return [];
        }

        // Get division-specific workflow definitions
        $divisionDefinitions = WorkflowDefinition::where('workflow_id', $workflowId)
            ->where('is_division_specific', 1)
            ->get();

        return $divisionDefinitions->pluck('approval_order')->toArray();
    }

    /**
     * Check if the current user is the current approver for the given item
     * Based on the logic from GenericApprovalHelper::get_approval_recipient_generic
     */
    protected function isCurrentApprover($model): bool
    {
        if (!$model->forward_workflow_id || !$model->approval_level) {
            return false;
        }

        $current_approval_point = WorkflowDefinition::where('approval_order', $model->approval_level)
            ->where('workflow_id', $model->forward_workflow_id)
            ->first();

        if (!$current_approval_point) {
            return false;
        }

        $today = Carbon::today();

        // Check for regular approvers first
        $approver = Approver::where('workflow_dfn_id', $current_approval_point->id)
            ->where('staff_id', $this->currentStaffId)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today);
            })
            ->first();

        if ($approver) {
            return true;
        }

        // Check for OIC approvers
        $oic_approver = Approver::where('workflow_dfn_id', $current_approval_point->id)
            ->where('oic_staff_id', $this->currentStaffId)
            ->where('end_date', '>=', $today)
            ->first();

        if ($oic_approver) {
            return true;
        }

        // Check for division-specific approvers
        if ($current_approval_point->is_division_specific && method_exists($model, 'division') && $model->division) {
            $division = $model->division;
            $referenceColumn = $current_approval_point->division_reference_column;
            
            // Check for active OIC first (if available)
            // Map reference columns to their OIC column names
            $oicColumnMap = [
                'division_head' => 'head_oic_id',
                'finance_officer' => 'finance_officer_oic_id', // This might need to be added to the division table
                'director_id' => 'director_oic_id'
            ];
            
            $oicColumn = $oicColumnMap[$referenceColumn] ?? $referenceColumn . '_oic_id';
            $oicStartColumn = str_replace('_oic_id', '_oic_start_date', $oicColumn);
            $oicEndColumn = str_replace('_oic_id', '_oic_end_date', $oicColumn);
            
            // Check if current user is the active OIC
            if (isset($division->$oicColumn) && $division->$oicColumn == $this->currentStaffId) {
                $isOicActive = true;
                if (isset($division->$oicStartColumn) && $division->$oicStartColumn) {
                    $isOicActive = $isOicActive && $division->$oicStartColumn <= $today;
                }
                if (isset($division->$oicEndColumn) && $division->$oicEndColumn) {
                    $isOicActive = $isOicActive && $division->$oicEndColumn >= $today;
                }
                
                if ($isOicActive) {
                    return true;
                }
            }
            
            // If no active OIC, check primary approver
            if (isset($division->$referenceColumn) && $division->$referenceColumn == $this->currentStaffId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the date when the item was received at the current approval level
     */
    protected function getDateReceivedToCurrentLevel($item): ?Carbon
    {
        // Get the most recent approval trail entry for this item
        $approvalTrail = null;
        
        if (method_exists($item, 'approvalTrails')) {
            $approvalTrail = $item->approvalTrails()
                ->where('approval_order', $item->approval_level)
                ->orderBy('created_at', 'desc')
                ->first();
        }

        return $approvalTrail ? $approvalTrail->created_at : $item->created_at;
    }

    /**
     * Get the workflow role for the current approval level
     */
    protected function getWorkflowRole($item): string
    {
        if (!$item->forward_workflow_id || !$item->approval_level) {
            return 'N/A';
        }

        $workflowDefinition = WorkflowDefinition::where('workflow_id', $item->forward_workflow_id)
            ->where('approval_order', $item->approval_level)
            ->where('is_enabled', 1)
            ->first();

        return $workflowDefinition ? $workflowDefinition->role : 'N/A';
    }

    /**
     * Format a pending item with common structure
     */
    protected function formatPendingItem($item, string $category, array $data): array
    {
        return array_merge([
            'id' => $item->id,
            'category' => $category,
            'status' => $item->overall_status,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ], $data);
    }

    /**
     * Group pending items by category
     */
    protected function groupByCategory(Collection $items): array
    {
        $grouped = $items->groupBy('category');
        
        // Sort items within each category by date received
        $grouped = $grouped->map(function ($categoryItems) {
            return $categoryItems->sortByDesc('date_received');
        });

        return $grouped->toArray();
    }

    /**
     * Get summary statistics for pending approvals
     */
    public function getSummaryStats(): array
    {
        $allPending = collect($this->getPendingApprovals())->flatten(1);
        
        return [
            'total_pending' => $allPending->count(),
            'by_category' => $allPending->groupBy('category')->map->count(),
            'by_division' => $allPending->groupBy('division')->map->count(),
            'oldest_pending' => $allPending->min('date_received'),
            'newest_pending' => $allPending->max('date_received'),
        ];
    }

    /**
     * Get pending approvals for a specific category
     */
    public function getPendingByCategory(string $category): Collection
    {
        $allPending = collect($this->getPendingApprovals())->flatten(1);
        return $allPending->where('category', $category);
    }

    /**
     * Get pending approvals for a specific division
     */
    public function getPendingByDivision(int $divisionId): Collection
    {
        $allPending = collect($this->getPendingApprovals())->flatten(1);
        return $allPending->filter(function ($item) use ($divisionId) {
            // This would need to be enhanced based on how division info is stored
            return true; // Placeholder - implement based on your data structure
        });
    }

    /**
     * Send email notification to approvers about pending items
     */
    public function sendPendingApprovalsNotification(): bool
    {
        try {
            $pendingApprovals = $this->getPendingApprovals();
            $summaryStats = $this->getSummaryStats();
            
            if ($summaryStats['total_pending'] === 0) {
                return true; // No pending items to notify about
            }

            // Get approver email addresses
            $approverEmails = $this->getApproverEmails();
            
            if (empty($approverEmails)) {
                return false; // No approvers to notify
            }

            // Get approver title
            $approverTitle = $this->getApproverTitle();

            // Send notification email
            Mail::send('emails.pending-approvals-notification', [
                'pendingApprovals' => $pendingApprovals,
                'summaryStats' => $summaryStats,
                'approverName' => $this->sessionData['name'] ?? 'Approver',
                'approverTitle' => $approverTitle,
                'baseUrl' => $this->sessionData['base_url'] ?? url('/')
            ], function ($message) use ($approverEmails) {
                $message->to($approverEmails)
                    ->subject('Pending Approvals Notification - ' . $this->sessionData['name'] ?? 'System');
            });

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send pending approvals notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get email addresses of approvers who should be notified
     */
    protected function getApproverEmails(): array
    {
        $emails = [];
        
        // Get division-specific approvers
        if ($this->isDivisionSpecificApprover()) {
            $division = Division::find($this->currentDivisionId);
            if ($division) {
                // Get emails from division table approvers
                $divisionApprovers = [
                    $division->division_head,
                    $division->focal_person,
                    $division->admin_assistant,
                    $division->finance_officer
                ];
                
                // Also include active OIC approvers
                $today = Carbon::today();
                $oicApprovers = [];
                
                // Check head OIC
                if ($division->head_oic_id) {
                    $isOicActive = true;
                    if ($division->head_oic_start_date) {
                        $isOicActive = $isOicActive && $division->head_oic_start_date <= $today;
                    }
                    if ($division->head_oic_end_date) {
                        $isOicActive = $isOicActive && $division->head_oic_end_date >= $today;
                    }
                    if ($isOicActive) {
                        $oicApprovers[] = $division->head_oic_id;
                    }
                }
                
                // Check director OIC
                if ($division->director_oic_id) {
                    $isOicActive = true;
                    if ($division->director_oic_start_date) {
                        $isOicActive = $isOicActive && $division->director_oic_start_date <= $today;
                    }
                    if ($division->director_oic_end_date) {
                        $isOicActive = $isOicActive && $division->director_oic_end_date >= $today;
                    }
                    if ($isOicActive) {
                        $oicApprovers[] = $division->director_oic_id;
                    }
                }
                
                // Combine primary and OIC approvers
                $allDivisionApprovers = array_merge(
                    array_filter($divisionApprovers),
                    $oicApprovers
                );
                
                $staffEmails = Staff::whereIn('staff_id', $allDivisionApprovers)
                    ->where('active', 1)
                    ->pluck('work_email')
                    ->filter()
                    ->toArray();
                
                $emails = array_merge($emails, $staffEmails);
            }
        }
        
        // Get regular approvers from approvers table
        $regularApprovers = Approver::where('staff_id', $this->currentStaffId)
            ->whereHas('workflowDefinition', function($query) {
                $query->where('is_division_specific', 0);
            })
            ->with('staff')
            ->get();
            
        foreach ($regularApprovers as $approver) {
            if ($approver->staff && $approver->staff->work_email) {
                $emails[] = $approver->staff->work_email;
            }
        }
        
        return array_unique(array_filter($emails));
    }

    /**
     * Send notification for a specific pending item
     */
    public function sendItemNotification($item, string $itemType): bool
    {
        try {
            $approverEmails = $this->getApproverEmails();
            
            if (empty($approverEmails)) {
                return false;
            }

            // Get approver title
            $approverTitle = $this->getApproverTitle();

            Mail::send('emails.pending-item-notification', [
                'item' => $item,
                'itemType' => $itemType,
                'approverName' => $this->sessionData['name'] ?? 'Approver',
                'approverTitle' => $approverTitle,
                'baseUrl' => $this->sessionData['base_url'] ?? url('/')
            ], function ($message) use ($approverEmails, $itemType) {
                $message->to($approverEmails)
                    ->subject("New {$itemType} Pending Approval - " . ($item->activity_title ?? $itemType));
            });

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send item notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the title of the current approver
     */
    protected function getApproverTitle(): string
    {
        $staff = Staff::where('staff_id', $this->currentStaffId)->first();
        return $staff ? $staff->title : 'Mr';
    }
}
