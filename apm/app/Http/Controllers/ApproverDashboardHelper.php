<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

trait ApproverDashboardHelper
{
    /**
     * Build the base query for approvers from both approvers table and divisions table.
     * Properly handles division-specific approval levels using is_division_specific column.
     */
    protected function buildApproverQuery($workflowId, $search = null, $divisionId = null, $docType = null, $approvalLevel = null)
    {
        // First, get approvers from the approvers table (only non-division-specific roles)
        $approversQuery = DB::table('workflow_definition as wd')
            ->join('approvers as a', 'wd.id', '=', 'a.workflow_dfn_id')
            ->join('staff as s', 'a.staff_id', '=', 's.staff_id')
            ->leftJoin('divisions as d', 's.division_id', '=', 'd.id')
            ->select([
                's.id as approver_id',
                's.staff_id',
                's.fname',
                's.lname',
                's.work_email',
                's.division_id',
                's.photo',
                'd.division_name',
                'wd.approval_order as level_no',
                'wd.workflow_id',
                'wd.role',
                'wd.is_division_specific',
                'wd.division_reference_column',
                DB::raw("'approvers_table' as source")
            ])
            ->where('wd.workflow_id', $workflowId)
            ->where('wd.is_enabled', 1)
            ->where('wd.is_division_specific', 0) // Only non-division-specific roles
            ->where('s.active', 1);

        // Apply filters to approvers query
        if ($search) {
            $approversQuery->where(function ($q) use ($search) {
                $q->where('s.fname', 'like', "%{$search}%")
                  ->orWhere('s.lname', 'like', "%{$search}%")
                  ->orWhere('s.work_email', 'like', "%{$search}%")
                  ->orWhere('wd.role', 'like', "%{$search}%");
            });
        }

        if ($divisionId) {
            $approversQuery->where('s.division_id', $divisionId);
        }

        if ($approvalLevel) {
            $approversQuery->where('wd.approval_order', $approvalLevel);
        }

        // Second, get approvers from divisions table (only for division-specific roles)
        // Get all division-specific workflow definitions first
        $divisionSpecificRoles = DB::table('workflow_definition')
            ->where('workflow_id', $workflowId)
            ->where('is_enabled', 1)
            ->where('is_division_specific', 1)
            ->get();

        $divisionsResults = collect();
        
        foreach ($divisionSpecificRoles as $role) {
            $columnName = $role->division_reference_column;
            if (!$columnName) continue;
            
            // Skip this role if approval level filter is specified and doesn't match
            if ($approvalLevel && $role->approval_order != $approvalLevel) {
                continue;
            }
            
            $roleQuery = DB::table('divisions as d')
                ->join('staff as s', "s.staff_id", "=", "d.{$columnName}")
                ->select([
                    's.id as approver_id',
                    's.staff_id',
                    's.fname',
                    's.lname',
                    's.work_email',
                    's.photo',
                    'd.id as division_id', // Use the division they're assigned to, not their own division
                    'd.division_name',
                    DB::raw("{$role->approval_order} as level_no"),
                    DB::raw("{$role->workflow_id} as workflow_id"),
                    DB::raw("'{$role->role}' as role"),
                    DB::raw("1 as is_division_specific"),
                    DB::raw("'{$columnName}' as division_reference_column"),
                    DB::raw("'divisions_table' as source")
                ])
                ->where('s.active', 1)
                ->whereNotNull("d.{$columnName}");

            // Apply filters
            if ($search) {
                $roleQuery->where(function ($q) use ($search) {
                    $q->where('s.fname', 'like', "%{$search}%")
                      ->orWhere('s.lname', 'like', "%{$search}%")
                      ->orWhere('s.work_email', 'like', "%{$search}%")
                      ->orWhere('role', 'like', "%{$search}%");
                });
            }

            if ($divisionId) {
                $roleQuery->where('d.id', $divisionId);
            }

            $divisionsResults = $divisionsResults->concat($roleQuery->get());
        }

        // Get results from both queries and combine them
        $approversResults = $approversQuery->get();
        
        // Combine results (don't deduplicate - show each assignment separately)
        $combinedResults = $approversResults->concat($divisionsResults)
            ->sortBy([
                ['level_no', 'asc'],
                ['division_name', 'asc'],
                ['fname', 'asc'],
                ['lname', 'asc']
            ]);
        
        return $combinedResults;
    }

