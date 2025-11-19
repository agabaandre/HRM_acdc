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
                    'approval_times' => [],
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
            
            // Get pending counts for this specific approver/level combination
            $pendingCounts = $this->getPendingCountsForApprover(
                $approverObj->approver_id,
                $approverObj->level_no,
                $workflowDefinitionId,
                $docType,
                $approverObj->division_id
            );

            // Sum up pending counts across all roles/levels
            foreach ($approversByStaffId[$staffId]['pending_counts'] as $key => $value) {
                if (isset($pendingCounts[$key])) {
                    $approversByStaffId[$staffId]['pending_counts'][$key] += $pendingCounts[$key];
                }
            }

            // Calculate average approval time for this specific approver/level
            $avgApprovalTime = $this->getAverageApprovalTime(
                $approverObj->staff_id,
                $approverObj->level_no,
                $approverObj->workflow_id,
                $docType
            );
            if ($avgApprovalTime > 0) {
                $approversByStaffId[$staffId]['approval_times'][] = $avgApprovalTime;
            }

            // Sum up total handled documents
            $totalHandled = $this->getTotalHandledForApprover(
                $approverObj->staff_id,
                $approverObj->level_no,
                $approverObj->workflow_id,
                $approverObj->division_id
            );
            $approversByStaffId[$staffId]['total_handled'] += $totalHandled;
        }

        // Second pass: build final array with combined data
        $approversWithCounts = [];
        foreach ($approversByStaffId as $staffId => $data) {
            // Calculate total pending
            $totalPending = array_sum(array_diff_key($data['pending_counts'], ['total' => '']));
            
            // Calculate average approval time across all levels
            $avgApprovalTime = 0;
            if (!empty($data['approval_times'])) {
                $avgApprovalTime = round(array_sum($data['approval_times']) / count($data['approval_times']), 2);
            }
            
            // Sort roles and levels for display
            sort($data['levels']);
            sort($data['roles']);
            
            $approversWithCounts[] = [
                'staff_id' => $data['staff_id'],
                'approver_id' => $data['approver_id'],
                'approver_name' => $data['approver_name'],
                'approver_email' => $data['approver_email'],
                'division_name' => $data['division_name'],
                'roles' => $data['roles'],
                'levels' => $data['levels'],
                'role' => implode(', ', $data['roles']), // Combined roles for display
                'level_no' => implode(', ', $data['levels']), // Combined levels for display
                'pending_counts' => $data['pending_counts'],
                'total_pending' => $totalPending,
                'total_handled' => $data['total_handled'],
                'avg_approval_time_hours' => $avgApprovalTime,
                'avg_approval_time_display' => $this->formatApprovalTime($avgApprovalTime),
            ];
        }

        return $approversWithCounts;
    }

    /**
     * Get pending counts for a specific approver using approval_trails logic.
     */
    protected function getPendingCountsForApprover($approverId, $levelNo, $workflowId, $docType = null, $divisionId = null)
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

        // Build division filter for documents
        $divisionFilter = '';
        $divisionParams = [];
        if ($divisionId) {
            // For division filtering, we need to join with the actual document tables
            // This will be handled in each document type query by joining with the document table
        }

        // Define model types and their corresponding document types
        $modelMappings = [
            'App\\Models\\Matrix' => 'matrix',
            'App\\Models\\NonTravelMemo' => 'non_travel',
            'App\\Models\\SpecialMemo' => 'special',
        ];

        foreach ($modelMappings as $modelType => $docTypeKey) {
            // Skip if specific doc type is requested and this isn't it
            if ($docType && $docType !== $docTypeKey) {
                continue;
            }

            try {
                // Find documents that are pending for this approver at this level
                // Logic: For level 1, find documents submitted (order 0) but not yet approved at level 1
                // For other levels, find documents approved at previous level but not yet approved at current level
                if ($levelNo == 1) {
                    // Level 1: Documents submitted (order 0) but not yet approved at level 1
                    if ($divisionId && $modelType === 'App\\Models\\Matrix') {
                        // For matrices, join with matrices table to filter by division
                        $sql = "
                            SELECT COUNT(DISTINCT at.model_id) as count
                            FROM approval_trails at
                            INNER JOIN matrices m ON at.model_id = m.id
                            WHERE at.model_type = ?
                            AND at.forward_workflow_id = ?
                            AND at.approval_order = 0
                            AND at.action = 'submitted'
                            AND m.division_id = ?
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
                        $params = [$modelType, $workflowId, $divisionId, $levelNo];
                    } elseif ($modelType === 'App\\Models\\NonTravelMemo') {
                        // For non-travel memos, use the approval_level and next_approval_level columns
                        // Only show pending memos, not drafts
                        if ($divisionId) {
                            $sql = "
                                SELECT COUNT(*) as count
                                FROM non_travel_memos ntm
                                WHERE ntm.division_id = ?
                                AND ntm.approval_level = ?
                                AND ntm.overall_status = 'pending'
                            ";
                            $params = [$divisionId, $levelNo];
                        } else {
                            $sql = "
                                SELECT COUNT(*) as count
                                FROM non_travel_memos ntm
                                WHERE ntm.approval_level = ?
                                AND ntm.overall_status = 'pending'
                            ";
                            $params = [$levelNo];
                        }
                    } elseif ($divisionId && $modelType === 'App\\Models\\SpecialMemo') {
                        // For special memos, join with special_memos table to filter by division
                        $sql = "
                            SELECT COUNT(DISTINCT at.model_id) as count
                            FROM approval_trails at
                            INNER JOIN special_memos sm ON at.model_id = sm.id
                            WHERE at.model_type = ?
                            AND at.forward_workflow_id = ?
                            AND at.approval_order = 0
                            AND at.action = 'submitted'
                            AND sm.division_id = ?
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
                        $params = [$modelType, $workflowId, $divisionId, $levelNo];
                    } else {
                        // No division filter or unsupported model type
                        $sql = "
                            SELECT COUNT(DISTINCT at.model_id) as count
                            FROM approval_trails at
                            WHERE at.model_type = ?
                            AND at.forward_workflow_id = ?
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
                        $params = [$modelType, $workflowId, $levelNo];
                    }
                } else {
                    // Other levels: Documents approved at previous level but not yet approved at current level
                    if ($modelType === 'App\\Models\\NonTravelMemo') {
                        // For non-travel memos, use the approval_level and next_approval_level columns
                        // Only show pending memos, not drafts
                        if ($divisionId) {
                            $sql = "
                                SELECT COUNT(*) as count
                                FROM non_travel_memos ntm
                                WHERE ntm.division_id = ?
                                AND ntm.approval_level = ?
                                AND ntm.overall_status = 'pending'
                            ";
                            $params = [$divisionId, $levelNo];
                        } else {
                            $sql = "
                                SELECT COUNT(*) as count
                                FROM non_travel_memos ntm
                                WHERE ntm.approval_level = ?
                                AND ntm.overall_status = 'pending'
                            ";
                            $params = [$levelNo];
                        }
                    } else {
                        // For other document types, use approval_trails logic
                        $sql = "
                            SELECT COUNT(DISTINCT at.model_id) as count
                            FROM approval_trails at
                            WHERE at.model_type = ?
                            AND at.forward_workflow_id = ?
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
                        $params = [$modelType, $workflowId, $levelNo - 1, $levelNo];
                    }
                }
                $result = DB::select($sql, $params);
                $counts[$docTypeKey] = $result[0]->count ?? 0;

                // Note: 'memos' and 'single_memos' are now separate document types
                // No need to duplicate counts from other types

            } catch (\Exception $e) {
                // Log error and continue
                Log::error('Error getting pending counts for ' . $modelType . ': ' . $e->getMessage());
                $counts[$docTypeKey] = 0;
            }
        }

        // Handle ARF and Service Requests separately (they might not use approval_trails)
        // These need to filter by workflow and approval level
        $arfCount = $this->getPendingCountForARF($workflowId, $levelNo, $divisionId);
        $serviceCount = $this->getPendingCountForServiceRequests($workflowId, $levelNo, $divisionId);
        $changeRequestCount = $this->getPendingCountForChangeRequests($workflowId, $levelNo, $divisionId);
        
        $counts['arf'] = $arfCount;
        $counts['requests_for_service'] = $serviceCount;
        $counts['change_requests'] = $changeRequestCount;

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
     * Get total handled documents for a specific approver.
     * This counts all documents that have been approved/rejected by this approver.
     */
    protected function getTotalHandledForApprover($staffId, $levelNo, $workflowId, $divisionId = null)
    {
        try {
            // Count documents handled by this approver at this level
            // This includes documents that have been approved or rejected by this specific staff member
            $sql = "
                SELECT COUNT(DISTINCT CONCAT(at.model_type, '-', at.model_id)) as total_count
                FROM approval_trails at
                WHERE at.staff_id = ?
                AND at.forward_workflow_id = ?
                AND at.approval_order = ?
                AND at.action IN ('approved', 'rejected')
            ";
            $params = [$staffId, $workflowId, $levelNo];
            
            $result = DB::select($sql, $params);
            return $result[0]->total_count ?? 0;
            
        } catch (\Exception $e) {
            Log::error('Error calculating total handled for approver: ' . $e->getMessage());
            return 0;
        }
    }
}