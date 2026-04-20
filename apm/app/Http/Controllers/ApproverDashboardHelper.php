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
                's.title',
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
                    's.title',
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
    protected function getPendingCountsForApprovers($approvers, $workflowDefinitionId, $docType = null, $divisionId = null, $year = null, $month = null)
    {
        $approversByStaffId = [];

        // Handle both collection and array inputs
        $approversList = is_array($approvers) ? $approvers : $approvers->toArray();

        // First pass: collect all approver data grouped by staff_id
        foreach ($approversList as $approver) {
            $approverObj = is_array($approver) ? (object) $approver : $approver;
            $staffId = $approverObj->staff_id;
            
            if (!isset($approversByStaffId[$staffId])) {
                $title = $approverObj->title ?? '';
                $fullName = trim(($title ? $title . ' ' : '') . $approverObj->fname . ' ' . $approverObj->lname);
                
                $approversByStaffId[$staffId] = [
                    'staff_id' => $staffId,
                    'approver_id' => $approverObj->approver_id,
                    'approver_name' => $fullName,
                    'approver_email' => $approverObj->work_email,
                    'photo' => self::normalizeStaffPhotoBasename($approverObj->photo ?? null),
                    'title' => $title,
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
                        'other_memo' => 0,
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

            if (empty($approversByStaffId[$staffId]['photo']) && !empty($approverObj->photo)) {
                $approversByStaffId[$staffId]['photo'] = self::normalizeStaffPhotoBasename($approverObj->photo);
            }
            
            // Note: Pending counts, total handled, and avg approval time are now calculated
            // across all workflows/levels in the second pass, so we don't need to calculate them per level here
        }

        // Get last approval date per approver from approval_trails (approved/rejected actions)
        $staffIds = array_keys($approversByStaffId);
        $lastApprovalDates = $this->getLastApprovalDatesByStaffIds($staffIds);

        // Second pass: build final array with combined data
        // Get pending counts across ALL workflows for each approver (matching pending-approvals logic)
        $approversWithCounts = [];
        foreach ($approversByStaffId as $staffId => $data) {
            // Get pending counts across ALL workflows using PendingApprovalsService logic
            $allPendingCounts = $this->getPendingCountsForApproverAll($staffId, $divisionId, $year, $month);
            
            // Use the aggregated counts from all workflows
            $data['pending_counts'] = $allPendingCounts;
            
            // Calculate total pending (sum of all pending counts)
            $totalPending = array_sum(array_diff_key($data['pending_counts'], ['total' => '', 'memos' => '']));
            
            // Calculate total handled across ALL workflows and levels for this approver
            $totalHandled = $this->getTotalHandledForApproverAll($staffId, $divisionId, $year, $month);
            
            // Calculate average approval time across ALL workflows and levels using approval_trails
            $avgApprovalTime = $this->getAverageApprovalTimeAll($staffId, $divisionId, $year, $month);
            
            // Sort roles and levels for display
            sort($data['levels']);
            sort($data['roles']);
            
            $lastApprovalRaw = $lastApprovalDates[$staffId] ?? null;
            $lastApprovalDisplay = $lastApprovalRaw ? Carbon::parse($lastApprovalRaw)->format('M j, Y g:i A') : null;

            $approversWithCounts[] = [
                'staff_id' => $data['staff_id'],
                'approver_id' => $data['approver_id'],
                'approver_name' => $data['approver_name'],
                'approver_email' => $data['approver_email'],
                'photo' => self::normalizeStaffPhotoBasename($data['photo'] ?? null),
                'title' => $data['title'] ?? '',
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
                'last_approval_date' => $lastApprovalRaw,
                'last_approval_date_display' => $lastApprovalDisplay,
            ];
        }

        return $approversWithCounts;
    }

    /**
     * Get the last approval/rejection date per staff_id from approval_trails.
     * Returns array keyed by staff_id with ISO datetime string or null.
     */
    protected function getLastApprovalDatesByStaffIds(array $staffIds): array
    {
        if (empty($staffIds)) {
            return [];
        }
        $rows = DB::table('approval_trails')
            ->whereIn('staff_id', $staffIds)
            ->whereIn('action', ['approved', 'rejected'])
            ->where('is_archived', 0)
            ->select('staff_id', DB::raw('MAX(updated_at) as last_approval_date'))
            ->groupBy('staff_id')
            ->get();
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->staff_id] = $row->last_approval_date;
        }
        return $result;
    }

    /**
     * Get pending counts for a specific approver across ALL workflows (matching pending-approvals logic).
     * This ensures counts match what's shown in pending-approvals and excludes already handled items.
     * Uses ApprovalService::canTakeAction to verify the approver can actually approve each item.
     */
    protected function getPendingCountsForApproverAll($staffId, $divisionId = null, $year = null, $month = null)
    {
        $counts = [
            'matrix' => 0,
            'non_travel' => 0,
            'single_memos' => 0,
            'special' => 0,
            'other_memo' => 0,
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
        
        if ($year) {
            $query->where('year', $year);
        }
        
        // Apply month filter by converting to quarter for matrices
        if ($month) {
            $quarter = 'Q' . ceil($month / 3);
            $query->where('quarter', $quarter);
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
        
        // Apply year filter by created_at year
        if ($year) {
            $query->whereYear('created_at', $year);
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
        
        // Apply year filter by created_at year
        if ($year) {
            $query->whereYear('created_at', $year);
        }
        
        $memos = $query->get();
        foreach ($memos as $memo) {
            if ($approvalService->canTakeAction($memo, $staffId)) {
                $counts['non_travel']++;
            }
        }

        // Get pending single memos (activities with is_single_memo = true, across all workflows)
        $query = \App\Models\Activity::with(['staff', 'division', 'matrix'])
            ->where('is_single_memo', true)
            ->where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);
        
        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }
        
        // Apply year filter through matrix relationship
        if ($year) {
            $query->whereHas('matrix', function($q) use ($year) {
                $q->where('year', $year);
            });
        }
        
        // Apply month filter through matrix relationship (convert month to quarter)
        if ($month) {
            $quarter = 'Q' . ceil($month / 3);
            $query->whereHas('matrix', function($q) use ($quarter) {
                $q->where('quarter', $quarter);
            });
        }
        
        $activities = $query->get();
        foreach ($activities as $activity) {
            if ($approvalService->canTakeAction($activity, $staffId)) {
                $counts['single_memos']++;
            }
        }

        // Pending other memos (explicit approver chain, not workflow definitions)
        $query = \App\Models\OtherMemo::query()
            ->where('overall_status', \App\Models\OtherMemo::STATUS_PENDING)
            ->whereNotNull('current_approver_staff_id');
        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }
        if ($year) {
            $query->whereYear('created_at', $year);
        }
        if ($month) {
            $query->whereMonth('created_at', $month);
        }
        foreach ($query->get() as $otherMemo) {
            if ($approvalService->canTakeAction($otherMemo, $staffId)) {
                $counts['other_memo']++;
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
        
        // Apply year filter by created_at year
        if ($year) {
            $query->whereYear('created_at', $year);
        }
        
        // Apply month filter by created_at month
        if ($month) {
            $query->whereMonth('created_at', $month);
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
        
        // Apply year filter by created_at year
        if ($year) {
            $query->whereYear('created_at', $year);
        }
        
        // Apply month filter by created_at month
        if ($month) {
            $query->whereMonth('created_at', $month);
        }
        
        $serviceRequests = $query->get();
        foreach ($serviceRequests as $serviceRequest) {
            if ($approvalService->canTakeAction($serviceRequest, $staffId)) {
                $counts['requests_for_service']++;
            }
        }

        // Get pending change requests (uses workflows 1, 6, 7)
        $query = \App\Models\ChangeRequest::with(['staff', 'division', 'forwardWorkflow', 'matrix'])
            ->where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);
        
        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }
        
        // Apply year filter through matrix relationship or created_at
        if ($year) {
            $query->where(function($q) use ($year) {
                // Filter by matrix year if change request has a matrix
                $q->whereHas('matrix', function($matrixQuery) use ($year) {
                    $matrixQuery->where('year', $year);
                })
                // Or filter by created_at year if no matrix
                ->orWhereYear('created_at', $year);
            });
        }
        
        // Apply month filter through matrix relationship (convert month to quarter) or created_at
        if ($month) {
            $quarter = 'Q' . ceil($month / 3);
            $query->where(function($q) use ($quarter, $month) {
                // Filter by matrix quarter if change request has a matrix
                $q->whereHas('matrix', function($matrixQuery) use ($quarter) {
                    $matrixQuery->where('quarter', $quarter);
                })
                // Or filter by created_at month if no matrix
                ->orWhereMonth('created_at', $month);
            });
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
            'other_memo' => 0,
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

        if (! $docType) {
            $query = \App\Models\OtherMemo::query()
                ->where('overall_status', \App\Models\OtherMemo::STATUS_PENDING)
                ->whereNotNull('current_approver_staff_id');
            if ($divisionId) {
                $query->where('division_id', $divisionId);
            }
            foreach ($query->get() as $otherMemo) {
                if ($approvalService->canTakeAction($otherMemo, $staffId)) {
                    $counts['other_memo']++;
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
     * Received time: for level 1 = last submission before this approval (handles return/resubmit);
     * for level >= 2 = last approval action of the previous level that occurred before this approval.
     */
    protected function getAverageApprovalTime($approverId, $levelNo, $workflowId, $docType = null)
    {
        try {
            // No date filtering needed for approval time calculation

            // Get approval times for this approver at this level.
            // Received = when they actually received the document: for level 1 the most recent submission
            // before this approval; for level >= 2 the previous level's last approval action before this approval.
            if ($levelNo == 1) {
                $sql = "
                    SELECT 
                        at.model_id,
                        at.model_type,
                        at.updated_at as approval_time,
                        (SELECT MAX(sub_at.updated_at)
                         FROM approval_trails sub_at
                         WHERE sub_at.model_type = at.model_type
                           AND sub_at.model_id = at.model_id
                           AND (
                               sub_at.forward_workflow_id = at.forward_workflow_id
                               OR (sub_at.forward_workflow_id IS NULL AND at.model_type = 'App\\\\Models\\\\Matrix' AND (SELECT m.forward_workflow_id FROM matrices m WHERE m.id = at.model_id LIMIT 1) = at.forward_workflow_id)
                               OR (sub_at.forward_workflow_id IS NULL AND at.model_type = 'App\\\\Models\\\\Activity' AND (SELECT a.forward_workflow_id FROM activities a WHERE a.id = at.model_id LIMIT 1) = at.forward_workflow_id)
                               OR (sub_at.forward_workflow_id IS NULL AND at.model_type NOT IN ('App\\\\Models\\\\Matrix', 'App\\\\Models\\\\Activity') AND at.forward_workflow_id IS NOT NULL)
                           )
                           AND sub_at.approval_order = 0
                           AND sub_at.action = 'submitted'
                           AND sub_at.is_archived = 0
                           AND sub_at.updated_at <= at.updated_at
                        ) as submitted_time
                    FROM approval_trails at
                    WHERE at.forward_workflow_id = ?
                    AND at.approval_order = ?
                    AND at.staff_id = ?
                    AND at.action IN ('approved', 'rejected')
                    AND at.is_archived = 0
                ";
                $params = [$workflowId, $levelNo, $approverId];
            } else {
                $sql = "
                    SELECT 
                        at.model_id,
                        at.model_type,
                        at.updated_at as approval_time,
                        (SELECT MAX(prev_at.updated_at)
                         FROM approval_trails prev_at
                         WHERE prev_at.model_type = at.model_type
                           AND prev_at.model_id = at.model_id
                           AND prev_at.forward_workflow_id = at.forward_workflow_id
                           AND prev_at.approval_order < at.approval_order
                           AND prev_at.action IN ('approved', 'rejected')
                           AND prev_at.is_archived = 0
                           AND prev_at.updated_at <= at.updated_at
                        ) as submitted_time
                    FROM approval_trails at
                    WHERE at.forward_workflow_id = ?
                    AND at.approval_order = ?
                    AND at.staff_id = ?
                    AND at.action IN ('approved', 'rejected')
                    AND at.is_archived = 0
                ";
                $params = [$workflowId, $levelNo, $approverId];
            }
            $results = DB::select($sql, $params);

            if (empty($results)) {
                return 0;
            }

            $totalHours = 0;
            $count = 0;

            foreach ($results as $result) {
                if (empty($result->submitted_time)) {
                    continue;
                }
                $approvalTime = Carbon::parse($result->approval_time);
                $submittedTime = Carbon::parse($result->submitted_time);
                
                // Calculate seconds between when document was received at this level and when it was approved
                $seconds = abs($approvalTime->getTimestamp() - $submittedTime->getTimestamp());
                
                // Convert to hours (with decimal precision)
                $hours = $seconds / 3600;
                
                // Only count when approval is after receipt
                if ($approvalTime->getTimestamp() >= $submittedTime->getTimestamp()) {
                    $totalHours += $hours;
                    $count++;
                }
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
     * Get average approval time by workflow for the dashboard.
     * Returns for each workflow: workflow name, memo/document count, average approval time (hours and display).
     * Approval time = from submitted (approval_order=0) to last approved/rejected for that document.
     */
    protected function getAverageApprovalTimeByWorkflow()
    {
        try {
            $workflows = DB::table('workflows')
                ->select('id', 'workflow_name')
                ->orderBy('workflow_name')
                ->get();

            $result = [];
            foreach ($workflows as $wf) {
                $workflowId = $wf->id;
                // Documents that have been submitted and have at least one approved/rejected in this workflow
                $rows = DB::select("
                    SELECT 
                        at.model_id,
                        at.model_type,
                        MIN(CASE WHEN at.action = 'submitted' AND at.approval_order = 0 THEN at.updated_at END) AS submitted_time,
                        MAX(CASE WHEN at.action IN ('approved', 'rejected') THEN at.updated_at END) AS last_approval_time
                    FROM approval_trails at
                    WHERE at.forward_workflow_id = ?
                    AND at.is_archived = 0
                    AND (
                        (at.action = 'submitted' AND at.approval_order = 0)
                        OR at.action IN ('approved', 'rejected')
                    )
                    GROUP BY at.model_id, at.model_type
                    HAVING submitted_time IS NOT NULL AND last_approval_time IS NOT NULL AND last_approval_time >= submitted_time
                ", [$workflowId]);

                $totalHours = 0;
                $count = 0;
                foreach ($rows as $row) {
                    $submitted = Carbon::parse($row->submitted_time);
                    $lastApproval = Carbon::parse($row->last_approval_time);
                    $totalHours += $submitted->diffInSeconds($lastApproval) / 3600;
                    $count++;
                }

                $avgHours = $count > 0 ? round($totalHours / $count, 2) : 0;
                $result[] = $this->formatWorkflowStatRow($wf->workflow_name, (int) $workflowId, $count, $avgHours, []);
            }

            $otherMemoAgg = $this->getOtherMemoApprovalDurationAggregates(null, null, null, null);
            $this->mergeOtherMemoIntoWorkflowStats($result, $otherMemoAgg);

            return $result;
        } catch (\Exception $e) {
            Log::error('Error calculating average approval time by workflow: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Basename for staff-uploads/photo ?f= (matches StaffPhotoRoute / CI3 uploads/staff).
     */
    private static function normalizeStaffPhotoBasename($photo): ?string
    {
        if ($photo === null || $photo === '') {
            return null;
        }
        $base = basename(str_replace('\\', '/', trim((string) $photo)));
        if ($base === '' || $base === '.' || $base === '..') {
            return null;
        }

        return $base;
    }

    protected static function getModelTypeByDocType($docType)
    {
        $map = [
            'matrix' => 'App\\Models\\Matrix',
            'non_travel' => 'App\\Models\\NonTravelMemo',
            'single_memos' => 'App\\Models\\Activity',
            'special' => 'App\\Models\\SpecialMemo',
            'arf' => 'App\\Models\\RequestARF',
            'requests_for_service' => 'App\\Models\\ServiceRequest',
            'change_requests' => 'App\\Models\\ChangeRequest',
            'other_memo' => 'App\\Models\\OtherMemo',
        ];
        return $map[$docType] ?? null;
    }

    /** Model type (full class) to human-readable label for workflow doc-type listing. */
    protected static function getDocTypeLabelByModelType($modelType)
    {
        $map = [
            'App\\Models\\Matrix' => 'Matrix',
            'App\\Models\\NonTravelMemo' => 'Non-Travel Memo',
            'App\\Models\\Activity' => 'Single Memo',
            'App\\Models\\SpecialMemo' => 'Special Memo',
            'App\\Models\\RequestARF' => 'ARF',
            'App\\Models\\ServiceRequest' => 'Request for Service',
            'App\\Models\\ChangeRequest' => 'Change Request',
            'App\\Models\\OtherMemo' => 'Other Memo',
        ];
        return $map[$modelType ?? ''] ?? $modelType;
    }

    /**
     * Distinct enabled workflow-definition role names for this workflow, comma-separated (approval order preserved).
     */
    protected function getUniqueApproverRolesForWorkflow(int $workflowId): string
    {
        try {
            $roles = DB::table('workflow_definition')
                ->where('workflow_id', $workflowId)
                ->where('is_enabled', 1)
                ->orderBy('approval_order')
                ->pluck('role')
                ->unique()
                ->filter(fn ($r) => $r !== null && $r !== '')
                ->values()
                ->all();

            return implode(', ', $roles);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * One workflow chart/table row including avg_days for chart unit toggle.
     *
     * @param  list<string>  $docTypeLabels
     */
    protected function formatWorkflowStatRow(string $workflowName, int $workflowId, int $memosCount, float $avgHours, array $docTypeLabels): array
    {
        return [
            'workflow_name' => $workflowName,
            'workflow_id' => $workflowId,
            'approver_roles' => $this->getUniqueApproverRolesForWorkflow($workflowId),
            'memos' => $memosCount,
            'avg_hours' => $avgHours,
            'avg_days' => $avgHours > 0 ? round($avgHours / 24, 4) : 0,
            'avg_display' => $this->formatApprovalTime($avgHours),
            'doc_type_labels' => array_values(array_unique($docTypeLabels)),
        ];
    }

    /**
     * Approved Other Memos: time from submitted_at to approved_at (not on approval_trails).
     *
     * @return array{count: int, total_hours: float, workflow_id: int|null}
     */
    protected function getOtherMemoApprovalDurationAggregates(?int $divisionId, ?string $docType, ?int $year, ?int $month): array
    {
        if ($docType && ! in_array($docType, ['memos', 'other_memo', ''], true)) {
            return ['count' => 0, 'total_hours' => 0.0, 'workflow_id' => null];
        }

        $workflowId = \App\Models\WorkflowModel::getWorkflowIdForModel('OtherMemo');

        $q = \App\Models\OtherMemo::query()
            ->where('overall_status', \App\Models\OtherMemo::STATUS_APPROVED)
            ->whereNotNull('submitted_at')
            ->whereNotNull('approved_at');

        if ($divisionId) {
            $q->where('division_id', $divisionId);
        }
        if ($year) {
            $q->whereYear('submitted_at', $year);
        }
        if ($month) {
            $q->whereMonth('submitted_at', $month);
        }

        $totalHours = 0.0;
        $count = 0;
        foreach ($q->cursor() as $om) {
            $totalHours += Carbon::parse($om->submitted_at)->diffInSeconds(Carbon::parse($om->approved_at)) / 3600.0;
            $count++;
        }

        return [
            'count' => $count,
            'total_hours' => $totalHours,
            'workflow_id' => $workflowId ? (int) $workflowId : null,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $workflows
     * @return list<array<string, mixed>>
     */
    protected function getWorkflowStatsOtherMemoOnlyRows($workflows, ?int $divisionId, ?int $year, ?int $month): array
    {
        $om = $this->getOtherMemoApprovalDurationAggregates($divisionId, 'other_memo', $year, $month);
        if (! $om['workflow_id'] || $om['count'] < 1) {
            return [];
        }
        $wf = $workflows->firstWhere('id', (int) $om['workflow_id']);
        if (! $wf) {
            return [];
        }
        $avgHours = round($om['total_hours'] / $om['count'], 2);

        return [$this->formatWorkflowStatRow($wf->workflow_name, (int) $wf->id, $om['count'], $avgHours, ['Other Memo'])];
    }

    /**
     * Merge Other Memo cycle-time stats into the workflow row that owns this model (workflow_models).
     *
     * @param  list<array<string, mixed>>  $result
     */
    protected function mergeOtherMemoIntoWorkflowStats(array &$result, array $otherAgg): void
    {
        if ($otherAgg['count'] < 1 || ! $otherAgg['workflow_id']) {
            return;
        }

        $found = false;
        foreach ($result as $idx => $row) {
            if ((int) ($row['workflow_id'] ?? 0) !== (int) $otherAgg['workflow_id']) {
                continue;
            }
            $found = true;
            $c1 = (int) ($row['memos'] ?? 0);
            $h1 = (float) ($row['avg_hours'] ?? 0) * $c1;
            $c2 = (int) $otherAgg['count'];
            $h2 = (float) $otherAgg['total_hours'];
            $tc = $c1 + $c2;
            $avgHours = $tc > 0 ? round(($h1 + $h2) / $tc, 2) : 0.0;
            $labels = $row['doc_type_labels'] ?? [];
            if (! in_array('Other Memo', $labels, true)) {
                $labels[] = 'Other Memo';
            }
            sort($labels);
            $result[$idx] = $this->formatWorkflowStatRow(
                (string) $row['workflow_name'],
                (int) $row['workflow_id'],
                $tc,
                $avgHours,
                $labels
            );
            break;
        }

        if (! $found) {
            $wf = DB::table('workflows')->where('id', (int) $otherAgg['workflow_id'])->first();
            if ($wf) {
                $avgHours = round($otherAgg['total_hours'] / $otherAgg['count'], 2);
                $result[] = $this->formatWorkflowStatRow(
                    (string) $wf->workflow_name,
                    (int) $wf->id,
                    (int) $otherAgg['count'],
                    $avgHours,
                    ['Other Memo']
                );
            }
        }
    }

    /**
     * Get average approval time by workflow with filters (division, doc_type, year, month).
     */
    protected function getAverageApprovalTimeByWorkflowFiltered($divisionId = null, $docType = null, $year = null, $month = null)
    {
        try {
            $workflows = DB::table('workflows')
                ->select('id', 'workflow_name')
                ->orderBy('workflow_name')
                ->get();

            if ($docType === 'other_memo') {
                return $this->getWorkflowStatsOtherMemoOnlyRows($workflows, $divisionId, $year, $month);
            }

            $modelType = $docType ? self::getModelTypeByDocType($docType) : null;
            $quarter = ($month && $year) ? 'Q' . ceil($month / 3) : null;

            $result = [];
            foreach ($workflows as $wf) {
                $workflowId = $wf->id;

                $query = DB::table('approval_trails as at')
                    ->select(
                        'at.model_id',
                        'at.model_type',
                        DB::raw("MIN(CASE WHEN at.action = 'submitted' THEN at.updated_at END) AS submitted_time"),
                        DB::raw("MAX(CASE WHEN at.action = 'approved' THEN at.updated_at END) AS last_approval_time")
                    )
                    // Match by trail's workflow OR by document's workflow (same approach as General workflow memos)
                    ->where(function ($q) use ($workflowId) {
                        $q->where('at.forward_workflow_id', $workflowId)
                            ->orWhere(function ($q2) use ($workflowId) {
                                $q2->where('at.model_type', 'App\\Models\\Matrix')
                                    ->whereExists(function ($ex) use ($workflowId) {
                                        $ex->select(DB::raw(1))->from('matrices as m')
                                            ->whereColumn('m.id', 'at.model_id')
                                            ->where('m.forward_workflow_id', $workflowId);
                                    });
                            })
                            ->orWhere(function ($q2) use ($workflowId) {
                                $q2->where('at.model_type', 'App\\Models\\Activity')
                                    ->whereExists(function ($ex) use ($workflowId) {
                                        $ex->select(DB::raw(1))->from('activities as a')
                                            ->whereColumn('a.id', 'at.model_id')
                                            ->where('a.forward_workflow_id', $workflowId);
                                    });
                            })
                            ->orWhere(function ($q2) use ($workflowId) {
                                $q2->where('at.model_type', 'App\\Models\\NonTravelMemo')
                                    ->whereExists(function ($ex) use ($workflowId) {
                                        $ex->select(DB::raw(1))->from('non_travel_memos as n')
                                            ->whereColumn('n.id', 'at.model_id')
                                            ->where('n.forward_workflow_id', $workflowId);
                                    });
                            })
                            ->orWhere(function ($q2) use ($workflowId) {
                                $q2->where('at.model_type', 'App\\Models\\SpecialMemo')
                                    ->whereExists(function ($ex) use ($workflowId) {
                                        $ex->select(DB::raw(1))->from('special_memos as s')
                                            ->whereColumn('s.id', 'at.model_id')
                                            ->where('s.forward_workflow_id', $workflowId);
                                    });
                            })
                            ->orWhere(function ($q2) use ($workflowId) {
                                $q2->where('at.model_type', 'App\\Models\\ServiceRequest')
                                    ->whereExists(function ($ex) use ($workflowId) {
                                        $ex->select(DB::raw(1))->from('service_requests as sr')
                                            ->whereColumn('sr.id', 'at.model_id')
                                            ->where('sr.forward_workflow_id', $workflowId);
                                    });
                            })
                            ->orWhere(function ($q2) use ($workflowId) {
                                $q2->where('at.model_type', 'App\\Models\\RequestARF')
                                    ->whereExists(function ($ex) use ($workflowId) {
                                        $ex->select(DB::raw(1))->from('request_arfs as r')
                                            ->whereColumn('r.id', 'at.model_id')
                                            ->where('r.forward_workflow_id', $workflowId);
                                    });
                            })
                            ->orWhere(function ($q2) use ($workflowId) {
                                $q2->where('at.model_type', 'App\\Models\\ChangeRequest')
                                    ->whereExists(function ($ex) use ($workflowId) {
                                        $ex->select(DB::raw(1))->from('change_request as c')
                                            ->whereColumn('c.id', 'at.model_id')
                                            ->where('c.forward_workflow_id', $workflowId);
                                    });
                            });
                    })
                    ->where('at.is_archived', 0)
                    ->whereIn('at.action', ['submitted', 'approved']);

                // Only documents that are fully approved (overall_status = 'approved')
                $query->where(function ($q) {
                    $q->where('at.model_type', 'App\\Models\\Matrix')
                        ->whereExists(function ($ex) {
                            $ex->select(DB::raw(1))->from('matrices as m')
                                ->whereColumn('m.id', 'at.model_id')
                                ->where('m.overall_status', 'approved');
                        })
                        ->orWhere(function ($q2) {
                            $q2->where('at.model_type', 'App\\Models\\Activity')
                                ->whereExists(function ($ex) {
                                    $ex->select(DB::raw(1))->from('activities as a')
                                        ->whereColumn('a.id', 'at.model_id')
                                        ->where('a.overall_status', 'approved');
                                });
                        })
                        ->orWhere(function ($q2) {
                            $q2->where('at.model_type', 'App\\Models\\NonTravelMemo')
                                ->whereExists(function ($ex) {
                                    $ex->select(DB::raw(1))->from('non_travel_memos as n')
                                        ->whereColumn('n.id', 'at.model_id')
                                        ->where('n.overall_status', 'approved');
                                });
                        })
                        ->orWhere(function ($q2) {
                            $q2->where('at.model_type', 'App\\Models\\SpecialMemo')
                                ->whereExists(function ($ex) {
                                    $ex->select(DB::raw(1))->from('special_memos as s')
                                        ->whereColumn('s.id', 'at.model_id')
                                        ->where('s.overall_status', 'approved');
                                });
                        })
                        ->orWhere(function ($q2) {
                            $q2->where('at.model_type', 'App\\Models\\RequestARF')
                                ->whereExists(function ($ex) {
                                    $ex->select(DB::raw(1))->from('request_arfs as r')
                                        ->whereColumn('r.id', 'at.model_id')
                                        ->where('r.overall_status', 'approved')
                                        ->where(function ($parent) {
                                            // Require parent memo overall_status = 'approved' when parent exists
                                            $parent->where(function ($noParent) {
                                                $noParent->whereNull('r.source_id')->orWhereNull('r.model_type');
                                            })
                                            ->orWhere(function ($act) {
                                                $act->where('r.model_type', 'App\\Models\\Activity')
                                                    ->whereExists(function ($pa) {
                                                        $pa->select(DB::raw(1))->from('activities as pa')
                                                            ->whereColumn('pa.id', 'r.source_id')
                                                            ->where('pa.overall_status', 'approved');
                                                    });
                                            })
                                            ->orWhere(function ($nt) {
                                                $nt->where('r.model_type', 'App\\Models\\NonTravelMemo')
                                                    ->whereExists(function ($pa) {
                                                        $pa->select(DB::raw(1))->from('non_travel_memos as pa')
                                                            ->whereColumn('pa.id', 'r.source_id')
                                                            ->where('pa.overall_status', 'approved');
                                                    });
                                            })
                                            ->orWhere(function ($sm) {
                                                $sm->where('r.model_type', 'App\\Models\\SpecialMemo')
                                                    ->whereExists(function ($pa) {
                                                        $pa->select(DB::raw(1))->from('special_memos as pa')
                                                            ->whereColumn('pa.id', 'r.source_id')
                                                            ->where('pa.overall_status', 'approved');
                                                    });
                                            });
                                        });
                                });
                        })
                        ->orWhere(function ($q2) {
                            $q2->where('at.model_type', 'App\\Models\\ServiceRequest')
                                ->whereExists(function ($ex) {
                                    $ex->select(DB::raw(1))->from('service_requests as sr')
                                        ->whereColumn('sr.id', 'at.model_id')
                                        ->where('sr.overall_status', 'approved')
                                        ->where(function ($parent) {
                                            // Require parent memo overall_status = 'approved' when parent exists
                                            $parent->where(function ($noParent) {
                                                $noParent->whereNull('sr.source_id')->whereNull('sr.source_type')->whereNull('sr.activity_id');
                                            })
                                            ->orWhere(function ($act) {
                                                $act->where(function ($a) {
                                                    $a->whereNotNull('sr.activity_id')
                                                        ->whereExists(function ($pa) {
                                                            $pa->select(DB::raw(1))->from('activities as pa')
                                                                ->whereColumn('pa.id', 'sr.activity_id')
                                                                ->where('pa.overall_status', 'approved');
                                                        });
                                                })->orWhere(function ($a) {
                                                    $a->where('sr.source_type', 'activity')
                                                        ->whereNotNull('sr.source_id')
                                                        ->whereExists(function ($pa) {
                                                            $pa->select(DB::raw(1))->from('activities as pa')
                                                                ->whereColumn('pa.id', 'sr.source_id')
                                                                ->where('pa.overall_status', 'approved');
                                                        });
                                                });
                                            })
                                            ->orWhere(function ($sm) {
                                                $sm->where('sr.source_type', 'special_memo')
                                                    ->whereNotNull('sr.source_id')
                                                    ->whereExists(function ($pa) {
                                                        $pa->select(DB::raw(1))->from('special_memos as pa')
                                                            ->whereColumn('pa.id', 'sr.source_id')
                                                            ->where('pa.overall_status', 'approved');
                                                    });
                                            })
                                            ->orWhere(function ($nt) {
                                                $nt->where('sr.source_type', 'non_travel_memo')
                                                    ->whereNotNull('sr.source_id')
                                                    ->whereExists(function ($pa) {
                                                        $pa->select(DB::raw(1))->from('non_travel_memos as pa')
                                                            ->whereColumn('pa.id', 'sr.source_id')
                                                            ->where('pa.overall_status', 'approved');
                                                    });
                                            });
                                        });
                                });
                        })
                        ->orWhere(function ($q2) {
                            $q2->where('at.model_type', 'App\\Models\\ChangeRequest')
                                ->whereExists(function ($ex) {
                                    $ex->select(DB::raw(1))->from('change_request as c')
                                        ->whereColumn('c.id', 'at.model_id')
                                        ->where('c.overall_status', 'approved');
                                });
                        });
                });

                if ($modelType) {
                    $query->where('at.model_type', $modelType);
                }

                if ($divisionId) {
                    $query->whereExists(function ($ex) use ($divisionId) {
                        $ex->select(DB::raw(1))
                            ->from('approval_trails as sub')
                            ->join('staff as st', 'st.staff_id', '=', 'sub.staff_id')
                            ->whereColumn('sub.model_id', 'at.model_id')
                            ->whereColumn('sub.model_type', 'at.model_type')
                            ->where(function ($q) {
                                $q->whereColumn('sub.forward_workflow_id', 'at.forward_workflow_id')
                                    ->orWhere(function ($q2) {
                                        $q2->whereNull('sub.forward_workflow_id')->whereNull('at.forward_workflow_id');
                                    })
                                    ->orWhere(function ($q2) {
                                        $q2->where('at.model_type', 'App\\Models\\Matrix')
                                            ->whereNull('at.forward_workflow_id')
                                            ->whereExists(function ($mx) {
                                                $mx->select(DB::raw(1))->from('matrices as m')
                                                    ->whereColumn('m.id', 'at.model_id')
                                                    ->where(function ($mq) {
                                                        $mq->whereColumn('m.forward_workflow_id', 'sub.forward_workflow_id')
                                                            ->orWhereNull('sub.forward_workflow_id');
                                                    });
                                            });
                                    })
                                    ->orWhere(function ($q2) {
                                        $q2->where('at.model_type', 'App\\Models\\Activity')
                                            ->whereNull('at.forward_workflow_id')
                                            ->whereExists(function ($ax) {
                                                $ax->select(DB::raw(1))->from('activities as a')
                                                    ->whereColumn('a.id', 'at.model_id')
                                                    ->where(function ($aq) {
                                                        $aq->whereColumn('a.forward_workflow_id', 'sub.forward_workflow_id')
                                                            ->orWhereNull('sub.forward_workflow_id');
                                                    });
                                            });
                                    })
                                    ->orWhere(function ($q2) {
                                        $q2->where('at.model_type', 'App\\Models\\ServiceRequest')
                                            ->whereNull('at.forward_workflow_id')
                                            ->whereExists(function ($srx) {
                                                $srx->select(DB::raw(1))->from('service_requests as sr')
                                                    ->whereColumn('sr.id', 'at.model_id')
                                                    ->where(function ($srq) {
                                                        $srq->whereColumn('sr.forward_workflow_id', 'sub.forward_workflow_id')
                                                            ->orWhereNull('sub.forward_workflow_id');
                                                    });
                                            });
                                    })
                                    ->orWhere(function ($q2) {
                                        $q2->where('at.model_type', 'App\\Models\\RequestARF')
                                            ->whereNull('at.forward_workflow_id')
                                            ->whereExists(function ($rx) {
                                                $rx->select(DB::raw(1))->from('request_arfs as r')
                                                    ->whereColumn('r.id', 'at.model_id')
                                                    ->where(function ($rq) {
                                                        $rq->whereColumn('r.forward_workflow_id', 'sub.forward_workflow_id')
                                                            ->orWhereNull('sub.forward_workflow_id');
                                                    });
                                            });
                                    });
                            })
                            ->where('sub.action', 'submitted')
                            ->where('sub.is_archived', 0)
                            ->where('st.division_id', $divisionId);
                    });
                }

                if ($year || $month) {
                    $query->where(function ($q) use ($year, $quarter, $month) {
                        $q->where(function ($subQ) use ($year, $quarter) {
                            $subQ->where('at.model_type', 'App\\Models\\Matrix')
                                ->whereExists(function ($exists) use ($year, $quarter) {
                                    $exists->select(DB::raw(1))->from('matrices as m')->whereColumn('m.id', 'at.model_id');
                                    if ($year) $exists->where('m.year', $year);
                                    if ($quarter) $exists->where('m.quarter', $quarter);
                                });
                        })
                        ->orWhere(function ($subQ) use ($year, $quarter) {
                            $subQ->where('at.model_type', 'App\\Models\\Activity')
                                ->whereExists(function ($exists) use ($year, $quarter) {
                                    $exists->select(DB::raw(1))
                                        ->from('activities as a')
                                        ->join('matrices as m', 'm.id', '=', 'a.matrix_id')
                                        ->whereColumn('a.id', 'at.model_id');
                                    if ($year) $exists->where('m.year', $year);
                                    if ($quarter) $exists->where('m.quarter', $quarter);
                                });
                        })
                        ->orWhere(function ($subQ) use ($year, $quarter) {
                            $subQ->where('at.model_type', 'App\\Models\\ChangeRequest')
                                ->whereExists(function ($exists) use ($year, $quarter) {
                                    $exists->select(DB::raw(1))
                                        ->from('change_request as cr')
                                        ->join('matrices as m', 'm.id', '=', 'cr.matrix_id')
                                        ->whereColumn('cr.id', 'at.model_id');
                                    if ($year) $exists->where('m.year', $year);
                                    if ($quarter) $exists->where('m.quarter', $quarter);
                                });
                        })
                        ->orWhere(function ($subQ) use ($year, $month) {
                            $subQ->where('at.model_type', 'App\\Models\\NonTravelMemo')
                                ->whereExists(function ($exists) use ($year, $month) {
                                    $exists->select(DB::raw(1))->from('non_travel_memos as n')
                                        ->whereColumn('n.id', 'at.model_id');
                                    if ($year) $exists->whereYear('n.created_at', $year);
                                    if ($month) $exists->whereMonth('n.created_at', $month);
                                });
                        })
                        ->orWhere(function ($subQ) use ($year, $month) {
                            $subQ->where('at.model_type', 'App\\Models\\SpecialMemo')
                                ->whereExists(function ($exists) use ($year, $month) {
                                    $exists->select(DB::raw(1))->from('special_memos as s')
                                        ->whereColumn('s.id', 'at.model_id');
                                    if ($year) $exists->whereYear('s.created_at', $year);
                                    if ($month) $exists->whereMonth('s.created_at', $month);
                                });
                        })
                        ->orWhere(function ($subQ) use ($year, $month) {
                            $subQ->where('at.model_type', 'App\\Models\\RequestARF')
                                ->whereExists(function ($exists) use ($year, $month) {
                                    $exists->select(DB::raw(1))->from('request_arfs as r')
                                        ->whereColumn('r.id', 'at.model_id');
                                    if ($year) $exists->whereYear('r.created_at', $year);
                                    if ($month) $exists->whereMonth('r.created_at', $month);
                                });
                        })
                        ->orWhere(function ($subQ) use ($year, $month) {
                            $subQ->where('at.model_type', 'App\\Models\\ServiceRequest')
                                ->whereExists(function ($exists) use ($year, $month) {
                                    $exists->select(DB::raw(1))->from('service_requests as sr')
                                        ->whereColumn('sr.id', 'at.model_id');
                                    if ($year) $exists->whereYear('sr.created_at', $year);
                                    if ($month) $exists->whereMonth('sr.created_at', $month);
                                });
                        });
                    });
                }

                $query->groupBy('at.model_id', 'at.model_type');
                $query->havingNotNull(DB::raw("MIN(CASE WHEN at.action = 'submitted' THEN at.updated_at END)"));
                $query->havingNotNull(DB::raw("MAX(CASE WHEN at.action = 'approved' THEN at.updated_at END)"));
                $query->havingRaw("MAX(CASE WHEN at.action = 'approved' THEN at.updated_at END) >= MIN(CASE WHEN at.action = 'submitted' THEN at.updated_at END)");

                $rows = $query->get();

                $totalHours = 0;
                $count = 0;
                $modelTypesSeen = [];
                foreach ($rows as $row) {
                    if (!$row->submitted_time || !$row->last_approval_time) continue;
                    $submitted = Carbon::parse($row->submitted_time);
                    $lastApproval = Carbon::parse($row->last_approval_time);
                    $totalHours += $submitted->diffInSeconds($lastApproval) / 3600;
                    $count++;
                    if (!empty($row->model_type)) {
                        $modelTypesSeen[$row->model_type] = true;
                    }
                }
                $docTypeLabels = array_values(array_unique(array_map(function ($mt) {
                    return self::getDocTypeLabelByModelType($mt);
                }, array_keys($modelTypesSeen))));
                sort($docTypeLabels);

                $avgHours = $count > 0 ? round($totalHours / $count, 2) : 0;
                $result[] = $this->formatWorkflowStatRow(
                    $wf->workflow_name,
                    (int) $workflowId,
                    $count,
                    $avgHours,
                    $docTypeLabels
                );
            }

            $otherMemoAgg = $this->getOtherMemoApprovalDurationAggregates($divisionId, $docType, $year, $month);
            $this->mergeOtherMemoIntoWorkflowStats($result, $otherMemoAgg);

            return $result;
        } catch (\Exception $e) {
            Log::error('Error calculating average approval time by workflow (filtered): ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get total handled documents for a specific approver across ALL workflows and levels.
     * This counts all documents that have been approved/rejected by this approver using approval_trails.
     */
    protected function getTotalHandledForApproverAll($staffId, $divisionId = null, $year = null, $month = null)
    {
        try {
            // Count all documents handled by this approver across all workflows and levels
            // Using approval_trails where staff_id matches and action is approved/rejected
            $query = DB::table('approval_trails as at')
                ->where('at.staff_id', $staffId)
                ->whereIn('at.action', ['approved', 'rejected'])
                ->where('at.is_archived', 0); // Only non-archived trails
            
            // Apply year and month filters
            if ($year || $month) {
                $query->where(function($q) use ($year, $month) {
                    $quarter = $month ? 'Q' . ceil($month / 3) : null;
                    
                    // For matrices, filter by matrix year and quarter
                    $q->where(function($subQ) use ($year, $quarter) {
                        $subQ->where('at.model_type', 'App\\Models\\Matrix')
                             ->whereExists(function($exists) use ($year, $quarter) {
                                 $exists->select(DB::raw(1))
                                       ->from('matrices as m')
                                       ->whereColumn('m.id', 'at.model_id');
                                 if ($year) {
                                     $exists->where('m.year', $year);
                                 }
                                 if ($quarter) {
                                     $exists->where('m.quarter', $quarter);
                                 }
                             });
                    })
                    // For activities (single memos), filter by matrix year and quarter
                    ->orWhere(function($subQ) use ($year, $quarter) {
                        $subQ->where('at.model_type', 'App\\Models\\Activity')
                             ->whereExists(function($exists) use ($year, $quarter) {
                                 $exists->select(DB::raw(1))
                                       ->from('activities as a')
                                       ->join('matrices as m', 'm.id', '=', 'a.matrix_id')
                                       ->whereColumn('a.id', 'at.model_id');
                                 if ($year) {
                                     $exists->where('m.year', $year);
                                 }
                                 if ($quarter) {
                                     $exists->where('m.quarter', $quarter);
                                 }
                             });
                    })
                    // For change requests with matrix, filter by matrix year and quarter
                    ->orWhere(function($subQ) use ($year, $quarter) {
                        $subQ->where('at.model_type', 'App\\Models\\ChangeRequest')
                             ->whereExists(function($exists) use ($year, $quarter) {
                                 $exists->select(DB::raw(1))
                                       ->from('change_request as cr')
                                       ->join('matrices as m', 'm.id', '=', 'cr.matrix_id')
                                       ->whereColumn('cr.id', 'at.model_id');
                                 if ($year) {
                                     $exists->where('m.year', $year);
                                 }
                                 if ($quarter) {
                                     $exists->where('m.quarter', $quarter);
                                 }
                             });
                    })
                    // For other types, filter by created_at year and month
                    ->orWhere(function($subQ) use ($year, $month) {
                        $subQ->whereNotIn('at.model_type', ['App\\Models\\Matrix', 'App\\Models\\Activity', 'App\\Models\\ChangeRequest']);
                        if ($year) {
                            $subQ->whereYear('at.created_at', $year);
                        }
                        if ($month) {
                            $subQ->whereMonth('at.created_at', $month);
                        }
                    });
                });
            }
            
            $result = $query->select(DB::raw('COUNT(DISTINCT CONCAT(at.model_type, "-", at.model_id)) as total_count'))
                ->first();

            $base = (int) ($result->total_count ?? 0);

            return $base + $this->getTotalHandledOtherMemoForApprover((int) $staffId, $divisionId, $year, $month);
            
        } catch (\Exception $e) {
            Log::error('Error calculating total handled for approver (all): ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Other Memo actions (approved/rejected) by this staff on other_memos_approval_trails.
     */
    protected function getTotalHandledOtherMemoForApprover(int $staffId, ?int $divisionId, ?int $year, ?int $month): int
    {
        try {
            $q = DB::table('other_memos_approval_trails as t')
                ->join('other_memos as om', 'om.id', '=', 't.other_memo_id')
                ->where('t.staff_id', $staffId)
                ->whereIn('t.action', ['approved', 'rejected']);

            if ($divisionId) {
                $q->where('om.division_id', $divisionId);
            }
            if ($year) {
                $q->whereYear('t.created_at', $year);
            }
            if ($month) {
                $q->whereMonth('t.created_at', $month);
            }

            return (int) $q->select(DB::raw('COUNT(DISTINCT om.id) as cnt'))->value('cnt');
        } catch (\Exception $e) {
            Log::error('Error calculating total handled other memo for approver: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Get average approval time for a specific approver across ALL workflows and levels.
     * Calculates time between when item reached approver's level and when they approved it.
     * Level skipping (by 1 or 2 levels) is handled at every level: we use the last approval at ANY lower order.
     *
     * - Order 1: received = most recent submission before this approval (handles return/resubmit).
     * - Order 2: received = last approval at order 1 if any; else submission (handles level 1 skipped).
     * - Order 3+: received = last approval at any lower order (prev_at.approval_order < at.approval_order).
     *   So e.g. level 9 uses when level 8, 7 or 6 actually approved; level 12 uses when 11 or 10 approved.
     *   We never use submission for order >= 3, so delay at earlier levels is not attributed to this approver.
     * - Approval time: current approver's updated_at (when they took the action).
     *
     * Pending items (same scope as pending-approvals / canTakeAction): each open item contributes
     * elapsed hours from "received at current level" to now, combined with completed actions in one average.
     */
    protected function getAverageApprovalTimeAll($staffId, $divisionId = null, $year = null, $month = null)
    {
        try {
            // Build year and month filter conditions
            $yearMonthConditions = '';
            $params = [$staffId];
            
            if ($year || $month) {
                // Use parameterized queries for model types to avoid SQL injection
                $matrixType = 'App\\Models\\Matrix';
                $activityType = 'App\\Models\\Activity';
                $changeRequestType = 'App\\Models\\ChangeRequest';
                $quarter = $month ? 'Q' . ceil($month / 3) : null;
                
                $conditions = [];
                $tempParams = [];
                
                // For matrices, filter by matrix year and quarter
                $matrixCond = "at.model_type = ?";
                $tempParams[] = $matrixType;
                $matrixExists = "EXISTS (SELECT 1 FROM matrices m WHERE m.id = at.model_id";
                if ($year) {
                    $matrixExists .= " AND m.year = ?";
                    $tempParams[] = $year;
                }
                if ($quarter) {
                    $matrixExists .= " AND m.quarter = ?";
                    $tempParams[] = $quarter;
                }
                $matrixExists .= ")";
                $conditions[] = "({$matrixCond} AND {$matrixExists})";
                
                // For activities (single memos), filter by matrix year and quarter
                $activityCond = "at.model_type = ?";
                $tempParams[] = $activityType;
                $activityExists = "EXISTS (SELECT 1 FROM activities a JOIN matrices m ON m.id = a.matrix_id WHERE a.id = at.model_id";
                if ($year) {
                    $activityExists .= " AND m.year = ?";
                    $tempParams[] = $year;
                }
                if ($quarter) {
                    $activityExists .= " AND m.quarter = ?";
                    $tempParams[] = $quarter;
                }
                $activityExists .= ")";
                $conditions[] = "({$activityCond} AND {$activityExists})";
                
                // For change requests with matrix, filter by matrix year and quarter
                $crCond = "at.model_type = ?";
                $tempParams[] = $changeRequestType;
                $crExists = "EXISTS (SELECT 1 FROM change_request cr JOIN matrices m ON m.id = cr.matrix_id WHERE cr.id = at.model_id";
                if ($year) {
                    $crExists .= " AND m.year = ?";
                    $tempParams[] = $year;
                }
                if ($quarter) {
                    $crExists .= " AND m.quarter = ?";
                    $tempParams[] = $quarter;
                }
                $crExists .= ")";
                $conditions[] = "({$crCond} AND {$crExists})";
                
                // For other types, filter by created_at year and month
                $otherCond = "at.model_type NOT IN (?, ?, ?)";
                $tempParams[] = $matrixType;
                $tempParams[] = $activityType;
                $tempParams[] = $changeRequestType;
                if ($year) {
                    $otherCond .= " AND YEAR(at.created_at) = ?";
                    $tempParams[] = $year;
                }
                if ($month) {
                    $otherCond .= " AND MONTH(at.created_at) = ?";
                    $tempParams[] = $month;
                }
                $conditions[] = "({$otherCond})";
                
                $yearMonthConditions = " AND (" . implode(" OR ", $conditions) . ")";
                $params = array_merge($params, $tempParams);
            }
            
            // Get all approval actions by this approver.
            // received_time = when item came to this approver:
            // - Order >= 2: previous level's updated_at (when previous approver took approved/rejected action).
            // - Order 1: submitted trail's updated_at (when item was submitted to workflow).
            // approval_time = this approver's updated_at (when they took the action).
            $sql = "
                SELECT 
                    at.id,
                    at.model_id,
                    at.model_type,
                    at.forward_workflow_id,
                    at.approval_order,
                    at.updated_at as approval_time,
                    CASE
                        WHEN at.approval_order >= 3 THEN (
                            SELECT MAX(prev_at.updated_at)
                            FROM approval_trails prev_at
                            WHERE prev_at.model_type = at.model_type
                              AND prev_at.model_id = at.model_id
                              AND prev_at.forward_workflow_id = at.forward_workflow_id
                              AND prev_at.approval_order < at.approval_order
                              AND prev_at.action IN ('approved', 'rejected')
                              AND prev_at.is_archived = 0
                              AND prev_at.updated_at <= at.updated_at
                        )
                        WHEN at.approval_order = 2 THEN COALESCE(
                            (SELECT MAX(prev_at.updated_at)
                             FROM approval_trails prev_at
                             WHERE prev_at.model_type = at.model_type
                               AND prev_at.model_id = at.model_id
                               AND prev_at.forward_workflow_id = at.forward_workflow_id
                               AND prev_at.approval_order < 2
                               AND prev_at.action IN ('approved', 'rejected')
                               AND prev_at.is_archived = 0
                               AND prev_at.updated_at <= at.updated_at),
                            (SELECT MAX(sub_at.updated_at)
                             FROM approval_trails sub_at
                             WHERE sub_at.model_type = at.model_type
                               AND sub_at.model_id = at.model_id
                               AND (
                                   sub_at.forward_workflow_id = at.forward_workflow_id
                                   OR (sub_at.forward_workflow_id IS NULL AND at.model_type = 'App\\\\Models\\\\Matrix' AND (SELECT m.forward_workflow_id FROM matrices m WHERE m.id = at.model_id LIMIT 1) = at.forward_workflow_id)
                                   OR (sub_at.forward_workflow_id IS NULL AND at.model_type = 'App\\\\Models\\\\Activity' AND (SELECT a.forward_workflow_id FROM activities a WHERE a.id = at.model_id LIMIT 1) = at.forward_workflow_id)
                                   OR (sub_at.forward_workflow_id IS NULL AND at.model_type NOT IN ('App\\\\Models\\\\Matrix', 'App\\\\Models\\\\Activity') AND at.forward_workflow_id IS NOT NULL)
                               )
                               AND sub_at.approval_order = 0
                               AND sub_at.action = 'submitted'
                               AND sub_at.is_archived = 0
                               AND sub_at.updated_at <= at.updated_at)
                        )
                        WHEN at.approval_order = 1 THEN (
                            SELECT MAX(sub_at.updated_at)
                            FROM approval_trails sub_at
                            WHERE sub_at.model_type = at.model_type
                              AND sub_at.model_id = at.model_id
                              AND (
                                  sub_at.forward_workflow_id = at.forward_workflow_id
                                  OR (sub_at.forward_workflow_id IS NULL AND at.model_type = 'App\\\\Models\\\\Matrix' AND (SELECT m.forward_workflow_id FROM matrices m WHERE m.id = at.model_id LIMIT 1) = at.forward_workflow_id)
                                  OR (sub_at.forward_workflow_id IS NULL AND at.model_type = 'App\\\\Models\\\\Activity' AND (SELECT a.forward_workflow_id FROM activities a WHERE a.id = at.model_id LIMIT 1) = at.forward_workflow_id)
                                  OR (sub_at.forward_workflow_id IS NULL AND at.model_type NOT IN ('App\\\\Models\\\\Matrix', 'App\\\\Models\\\\Activity') AND at.forward_workflow_id IS NOT NULL)
                              )
                              AND sub_at.approval_order = 0
                              AND sub_at.action = 'submitted'
                              AND sub_at.is_archived = 0
                              AND sub_at.updated_at <= at.updated_at
                        )
                        ELSE NULL
                    END as received_time
                FROM approval_trails at
                WHERE at.staff_id = ?
                  AND at.action IN ('approved', 'rejected')
                  AND at.is_archived = 0
                  {$yearMonthConditions}
                HAVING received_time IS NOT NULL
                  AND approval_time >= received_time
                ORDER BY at.updated_at DESC
            ";
            
            $results = DB::select($sql, $params);
            
            $totalHours = 0.0;
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

            $this->addPendingApprovalWaitContributions((int) $staffId, $divisionId, $year, $month, $totalHours, $count);
            
            return $count > 0 ? round($totalHours / $count, 2) : 0;
            
        } catch (\Exception $e) {
            Log::error('Error calculating average approval time (all): ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Last submitted trail time for a document (same rules as average-approval SQL for order 1 receipt).
     */
    protected function selectLastSubmittedTimeForModel(string $modelType, int $modelId, int $forwardWorkflowId): ?string
    {
        $matrixType = 'App\\Models\\Matrix';
        $activityType = 'App\\Models\\Activity';

        $row = DB::selectOne(
            "
            SELECT MAX(sub_at.updated_at) as t
            FROM approval_trails sub_at
            WHERE sub_at.model_type = ?
              AND sub_at.model_id = ?
              AND (
                  sub_at.forward_workflow_id = ?
                  OR (sub_at.forward_workflow_id IS NULL AND ? = ? AND (SELECT m.forward_workflow_id FROM matrices m WHERE m.id = ? LIMIT 1) = ?)
                  OR (sub_at.forward_workflow_id IS NULL AND ? = ? AND (SELECT a.forward_workflow_id FROM activities a WHERE a.id = ? LIMIT 1) = ?)
                  OR (sub_at.forward_workflow_id IS NULL AND ? NOT IN (?, ?))
              )
              AND sub_at.approval_order = 0
              AND sub_at.action = 'submitted'
              AND sub_at.is_archived = 0
            ",
            [
                $modelType, $modelId, $forwardWorkflowId,
                $modelType, $matrixType, $modelId, $forwardWorkflowId,
                $modelType, $activityType, $modelId, $forwardWorkflowId,
                $modelType, $matrixType, $activityType,
            ]
        );

        return $row->t ?? null;
    }

    /**
     * Latest approved/rejected trail strictly before the given approval order (same workflow).
     */
    protected function selectMaxPreviousApprovalBefore(string $modelType, int $modelId, int $forwardWorkflowId, int $approvalOrderExclusive): ?string
    {
        $row = DB::selectOne(
            "
            SELECT MAX(prev_at.updated_at) as t
            FROM approval_trails prev_at
            WHERE prev_at.model_type = ?
              AND prev_at.model_id = ?
              AND prev_at.forward_workflow_id = ?
              AND prev_at.approval_order < ?
              AND prev_at.action IN ('approved', 'rejected')
              AND prev_at.is_archived = 0
            ",
            [$modelType, $modelId, $forwardWorkflowId, $approvalOrderExclusive]
        );

        return $row->t ?? null;
    }

    /**
     * When the item became actionable at its current approval level (for open / pending items).
     */
    protected function getReceivedAtCurrentLevelForModel(\Illuminate\Database\Eloquent\Model $model): ?Carbon
    {
        if ($model instanceof \App\Models\OtherMemo) {
            if ($model->submitted_at) {
                return Carbon::parse($model->submitted_at);
            }
            $t = DB::table('other_memos_approval_trails')
                ->where('other_memo_id', $model->getKey())
                ->max('created_at');

            return $t ? Carbon::parse($t) : null;
        }

        $level = (int) ($model->approval_level ?? 0);
        $wf = (int) ($model->forward_workflow_id ?? 0);
        if ($level <= 0 || $wf <= 0) {
            return null;
        }

        $type = $model->getMorphClass();
        $id = (int) $model->getKey();

        if ($level >= 3) {
            $t = $this->selectMaxPreviousApprovalBefore($type, $id, $wf, $level);
        } elseif ($level === 2) {
            $t = $this->selectMaxPreviousApprovalBefore($type, $id, $wf, 2);
            if ($t === null) {
                $t = $this->selectLastSubmittedTimeForModel($type, $id, $wf);
            }
        } else {
            $t = $this->selectLastSubmittedTimeForModel($type, $id, $wf);
        }

        return $t ? Carbon::parse($t) : null;
    }

    /**
     * Add open-wait hours for every item this approver can still action (pending-approvals scope).
     */
    protected function addPendingApprovalWaitContributions(
        int $staffId,
        $divisionId,
        $year,
        $month,
        float &$totalHours,
        int &$count
    ): void {
        $approvalService = app(\App\Services\ApprovalService::class);
        $now = Carbon::now();

        $applyWait = function (\Illuminate\Database\Eloquent\Model $model) use (&$totalHours, &$count, $now): void {
            $received = $this->getReceivedAtCurrentLevelForModel($model);
            if (! $received || $now->lt($received)) {
                return;
            }
            $totalHours += max(0, $now->getTimestamp() - $received->getTimestamp()) / 3600.0;
            $count++;
        };

        $query = \App\Models\Matrix::with(['division', 'staff', 'focalPerson', 'forwardWorkflow'])
            ->where('overall_status', 'pending')
            ->whereNotNull('forward_workflow_id')
            ->where('approval_level', '>', 0);
        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }
        if ($year) {
            $query->where('year', $year);
        }
        if ($month) {
            $query->where('quarter', 'Q' . ceil($month / 3));
        }
        foreach ($query->get() as $matrix) {
            if ($approvalService->canTakeAction($matrix, $staffId)) {
                $applyWait($matrix);
            }
        }

        $query = \App\Models\SpecialMemo::with(['staff', 'division'])
            ->where('overall_status', 'pending')
            ->whereNotNull('forward_workflow_id')
            ->where('approval_level', '>', 0);
        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }
        if ($year) {
            $query->whereYear('created_at', $year);
        }
        if ($month) {
            $query->whereMonth('created_at', $month);
        }
        foreach ($query->get() as $memo) {
            if ($approvalService->canTakeAction($memo, $staffId)) {
                $applyWait($memo);
            }
        }

        $query = \App\Models\NonTravelMemo::with(['staff', 'division'])
            ->where('overall_status', 'pending')
            ->whereNotNull('forward_workflow_id')
            ->where('approval_level', '>', 0);
        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }
        if ($year) {
            $query->whereYear('created_at', $year);
        }
        if ($month) {
            $query->whereMonth('created_at', $month);
        }
        foreach ($query->get() as $memo) {
            if ($approvalService->canTakeAction($memo, $staffId)) {
                $applyWait($memo);
            }
        }

        $query = \App\Models\Activity::with(['staff', 'division', 'matrix'])
            ->where('is_single_memo', true)
            ->where('overall_status', 'pending')
            ->whereNotNull('forward_workflow_id')
            ->where('approval_level', '>', 0);
        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }
        if ($year) {
            $query->whereHas('matrix', function ($q) use ($year): void {
                $q->where('year', $year);
            });
        }
        if ($month) {
            $quarter = 'Q' . ceil($month / 3);
            $query->whereHas('matrix', function ($q) use ($quarter): void {
                $q->where('quarter', $quarter);
            });
        }
        foreach ($query->get() as $activity) {
            if ($approvalService->canTakeAction($activity, $staffId)) {
                $applyWait($activity);
            }
        }

        $query = \App\Models\OtherMemo::query()
            ->where('overall_status', \App\Models\OtherMemo::STATUS_PENDING)
            ->whereNotNull('current_approver_staff_id');
        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }
        if ($year) {
            $query->whereYear('created_at', $year);
        }
        if ($month) {
            $query->whereMonth('created_at', $month);
        }
        foreach ($query->get() as $otherMemo) {
            if ($approvalService->canTakeAction($otherMemo, $staffId)) {
                $applyWait($otherMemo);
            }
        }

        $query = \App\Models\RequestARF::with(['staff', 'division', 'forwardWorkflow'])
            ->where('overall_status', 'pending')
            ->whereNotNull('forward_workflow_id')
            ->where('approval_level', '>', 0);
        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }
        if ($year) {
            $query->whereYear('created_at', $year);
        }
        if ($month) {
            $query->whereMonth('created_at', $month);
        }
        foreach ($query->get() as $arf) {
            if ($approvalService->canTakeAction($arf, $staffId)) {
                $applyWait($arf);
            }
        }

        $query = \App\Models\ServiceRequest::with(['staff', 'division', 'forwardWorkflow'])
            ->where('overall_status', 'pending')
            ->whereNotNull('forward_workflow_id')
            ->where('approval_level', '>', 0);
        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }
        if ($year) {
            $query->whereYear('created_at', $year);
        }
        if ($month) {
            $query->whereMonth('created_at', $month);
        }
        foreach ($query->get() as $serviceRequest) {
            if ($approvalService->canTakeAction($serviceRequest, $staffId)) {
                $applyWait($serviceRequest);
            }
        }

        $query = \App\Models\ChangeRequest::with(['staff', 'division', 'forwardWorkflow', 'matrix'])
            ->where('overall_status', 'pending')
            ->whereNotNull('forward_workflow_id')
            ->where('approval_level', '>', 0);
        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }
        if ($year) {
            $query->where(function ($q) use ($year): void {
                $q->whereHas('matrix', function ($matrixQuery) use ($year): void {
                    $matrixQuery->where('year', $year);
                })->orWhereYear('created_at', $year);
            });
        }
        if ($month) {
            $quarter = 'Q' . ceil($month / 3);
            $query->where(function ($q) use ($quarter, $month): void {
                $q->whereHas('matrix', function ($matrixQuery) use ($quarter): void {
                    $matrixQuery->where('quarter', $quarter);
                })->orWhereMonth('created_at', $month);
            });
        }
        foreach ($query->get() as $changeRequest) {
            if ($approvalService->canTakeAction($changeRequest, $staffId)) {
                $applyWait($changeRequest);
            }
        }
    }

    /**
     * Dashboard header cards: document counts by status for the selected division / doc type / year / month.
     * Total approval requests = pending + approved + returned (same scope).
     */
    protected function getDashboardSummaryMetrics(?int $divisionId, ?string $docType, ?int $year, ?int $month): array
    {
        try {
            $types = $this->expandDashboardDocTypes($docType);
            $pending = 0;
            $approved = 0;
            $returned = 0;
            foreach ($types as $t) {
                $pending += $this->dashboardCountDocumentsByStatus($t, 'pending', $divisionId, $year, $month);
                $approved += $this->dashboardCountDocumentsByStatus($t, 'approved', $divisionId, $year, $month);
                $returned += $this->dashboardCountDocumentsByStatus($t, 'returned', $divisionId, $year, $month);
            }

            return [
                'total_approval_requests' => $pending + $approved + $returned,
                'total_pending' => $pending,
                'total_approved' => $approved,
                'total_returned' => $returned,
            ];
        } catch (\Exception $e) {
            Log::error('Error calculating dashboard summary metrics: ' . $e->getMessage());

            return [
                'total_approval_requests' => 0,
                'total_pending' => 0,
                'total_approved' => 0,
                'total_returned' => 0,
            ];
        }
    }

    /**
     * @return list<string>
     */
    protected function expandDashboardDocTypes(?string $docType): array
    {
        if ($docType === null || $docType === '') {
            return ['matrix', 'non_travel', 'special', 'single_memos', 'arf', 'requests_for_service', 'change_requests', 'other_memo'];
        }
        if ($docType === 'memos') {
            return ['non_travel', 'special', 'single_memos', 'other_memo'];
        }

        return [$docType];
    }

    protected function dashboardCountDocumentsByStatus(string $type, string $status, ?int $divisionId, ?int $year, ?int $month): int
    {
        switch ($type) {
            case 'matrix':
                return $this->dashboardCountMatrixLike(\App\Models\Matrix::query(), $status, $divisionId, $year, $month, true);
            case 'special':
                return $this->dashboardCountMemoLike(\App\Models\SpecialMemo::query(), $status, $divisionId, $year, $month);
            case 'non_travel':
                return $this->dashboardCountMemoLike(\App\Models\NonTravelMemo::query(), $status, $divisionId, $year, $month);
            case 'single_memos':
                $q = \App\Models\Activity::query()->where('is_single_memo', true);
                if ($status === 'pending') {
                    $q->where('overall_status', 'pending')->whereNotNull('forward_workflow_id')->where('approval_level', '>', 0);
                } else {
                    $q->where('overall_status', $status);
                }
                if ($divisionId) {
                    $q->where('division_id', $divisionId);
                }
                if ($year) {
                    $q->whereHas('matrix', function ($mq) use ($year): void {
                        $mq->where('year', $year);
                    });
                }
                if ($month) {
                    $quarter = 'Q' . ceil($month / 3);
                    $q->whereHas('matrix', function ($mq) use ($quarter): void {
                        $mq->where('quarter', $quarter);
                    });
                }

                return (int) $q->count();
            case 'arf':
                return $this->dashboardCountMemoLike(\App\Models\RequestARF::query(), $status, $divisionId, $year, $month);
            case 'requests_for_service':
                return $this->dashboardCountMemoLike(\App\Models\ServiceRequest::query(), $status, $divisionId, $year, $month);
            case 'change_requests':
                $q = \App\Models\ChangeRequest::query();
                if ($status === 'pending') {
                    $q->where('overall_status', 'pending')->whereNotNull('forward_workflow_id')->where('approval_level', '>', 0);
                } else {
                    $q->where('overall_status', $status);
                }
                if ($divisionId) {
                    $q->where('division_id', $divisionId);
                }
                if ($year) {
                    $q->where(function ($qq) use ($year): void {
                        $qq->whereHas('matrix', function ($mq) use ($year): void {
                            $mq->where('year', $year);
                        })->orWhereYear('created_at', $year);
                    });
                }
                if ($month) {
                    $quarter = 'Q' . ceil($month / 3);
                    $q->where(function ($qq) use ($quarter, $month): void {
                        $qq->whereHas('matrix', function ($mq) use ($quarter): void {
                            $mq->where('quarter', $quarter);
                        })->orWhereMonth('created_at', $month);
                    });
                }

                return (int) $q->count();
            case 'other_memo':
                return $this->dashboardCountOtherMemo($status, $divisionId, $year, $month);
            default:
                return 0;
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Matrix>  $q
     */
    protected function dashboardCountMatrixLike($q, string $status, ?int $divisionId, ?int $year, ?int $month, bool $isMatrix): int
    {
        if ($status === 'pending') {
            $q->where('overall_status', 'pending')->whereNotNull('forward_workflow_id')->where('approval_level', '>', 0);
        } else {
            $q->where('overall_status', $status);
        }
        if ($divisionId) {
            $q->where('division_id', $divisionId);
        }
        if ($year && $isMatrix) {
            $q->where('year', $year);
        }
        if ($month && $isMatrix) {
            $q->where('quarter', 'Q' . ceil($month / 3));
        }

        return (int) $q->count();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $q
     */
    protected function dashboardCountMemoLike($q, string $status, ?int $divisionId, ?int $year, ?int $month): int
    {
        if ($status === 'pending') {
            $q->where('overall_status', 'pending')->whereNotNull('forward_workflow_id')->where('approval_level', '>', 0);
        } else {
            $q->where('overall_status', $status);
        }
        if ($divisionId) {
            $q->where('division_id', $divisionId);
        }
        if ($year) {
            $q->whereYear('created_at', $year);
        }
        if ($month) {
            $q->whereMonth('created_at', $month);
        }

        return (int) $q->count();
    }

    protected function dashboardCountOtherMemo(string $status, ?int $divisionId, ?int $year, ?int $month): int
    {
        $q = \App\Models\OtherMemo::query();
        if ($status === 'pending') {
            $q->where('overall_status', \App\Models\OtherMemo::STATUS_PENDING)->whereNotNull('current_approver_staff_id');
        } elseif ($status === 'approved') {
            $q->where('overall_status', \App\Models\OtherMemo::STATUS_APPROVED);
        } elseif ($status === 'returned') {
            $q->where('overall_status', \App\Models\OtherMemo::STATUS_RETURNED);
        } else {
            return 0;
        }
        if ($divisionId) {
            $q->where('division_id', $divisionId);
        }
        if ($year) {
            $q->whereYear('created_at', $year);
        }
        if ($month) {
            $q->whereMonth('created_at', $month);
        }

        return (int) $q->count();
    }
}