    /**
     * Get pending counts for each approver.
     * Combines approvers by staff_id to aggregate counts across all roles/levels.
     */
    protected function getPendingCountsForApprovers($approvers, $workflowDefinitionId, $docType = null, $divisionId = null)
    {
        $approversByStaffId = [];

        // Handle both collection and array inputs
        $approversList = is_array($approvers) ? $approvers : $approvers->toArray();

        // First pass: collect all approver data grouped by staff_id
        foreach ($approversList as $approver) {
            $approverObj = is_array($approver) ? (object) $approver : $approver;
            $staffId = $approverObj->staff_id;
            
            if (!isset($approversByStaffId[$staffId])) {
                $approversByStaffId[$staffId] = [
                    'staff_id' => $staffId,
                    'approver_id' => $approverObj->approver_id,
                    'approver_name' => trim($approverObj->fname . ' ' . $approverObj->lname),
                    'approver_email' => $approverObj->work_email,
                    'photo' => $approverObj->photo ?? null,
                    'fname' => $approverObj->fname ?? '',
                    'lname' => $approverObj->lname ?? '',
                    'division_name' => $approverObj->division_name,
                    'roles' => [],
                    'levels' => [],
                    'pending_counts' => [
                        'matrix' => 0,
                        'non_travel' => 0,
                        'single_memos' => 0,
                        'special' => 0,
                        'memos' => 0,
                        'arf' => 0,
                        'requests_for_service' => 0,
                        'change_requests' => 0,
                    ],
                    'total_pending' => 0,
                    'total_handled' => 0,
                ];
            }
            
            // Add role and level if not already present
            $roleLevel = $approverObj->role . ' (Level ' . $approverObj->level_no . ')';
            if (!in_array($roleLevel, $approversByStaffId[$staffId]['roles'])) {
                $approversByStaffId[$staffId]['roles'][] = $roleLevel;
            }
            if (!in_array($approverObj->level_no, $approversByStaffId[$staffId]['levels'])) {
                $approversByStaffId[$staffId]['levels'][] = $approverObj->level_no;
            }
            
            // Note: Pending counts, total handled, and avg approval time are now calculated
            // across all workflows/levels in the second pass, so we don't need to calculate them per level here
        }

        // Second pass: build final array with combined data
        // Get pending counts across ALL workflows for each approver (matching pending-approvals logic)
        $approversWithCounts = [];
        foreach ($approversByStaffId as $staffId => $data) {
            // Get pending counts across ALL workflows using PendingApprovalsService logic
            $allPendingCounts = $this->getPendingCountsForApproverAll($staffId, $divisionId);
            
            // Use the aggregated counts from all workflows
            $data['pending_counts'] = $allPendingCounts;
            
            // Calculate total pending (sum of all pending counts)
            $totalPending = array_sum(array_diff_key($data['pending_counts'], ['total' => '', 'memos' => '']));
            
            // Calculate total handled across ALL workflows and levels for this approver
            $totalHandled = $this->getTotalHandledForApproverAll($staffId, $divisionId);
            
            // Calculate average approval time across ALL workflows and levels using approval_trails
            $avgApprovalTime = $this->getAverageApprovalTimeAll($staffId, $divisionId);
            
            // Sort roles and levels for display
            sort($data['levels']);
            sort($data['roles']);
            
            $approversWithCounts[] = [
                'staff_id' => $data['staff_id'],
                'approver_id' => $data['approver_id'],
                'approver_name' => $data['approver_name'],
                'approver_email' => $data['approver_email'],
                'photo' => !empty($data['photo']) ? $data['photo'] : null, // Ensure photo is included
                'fname' => $data['fname'] ?? '',
                'lname' => $data['lname'] ?? '',
                'division_name' => $data['division_name'],
                'roles' => $data['roles'],
                'levels' => $data['levels'],
                'role' => implode(', ', $data['roles']), // Combined roles for display
                'level_no' => implode(', ', $data['levels']), // Combined levels for display
                'pending_counts' => $data['pending_counts'],
                'total_pending' => $totalPending,
                'total_handled' => $totalHandled,
                'avg_approval_time_hours' => $avgApprovalTime,
                'avg_approval_time_display' => $this->formatApprovalTime($avgApprovalTime),
            ];
        }

        return $approversWithCounts;
    }

