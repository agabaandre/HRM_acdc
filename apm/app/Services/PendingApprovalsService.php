<?php

namespace App\Services;

use App\Models\Matrix;
use App\Models\Activity;
use App\Models\SpecialMemo;
use App\Models\NonTravelMemo;
use App\Models\ServiceRequest;
use App\Models\RequestARF;
use App\Models\ChangeRequest;
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

        // Get pending service requests
        $pendingItems = $pendingItems->merge($this->getPendingServiceRequests());

        // Get pending ARF requests
        $pendingItems = $pendingItems->merge($this->getPendingARFRequests());

        // Get pending change requests
        $pendingItems = $pendingItems->merge($this->getPendingChangeRequests());

        // Group by category and sort by date received
        return $this->groupByCategory($pendingItems);
    }

    /**
     * Get pending matrices
     */
    protected function getPendingMatrices(): Collection
    {
        $query = Matrix::with([
            'division',
            'staff',
            'focalPerson',
            'forwardWorkflow',
            'activities' => function ($q) {
                $q->select('id', 'matrix_id', 'activity_title', 'total_participants', 'budget_breakdown')
                  ->whereNotNull('matrix_id');
            }
        ]);

        // Only show pending matrices (not draft)
        $query->where('overall_status', 'pending')
              ->where('forward_workflow_id', '!=', null)
              ->where('approval_level', '>', 0);

        // Get all approval levels for this user (both division-specific and non-division-specific)
        $approvalLevels = $this->getUserApprovalLevels('Matrix');
        
        if (!empty($approvalLevels)) {
            $query->whereIn('approval_level', $approvalLevels);
        } else {
            // If no approval levels, return empty collection
            return collect();
        }

        // For division-specific approvers, show items from all divisions where they are assigned
        $divisionIds = $this->getUserDivisionIds();
        if (!empty($divisionIds)) {
            $query->whereIn('division_id', $divisionIds);
        }

        return $query->get()->filter(function ($matrix) {
            // Check if the current user is actually the current approver for this item
            return $this->isCurrentApprover($matrix);
        })->map(function ($matrix) {
            return $this->formatPendingItem($matrix, 'Matrix', [
                'title' => "Matrix - {$matrix->quarter} {$matrix->year}",
                'division' => $matrix->division->division_name ?? 'N/A',
                'submitted_by' => $matrix->focalPerson ? 
                    ($matrix->focalPerson->fname . ' ' . $matrix->focalPerson->lname) : 
                    ($matrix->staff->fname . ' ' . $matrix->staff->lname ?? 'N/A'),
                'date_received' => $this->getDateReceivedToCurrentLevel($matrix),
                'view_url' => url(route('matrices.show', $matrix, false)),
                'approval_level' => $matrix->approval_level,
                'workflow_role' => $this->getCurrentApproverRole($matrix),
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

        // For division-specific approvers, show items from all divisions where they are assigned
        $divisionIds = $this->getUserDivisionIds();
        if (!empty($divisionIds)) {
            $query->whereIn('division_id', $divisionIds);
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
                'view_url' => url(route('special-memo.show', $memo, false)),
                'approval_level' => $memo->approval_level,
                'workflow_role' => $this->getCurrentApproverRole($memo),
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
            ->where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);

        // Get all approval levels for this user (both division-specific and non-division-specific)
        $approvalLevels = $this->getUserApprovalLevels('NonTravelMemo');
        
        if (!empty($approvalLevels)) {
            $query->whereIn('approval_level', $approvalLevels);
        } else {
            // If no approval levels, return empty collection
            return collect();
        }

        // For division-specific approvers, show items from all divisions where they are assigned
        $divisionIds = $this->getUserDivisionIds();
        if (!empty($divisionIds)) {
            $query->whereIn('division_id', $divisionIds);
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
                'view_url' => url(route('non-travel.show', $memo, false)),
                'approval_level' => $memo->approval_level,
                'workflow_role' => $this->getCurrentApproverRole($memo),
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

        // For division-specific approvers, show items from all divisions where they are assigned
        $divisionIds = $this->getUserDivisionIds();
        if (!empty($divisionIds)) {
            $query->whereIn('division_id', $divisionIds);
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
                'view_url' => url(route('activities.single-memos.show', $activity, false)),
                'approval_level' => $activity->approval_level,
                'workflow_role' => $this->getCurrentApproverRole($activity),
                'item_id' => $activity->id,
                'item_type' => 'Activity'
            ]);
        });
    }

    /**
     * Get pending service requests
     */
    protected function getPendingServiceRequests(): Collection
    {
        $query = ServiceRequest::with(['staff', 'responsiblePerson', 'division', 'approvalTrails.staff', 'forwardWorkflow.workflowDefinitions.approvers.staff'])
            ->where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);

        // Get all approval levels for this user (both division-specific and non-division-specific)
        $approvalLevels = $this->getUserApprovalLevels('ServiceRequest');
        
        if (!empty($approvalLevels)) {
            $query->whereIn('approval_level', $approvalLevels);
        } else {
            // If no approval levels, return empty collection
            return collect();
        }

        // For division-specific approvers, show items from all divisions where they are assigned
        $divisionIds = $this->getUserDivisionIds();
        if (!empty($divisionIds)) {
            $query->whereIn('division_id', $divisionIds);
        }

        return $query->get()->filter(function ($serviceRequest) {
            // Check if the current user is actually the current approver for this item
            return $this->isCurrentApprover($serviceRequest);
        })->map(function ($serviceRequest) {
            return $this->formatPendingItem($serviceRequest, 'Service Request', [
                'title' => $serviceRequest->title ?? 'Service Request',
                'division' => $serviceRequest->division->division_name ?? 'N/A',
                'submitted_by' => $serviceRequest->responsiblePerson ? 
                    ($serviceRequest->responsiblePerson->fname . ' ' . $serviceRequest->responsiblePerson->lname) : 
                    ($serviceRequest->staff->fname . ' ' . $serviceRequest->staff->lname ?? 'N/A'),
                'date_received' => $this->getDateReceivedToCurrentLevel($serviceRequest),
                'view_url' => url(route('service-requests.show', $serviceRequest, false)),
                'approval_level' => $serviceRequest->approval_level,
                'workflow_role' => $this->getCurrentApproverRole($serviceRequest),
                'item_id' => $serviceRequest->id,
                'item_type' => 'ServiceRequest'
            ]);
        });
    }

    /**
     * Get pending ARF requests
     */
    protected function getPendingARFRequests(): Collection
    {
        $query = RequestARF::with(['staff', 'responsiblePerson', 'division', 'approvalTrails.staff', 'forwardWorkflow.workflowDefinitions.approvers.staff'])
            ->where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);

        // Get all approval levels for this user (both division-specific and non-division-specific)
        $approvalLevels = $this->getUserApprovalLevels('RequestARF');
        
        if (!empty($approvalLevels)) {
            $query->whereIn('approval_level', $approvalLevels);
        } else {
            // If no approval levels, return empty collection
            return collect();
        }

        // For division-specific approvers, show items from all divisions where they are assigned
        $divisionIds = $this->getUserDivisionIds();
        if (!empty($divisionIds)) {
            $query->whereIn('division_id', $divisionIds);
        }

        $results = $query->get()->filter(function ($arfRequest) {
            // Check if the current user is actually the current approver for this item
            return $this->isCurrentApprover($arfRequest);
        })->map(function ($arfRequest) {
            return $this->formatPendingItem($arfRequest, 'ARF', [
                'title' => $arfRequest->activity_title ?? 'ARF Request',
                'division' => $arfRequest->division->division_name ?? 'N/A',
                'submitted_by' => $arfRequest->responsiblePerson ? 
                    ($arfRequest->responsiblePerson->fname . ' ' . $arfRequest->responsiblePerson->lname) : 
                    ($arfRequest->staff->fname . ' ' . $arfRequest->staff->lname ?? 'N/A'),
                'date_received' => $this->getDateReceivedToCurrentLevel($arfRequest),
                'view_url' => url(route('request-arf.show', $arfRequest, false)),
                'approval_level' => $arfRequest->approval_level,
                'workflow_role' => $this->getCurrentApproverRole($arfRequest),
                'item_id' => $arfRequest->id,
                'item_type' => 'RequestARF'
            ]);
        });
        
        return $results;
    }

    /**
     * Check if the current user is a division-specific approver (defined in division table)
     */
    protected function isDivisionSpecificApprover(): bool
    {
        if (!$this->currentStaffId) {
            return false;
        }

        // Check if user is a division approver in ANY division (not just their primary division)
        $divisionIds = $this->getUserDivisionIds();
        
        // If user is assigned to any division as a division-specific officer, return true
        return !empty($divisionIds);
    }

    /**
     * Get all division IDs where the current user is assigned as a division-specific approver
     */
    protected function getUserDivisionIds(): array
    {
        if (!$this->currentStaffId) {
            return [];
        }

        $divisionIds = [];
        
        // Get all divisions where the user is assigned as a division-specific approver
        $divisions = Division::where(function ($query) {
            $query->where('division_head', $this->currentStaffId)
                  ->orWhere('focal_person', $this->currentStaffId)
                  ->orWhere('admin_assistant', $this->currentStaffId)
                  ->orWhere('finance_officer', $this->currentStaffId)
                  ->orWhere('director_id', $this->currentStaffId);
        })->get();

        foreach ($divisions as $division) {
            $divisionIds[] = $division->id;
        }

        // Also check for active OIC assignments
        $today = Carbon::today();
        
        $oicDivisions = Division::where(function ($query) use ($today) {
            $query->where('head_oic_id', $this->currentStaffId)
                  ->where(function ($q) use ($today) {
                      $q->whereNull('head_oic_start_date')
                        ->orWhere('head_oic_start_date', '<=', $today);
                  })
                  ->where(function ($q) use ($today) {
                      $q->whereNull('head_oic_end_date')
                        ->orWhere('head_oic_end_date', '>=', $today);
                  });
        })->orWhere(function ($query) use ($today) {
            $query->where('director_oic_id', $this->currentStaffId)
                  ->where(function ($q) use ($today) {
                      $q->whereNull('director_oic_start_date')
                        ->orWhere('director_oic_start_date', '<=', $today);
                  })
                  ->where(function ($q) use ($today) {
                      $q->whereNull('director_oic_end_date')
                        ->orWhere('director_oic_end_date', '>=', $today);
                  });
        })->get();

        foreach ($oicDivisions as $division) {
            if (!in_array($division->id, $divisionIds)) {
                $divisionIds[] = $division->id;
            }
        }

        return $divisionIds;
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
     * Use the ApprovalService for proper generic logic
     */
    protected function isCurrentApprover($model): bool
    {
        // Use the ApprovalService for consistent logic across all model types
        $approvalService = app(\App\Services\ApprovalService::class);
        return $approvalService->canTakeAction($model, $this->currentStaffId);
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
     * Get the workflow role that the current approver should be acting as
     * This is different from getWorkflowRole which shows the current level of the item
     */
    protected function getCurrentApproverRole($item): string
    {
        if (!$item->forward_workflow_id || $item->approval_level === null) {
            return 'N/A';
        }

        // Special case: level 0 means returned to creator/focal person
        if ($item->approval_level == 0) {
            return 'Creator/Focal Person';
        }

        // For daily notifications, we need to find what role the current approver should be acting as
        // This means finding the workflow definition that matches the current approver's approval level
        $workflowDefinition = WorkflowDefinition::where('workflow_id', $item->forward_workflow_id)
            ->where('approval_order', $item->approval_level)
            ->where('is_enabled', 1)
            ->first();

        if (!$workflowDefinition) {
            return 'N/A';
        }

        // Check if this is a division-specific role and if the current user matches
        if ($workflowDefinition->is_division_specific) {
            $division = $item->division;
            if ($division) {
                $referenceColumn = $workflowDefinition->division_reference_column;
                $staffId = $division->{$referenceColumn} ?? null;
                
                if ($staffId == $this->currentStaffId) {
                    // Special case: If the person is both division head and finance officer,
                    // prioritize showing "Head of Division" for better clarity
                    if ($referenceColumn == 'finance_officer' && $division->division_head == $this->currentStaffId) {
                        return 'Head of Division';
                    }
                    return $workflowDefinition->role;
                }
            }
        }

        // Check if the current user is assigned to this workflow definition
        $approver = Approver::where('workflow_dfn_id', $workflowDefinition->id)
            ->where('staff_id', $this->currentStaffId)
            ->first();

        if ($approver) {
            return $workflowDefinition->role;
        }

        // If no specific match, return the role anyway (this might be a fallback case)
        return $workflowDefinition->role;
    }

    /**
     * Format a pending item with common structure
     */
    protected function formatPendingItem($item, string $category, array $data): array
    {
        return array_merge([
            'id' => $item->id,
            'category' => $category,
            'type' => $data['item_type'] ?? $category, // Use item_type if available, otherwise use category
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
        $allPending = $this->getPendingApprovals();
        return collect($allPending[$category] ?? []);
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
     * Get pending change requests
     */
    protected function getPendingChangeRequests(): Collection
    {
        $query = ChangeRequest::with(['staff', 'division', 'approvalTrails.staff'])
            ->where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);

        // Get all approval levels for this user (both division-specific and non-division-specific)
        $approvalLevels = $this->getUserApprovalLevels('ChangeRequest');
        
        if (!empty($approvalLevels)) {
            $query->whereIn('approval_level', $approvalLevels);
        } else {
            // If no approval levels, return empty collection
            return collect();
        }

        // For division-specific approvers, show items from all divisions where they are assigned
        $divisionIds = $this->getUserDivisionIds();
        if (!empty($divisionIds)) {
            $query->whereIn('division_id', $divisionIds);
        }

        return $query->get()->filter(function ($changeRequest) {
            // Check if the current user is actually the current approver for this item
            return $this->isCurrentApprover($changeRequest);
        })->map(function ($changeRequest) {
            return $this->formatPendingItem($changeRequest, 'Change Request', [
                'title' => $changeRequest->activity_title ?? 'Change Request',
                'division' => $changeRequest->division->division_name ?? 'N/A',
                'submitted_by' => $changeRequest->staff->fname . ' ' . $changeRequest->staff->lname ?? 'N/A',
                'date_received' => $this->getDateReceivedToCurrentLevel($changeRequest),
                'view_url' => url(route('change-requests.show', $changeRequest, false)),
                'approval_level' => $changeRequest->approval_level,
                'workflow_role' => $this->getCurrentApproverRole($changeRequest),
                'item_id' => $changeRequest->id,
                'item_type' => 'ChangeRequest'
            ]);
        });
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