    /**
     * Get pending counts for a specific approver across ALL workflows (matching pending-approvals logic).
     * This ensures counts match what's shown in pending-approvals and excludes already handled items.
     * Uses ApprovalService::canTakeAction to verify the approver can actually approve each item.
     */
    protected function getPendingCountsForApproverAll($staffId, $divisionId = null)
    {
        $counts = [
            'matrix' => 0,
            'non_travel' => 0,
            'single_memos' => 0,
            'special' => 0,
            'memos' => 0,
            'arf' => 0,
            'requests_for_service' => 0,
            'change_requests' => 0,
        ];

        // Use ApprovalService to check if approver can take action (same logic as PendingApprovalsService)
        $approvalService = app(\App\Services\ApprovalService::class);

        // Get pending matrices (across all workflows)
        $query = \App\Models\Matrix::with(['division', 'staff', 'focalPerson', 'forwardWorkflow'])
            ->where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);
        
        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }
        
        $matrices = $query->get();
        foreach ($matrices as $matrix) {
            if ($approvalService->canTakeAction($matrix, $staffId)) {
                $counts['matrix']++;
            }
        }

        // Get pending special memos (across all workflows)
        $query = \App\Models\SpecialMemo::with(['staff', 'division'])
            ->where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);
        
        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }
        
        $memos = $query->get();
        foreach ($memos as $memo) {
            if ($approvalService->canTakeAction($memo, $staffId)) {
                $counts['special']++;
            }
        }

        // Get pending non-travel memos (across all workflows)
        $query = \App\Models\NonTravelMemo::with(['staff', 'division'])
            ->where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);
        
        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }
        
        $memos = $query->get();
        foreach ($memos as $memo) {
            if ($approvalService->canTakeAction($memo, $staffId)) {
                $counts['non_travel']++;
            }
        }

        // Get pending single memos (activities with is_single_memo = true, across all workflows)
        $query = \App\Models\Activity::with(['staff', 'division'])
            ->where('is_single_memo', true)
            ->where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);
        
        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }
        
        $activities = $query->get();
        foreach ($activities as $activity) {
            if ($approvalService->canTakeAction($activity, $staffId)) {
                $counts['single_memos']++;
            }
        }

        // Get pending ARF requests (across all workflows)
        $query = \App\Models\RequestARF::with(['staff', 'division', 'forwardWorkflow'])
            ->where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);
        
        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }
        
        $arfs = $query->get();
        foreach ($arfs as $arf) {
            if ($approvalService->canTakeAction($arf, $staffId)) {
                $counts['arf']++;
            }
        }

        // Get pending service requests (across all workflows)
        $query = \App\Models\ServiceRequest::with(['staff', 'division', 'forwardWorkflow'])
            ->where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);
        
        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }
        
        $serviceRequests = $query->get();
        foreach ($serviceRequests as $serviceRequest) {
            if ($approvalService->canTakeAction($serviceRequest, $staffId)) {
                $counts['requests_for_service']++;
            }
        }

        // Get pending change requests (uses workflows 1, 6, 7)
        $query = \App\Models\ChangeRequest::with(['staff', 'division', 'forwardWorkflow'])
            ->where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);
        
        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }
        
        $changeRequests = $query->get();
        foreach ($changeRequests as $changeRequest) {
            if ($approvalService->canTakeAction($changeRequest, $staffId)) {
                $counts['change_requests']++;
            }
        }

        return $counts;
    }

    /**
     * Get pending counts for a specific approver at a specific level/workflow (legacy method, kept for compatibility).
     * This ensures counts match what's shown in pending-approvals and excludes already handled items.
     * Uses ApprovalService::canTakeAction to verify the approver can actually approve each item.
     */
    protected function getPendingCountsForApprover($staffId, $levelNo, $workflowId, $docType = null, $divisionId = null)
    {
        $counts = [
            'matrix' => 0,
            'non_travel' => 0,
            'single_memos' => 0,
            'special' => 0,
            'memos' => 0,
            'arf' => 0,
            'requests_for_service' => 0,
            'change_requests' => 0,
        ];

        // Use ApprovalService to check if approver can take action (same logic as PendingApprovalsService)
        $approvalService = app(\App\Services\ApprovalService::class);

        // Get pending matrices
        if (!$docType || $docType === 'matrix') {
            $query = \App\Models\Matrix::with(['division', 'staff', 'focalPerson', 'forwardWorkflow'])
                ->where('overall_status', 'pending')
                ->where('forward_workflow_id', '!=', null)
                ->where('approval_level', '>', 0)
                ->where('forward_workflow_id', $workflowId)
                ->where('approval_level', $levelNo);
            
            if ($divisionId) {
                $query->where('division_id', $divisionId);
            }
            
            $matrices = $query->get();
            foreach ($matrices as $matrix) {
                if ($approvalService->canTakeAction($matrix, $staffId)) {
                    $counts['matrix']++;
                }
            }
        }

        // Get pending special memos
        if (!$docType || $docType === 'special') {
            $query = \App\Models\SpecialMemo::with(['staff', 'division'])
                ->where('overall_status', 'pending')
                ->where('forward_workflow_id', '!=', null)
                ->where('approval_level', '>', 0)
                ->where('forward_workflow_id', $workflowId)
                ->where('approval_level', $levelNo);
            
            if ($divisionId) {
                $query->where('division_id', $divisionId);
            }
            
            $memos = $query->get();
            foreach ($memos as $memo) {
                if ($approvalService->canTakeAction($memo, $staffId)) {
                    $counts['special']++;
                }
            }
        }

        // Get pending non-travel memos
        if (!$docType || $docType === 'non_travel') {
            $query = \App\Models\NonTravelMemo::with(['staff', 'division'])
                ->where('overall_status', 'pending')
                ->where('forward_workflow_id', '!=', null)
                ->where('approval_level', '>', 0)
                ->where('forward_workflow_id', $workflowId)
                ->where('approval_level', $levelNo);
            
            if ($divisionId) {
                $query->where('division_id', $divisionId);
            }
            
            $memos = $query->get();
            foreach ($memos as $memo) {
                if ($approvalService->canTakeAction($memo, $staffId)) {
                    $counts['non_travel']++;
                }
            }
        }

        // Get pending single memos (activities with is_single_memo = true)
        if (!$docType || $docType === 'single_memos') {
            $query = \App\Models\Activity::with(['staff', 'division'])
                ->where('is_single_memo', true)
                ->where('overall_status', 'pending')
                ->where('forward_workflow_id', '!=', null)
                ->where('approval_level', '>', 0)
                ->where('forward_workflow_id', $workflowId)
                ->where('approval_level', $levelNo);
            
            if ($divisionId) {
                $query->where('division_id', $divisionId);
            }
            
            $activities = $query->get();
            foreach ($activities as $activity) {
                if ($approvalService->canTakeAction($activity, $staffId)) {
                    $counts['single_memos']++;
                }
            }
        }

        // Get pending ARF requests
        if (!$docType || $docType === 'arf') {
            $query = \App\Models\RequestARF::with(['staff', 'division', 'forwardWorkflow'])
                ->where('overall_status', 'pending')
                ->where('forward_workflow_id', '!=', null)
                ->where('approval_level', '>', 0)
                ->where('forward_workflow_id', $workflowId)
                ->where('approval_level', $levelNo);
            
            if ($divisionId) {
                $query->where('division_id', $divisionId);
            }
            
            $arfs = $query->get();
            foreach ($arfs as $arf) {
                if ($approvalService->canTakeAction($arf, $staffId)) {
                    $counts['arf']++;
                }
            }
        }

        // Get pending service requests
        if (!$docType || $docType === 'requests_for_service') {
            $query = \App\Models\ServiceRequest::with(['staff', 'division', 'forwardWorkflow'])
                ->where('overall_status', 'pending')
                ->where('forward_workflow_id', '!=', null)
                ->where('approval_level', '>', 0)
                ->where('forward_workflow_id', $workflowId)
                ->where('approval_level', $levelNo);
            
            if ($divisionId) {
                $query->where('division_id', $divisionId);
            }
            
            $serviceRequests = $query->get();
            foreach ($serviceRequests as $serviceRequest) {
                if ($approvalService->canTakeAction($serviceRequest, $staffId)) {
                    $counts['requests_for_service']++;
                }
            }
        }

        // Get pending change requests (uses workflows 1, 6, 7)
        if (!$docType || $docType === 'change_requests') {
            $possibleWorkflowIds = [1, 6, 7];
            $query = \App\Models\ChangeRequest::with(['staff', 'division', 'forwardWorkflow'])
                ->where('overall_status', 'pending')
                ->where('forward_workflow_id', '!=', null)
                ->where('approval_level', '>', 0)
                ->whereIn('forward_workflow_id', $possibleWorkflowIds)
                ->where('approval_level', $levelNo);
            
            if ($divisionId) {
                $query->where('division_id', $divisionId);
            }
            
            $changeRequests = $query->get();
            foreach ($changeRequests as $changeRequest) {
                if ($approvalService->canTakeAction($changeRequest, $staffId)) {
                    $counts['change_requests']++;
                }
            }
        }

        $counts['total'] = array_sum($counts);
        return $counts;
    }

    /**
     * Get pending count for ARF requests.
     * ARF requests use forward_workflow_id and approval_level.
     */
    protected function getPendingCountForARF($workflowId, $levelNo, $divisionId = null)
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('request_arfs')) {
                return 0;
            }

            $query = DB::table('request_arfs')
                ->where('forward_workflow_id', $workflowId)
                ->where('approval_level', $levelNo)
                ->where('overall_status', 'pending')
                ->whereNotNull('forward_workflow_id');

            if ($divisionId) {
                $query->where('division_id', $divisionId);
            }

            return $query->count();
        } catch (\Exception $e) {
            Log::error('Error getting pending count for ARF: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get pending count for Service Requests.
     * Service requests use workflow_id and approval_level.
     */
    protected function getPendingCountForServiceRequests($workflowId, $levelNo, $divisionId = null)
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('service_requests')) {
                return 0;
            }

            $query = DB::table('service_requests')
                ->where('workflow_id', $workflowId)
                ->where('approval_level', $levelNo)
                ->where('approval_status', 'pending')
                ->whereNotNull('workflow_id');

            if ($divisionId) {
                $query->where('division_id', $divisionId);
            }

            return $query->count();
        } catch (\Exception $e) {
            Log::error('Error getting pending count for service requests: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get pending count for Change Requests.
     */
    protected function getPendingCountForChangeRequests($workflowId, $levelNo, $divisionId = null)
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('change_request')) {
                return 0;
            }

            // Change requests use dynamic workflows (1, 6, 7) based on change type
            // Check if the workflow ID matches any of the possible change request workflows
            $possibleWorkflowIds = [1, 6, 7];
            if (!in_array($workflowId, $possibleWorkflowIds)) {
                return 0;
            }

            $query = DB::table('change_request')
                ->where('forward_workflow_id', $workflowId)
                ->where('overall_status', 'pending')
                ->where('approval_level', $levelNo)
                ->whereNotNull('forward_workflow_id');

            if ($divisionId) {
                $query->where('division_id', $divisionId);
            }

            return $query->count();
        } catch (\Exception $e) {
            Log::error('Error getting pending count for change requests: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get document type labels.
     */
    protected function getDocumentTypeLabels()
    {
        return [
            'matrix' => 'Matrix',
            'non_travel' => 'Non-Travel Memos',
            'single_memos' => 'Single Memos',
            'special' => 'Special Memos',
            'memos' => 'Memos',
            'arf' => 'ARF Requests',
            'requests_for_service' => 'Requests for Service',
            'change_requests' => 'Change Requests',
        ];
    }

    /**
     * Get status labels.
     */
    protected function getStatusLabels()
    {
        return [
            'draft' => 'Draft',
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
        ];
    }

    /**
     * Get total pending documents for a specific approver at their level.
     * This counts all documents that are pending for this approver regardless of document type.
     */
    protected function getTotalPendingForApprover($approverId, $levelNo, $workflowId, $docType = null, $divisionId = null)
    {
        try {
            // No date filtering needed for total pending calculation

            // Count all documents pending for this approver at this level
            // Logic: For level 1, find documents submitted (order 0) but not yet approved at level 1
            // For other levels, find documents approved at previous level but not yet approved at current level
            if ($levelNo == 1) {
                // Level 1: Documents submitted (order 0) but not yet approved at level 1
                $sql = "
                    SELECT COUNT(DISTINCT CONCAT(at.model_type, '-', at.model_id)) as total_count
                    FROM approval_trails at
                    WHERE at.forward_workflow_id = ?
                    AND at.approval_order = 0
                    AND at.action = 'submitted'
                    AND at.model_id NOT IN (
                        SELECT DISTINCT at2.model_id 
                        FROM approval_trails at2 
                        WHERE at2.model_type = at.model_type 
                        AND at2.model_id = at.model_id 
                        AND at2.forward_workflow_id = at.forward_workflow_id
                        AND at2.approval_order = ?
                        AND at2.action IN ('approved', 'rejected')
                    )
                                        ";
                    $params = [$workflowId, $levelNo];
            } else {
                // Other levels: Documents approved at previous level but not yet approved at current level
                $sql = "
                    SELECT COUNT(DISTINCT CONCAT(at.model_type, '-', at.model_id)) as total_count
                    FROM approval_trails at
                    WHERE at.forward_workflow_id = ?
                    AND at.approval_order = ?
                    AND at.action IN ('approved', 'rejected')
                    AND at.model_id NOT IN (
                        SELECT DISTINCT at2.model_id 
                        FROM approval_trails at2 
                        WHERE at2.model_type = at.model_type 
                        AND at2.model_id = at.model_id 
                        AND at2.forward_workflow_id = at.forward_workflow_id
                        AND at2.approval_order = ?
                        AND at2.action IN ('approved', 'rejected')
                    )
                                        ";
                    $params = [$workflowId, $levelNo - 1, $levelNo];
            }
            $result = DB::select($sql, $params);
            
            return $result[0]->total_count ?? 0;

        } catch (\Exception $e) {
            Log::error('Error calculating total pending for approver: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Calculate average approval time for a specific approver.
     * Returns the average time in hours between when a document reaches this approver's level
     * and when they approve/reject it.
     */
    protected function getAverageApprovalTime($approverId, $levelNo, $workflowId, $docType = null)
    {
        try {
            // No date filtering needed for approval time calculation

            // Get approval times for this approver at this level
            // For level 1: Find documents submitted (order 0) and approved at level 1
            // For other levels: Find documents approved at previous level and approved at current level
            if ($levelNo == 1) {
                // Level 1: Documents submitted (order 0) and approved at level 1 by this specific approver
                $sql = "
                    SELECT 
                        at.model_id,
                        at.model_type,
                        at.created_at as approval_time,
                        submitted_at.created_at as submitted_time
                    FROM approval_trails at
                    INNER JOIN approval_trails submitted_at ON (
                        submitted_at.model_type = at.model_type 
                        AND submitted_at.model_id = at.model_id 
                        AND submitted_at.forward_workflow_id = at.forward_workflow_id
                        AND submitted_at.approval_order = 0
                        AND submitted_at.action = 'submitted'
                    )
                    WHERE at.forward_workflow_id = ?
                    AND at.approval_order = ?
                    AND at.staff_id = ?
                    AND at.action IN ('approved', 'rejected')
                    AND at.created_at >= submitted_at.created_at
                ";
                $params = [$workflowId, $levelNo, $approverId];
            } else {
                // Other levels: Documents approved at previous level and approved at current level by this specific approver
                $sql = "
                    SELECT 
                        at.model_id,
                        at.model_type,
                        at.created_at as approval_time,
                        prev_at.created_at as submitted_time
                    FROM approval_trails at
                    INNER JOIN approval_trails prev_at ON (
                        prev_at.model_type = at.model_type 
                        AND prev_at.model_id = at.model_id 
                        AND prev_at.forward_workflow_id = at.forward_workflow_id
                        AND prev_at.approval_order = ?
                        AND prev_at.action IN ('approved', 'rejected')
                    )
                    WHERE at.forward_workflow_id = ?
                    AND at.approval_order = ?
                    AND at.staff_id = ?
                    AND at.action IN ('approved', 'rejected')
                    AND at.created_at >= prev_at.created_at
                ";
                $params = [$levelNo - 1, $workflowId, $levelNo, $approverId];
            }
            $results = DB::select($sql, $params);

            if (empty($results)) {
                return 0;
            }

            $totalHours = 0;
            $count = 0;

            foreach ($results as $result) {
                $approvalTime = Carbon::parse($result->approval_time);
                $submittedTime = Carbon::parse($result->submitted_time);
                
                // Calculate seconds between when document was submitted to this level and when it was approved
                $seconds = abs($approvalTime->getTimestamp() - $submittedTime->getTimestamp());
                
                // Convert to hours (with decimal precision)
                $hours = $seconds / 3600;
                
                // Add to total (time is now always positive)
                $totalHours += $hours;
                $count++;
            }

            return $count > 0 ? round($totalHours / $count, 2) : 0;

        } catch (\Exception $e) {
            Log::error('Error calculating average approval time: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Format approval time for display.
     */
    protected function formatApprovalTime($hours)
    {
        if ($hours == 0) {
            return 'No data';
        }

        if ($hours < 1) {
            $minutes = round($hours * 60);
            return $minutes . ' min';
        } elseif ($hours < 24) {
            return round($hours, 1) . ' hrs';
        } else {
            $days = round($hours / 24, 1);
            return $days . ' days';
        }
    }

    /**
     * Get total handled documents for a specific approver across ALL workflows and levels.
     * This counts all documents that have been approved/rejected by this approver using approval_trails.
     */
    protected function getTotalHandledForApproverAll($staffId, $divisionId = null)
    {
        try {
            // Count all documents handled by this approver across all workflows and levels
            // Using approval_trails where staff_id matches and action is approved/rejected
            $query = DB::table('approval_trails as at')
                ->where('at.staff_id', $staffId)
                ->whereIn('at.action', ['approved', 'rejected'])
                ->where('at.is_archived', 0); // Only non-archived trails
            
            // If division filter is applied, we need to join with the document tables
            // For now, we'll count all handled items regardless of division
            // (Division filtering is handled at the pending counts level)
            
            $result = $query->select(DB::raw('COUNT(DISTINCT CONCAT(at.model_type, "-", at.model_id)) as total_count'))
                ->first();
            
            return $result->total_count ?? 0;
            
        } catch (\Exception $e) {
            Log::error('Error calculating total handled for approver (all): ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get average approval time for a specific approver across ALL workflows and levels.
     * Calculates time between when item reached approver's level and when they approved it.
     * Uses approval_trails created_at timestamps.
     */
    protected function getAverageApprovalTimeAll($staffId, $divisionId = null)
    {
        try {
            // Get all approval actions by this approver
            // For each approval, find when the item reached their level (previous approval or submission)
            $sql = "
                SELECT 
                    at.id,
                    at.model_id,
                    at.model_type,
                    at.forward_workflow_id,
                    at.approval_order,
                    at.created_at as approval_time,
                    COALESCE(
                        -- Previous level approval time
                        (SELECT MAX(prev_at.created_at)
                         FROM approval_trails prev_at
                         WHERE prev_at.model_type = at.model_type
                           AND prev_at.model_id = at.model_id
                           AND prev_at.forward_workflow_id = at.forward_workflow_id
                           AND prev_at.approval_order < at.approval_order
                           AND prev_at.action IN ('approved', 'rejected', 'submitted')
                           AND prev_at.is_archived = 0),
                        -- Or submission time if no previous approval
                        (SELECT MIN(sub_at.created_at)
                         FROM approval_trails sub_at
                         WHERE sub_at.model_type = at.model_type
                           AND sub_at.model_id = at.model_id
                           AND sub_at.forward_workflow_id = at.forward_workflow_id
                           AND sub_at.approval_order = 0
                           AND sub_at.action = 'submitted'
                           AND sub_at.is_archived = 0)
                    ) as received_time
                FROM approval_trails at
                WHERE at.staff_id = ?
                  AND at.action IN ('approved', 'rejected')
                  AND at.is_archived = 0
                HAVING received_time IS NOT NULL
                  AND approval_time >= received_time
                ORDER BY at.created_at DESC
            ";
            
            $results = DB::select($sql, [$staffId]);
            
            if (empty($results)) {
                return 0;
            }
            
            $totalHours = 0;
            $count = 0;
            
            foreach ($results as $result) {
                try {
                    $approvalTime = Carbon::parse($result->approval_time);
                    $receivedTime = Carbon::parse($result->received_time);
                    
                    // Calculate seconds between when item was received at this level and when it was approved
                    $seconds = abs($approvalTime->getTimestamp() - $receivedTime->getTimestamp());
                    
                    // Convert to hours (with decimal precision)
                    $hours = $seconds / 3600;
                    
                    // Only count positive time differences (approval after receipt)
                    if ($approvalTime->getTimestamp() >= $receivedTime->getTimestamp()) {
                        $totalHours += $hours;
                        $count++;
                    }
                } catch (\Exception $e) {
                    // Skip invalid date entries
                    continue;
                }
            }
            
            return $count > 0 ? round($totalHours / $count, 2) : 0;
            
        } catch (\Exception $e) {
            Log::error('Error calculating average approval time (all): ' . $e->getMessage());
            return 0;
        }
    }
}