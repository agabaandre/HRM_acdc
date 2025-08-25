<?php
/**
 * Home Helper Functions
 * 
 * This helper file contains all the functions needed for the home page dashboard.
 * It provides functions to get pending action counts, recent activities, and other
 * data needed to display the home page widgets.
 */

use App\Models\ActivityApprovalTrail;
use App\Models\ApprovalTrail;
use App\Models\Matrix;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use App\Models\ServiceRequest;
use App\Models\RequestARF;
use App\Models\Division;
use Illuminate\Support\Facades\DB;



if (!function_exists('get_staff_pending_action_count')) {
    /**
     * Get the count of pending actions for a specific module for the current staff member
     *
     * @param string $module The module to check (matrices, non-travel, special-memo, service-requests, request-arf)
     * @return int
     */
    function get_staff_pending_action_count(string $module): int
    {
        $user = session('user', []);
        $staffId = $user['staff_id'] ?? null;
        
        if (!$staffId) {
            return 0;
        }

        switch ($module) {
            case 'matrices':
                return get_pending_matrices_count($staffId);
            case 'non-travel':
                return get_pending_non_travel_memo_count($staffId);
            case 'special-memo':
                return get_pending_special_memo_count($staffId);
            case 'service-requests':
                return get_pending_service_requests_count($staffId);
            case 'request-arf':
                return get_pending_request_arf_count($staffId);
            case 'single-memo':
                return get_pending_single_memo_count($staffId);
            case 'change-request':
                return get_pending_change_request_count($staffId);
            default:
                return 0;
        }
    }
}

if (!function_exists('get_pending_matrices_count')) {
    /**
     * Get count of pending matrices that require action from the current staff member
     *
     * @param int $staffId
     * @return int
     */
    function get_pending_matrices_count(int $staffId): int
    {
        $userDivisionId = user_session('division_id');
        
        // Use the same logic as the pendingApprovals method for consistency
        $query = Matrix::where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);

        $query->where(function($query) use ($userDivisionId, $staffId) {
            // Case 1: Division-specific approval - check if user's division matches matrix division
            if ($userDivisionId) {
                $query->whereHas('forwardWorkflow.workflowDefinitions', function($subQ): void {
                    $subQ->where('is_division_specific', 1)
                    ->whereNull('division_reference_column')
                          ->where('approval_order', \Illuminate\Support\Facades\DB::raw('matrices.approval_level'));
                })
                ->where('division_id', $userDivisionId);
            }

            // Case 1b: Division-specific approval with division_reference_column - check if user's staff_id matches the value in the division_reference_column
            if ($staffId) {
                $query->orWhere(function($subQ) use ($staffId, $userDivisionId) {
                    $divisionsTable = (new Division())->getTable();
                    $subQ->whereRaw("EXISTS (
                        SELECT 1 FROM workflow_definition wd 
                        JOIN {$divisionsTable} d ON d.id = matrices.division_id 
                        WHERE wd.workflow_id = matrices.forward_workflow_id 
                        AND wd.is_division_specific = 1 
                        AND wd.division_reference_column IS NOT NULL 
                        AND wd.approval_order = matrices.approval_level
                        AND ( d.focal_person = ? OR
                            d.division_head = ? OR
                            d.admin_assistant = ? OR
                            d.finance_officer = ? OR
                            d.head_oic_id = ? OR
                            d.director_id = ? OR
                            d.director_oic_id = ?
                            OR (d.id=matrices.division_id AND d.id=?)
                        )
                    )", [$staffId, $staffId, $staffId, $staffId, $staffId, $staffId, $staffId, $userDivisionId])
                    ->orWhere(function($subQ2) use ($staffId) {
                        $subQ2->where('approval_level', $staffId)
                              ->orWhereHas('approvalTrails', function($trailQ) use ($staffId) {
                                $trailQ->where('staff_id', '=',$staffId);
                              });
                    });
                });
            }
            
            // Case 2: Non-division-specific approval - check workflow definition and approver
            if ($staffId) {
                $query->orWhere(function($subQ) use ($staffId) {
                    $subQ->whereHas('forwardWorkflow.workflowDefinitions', function($workflowQ) use ($staffId) {
                        $workflowQ->where('is_division_specific','=', 0)
                                  ->where('approval_order', \Illuminate\Support\Facades\DB::raw('matrices.approval_level'))
                                  ->whereHas('approvers', function($approverQ) use ($staffId) {
                                      $approverQ->where('staff_id', $staffId);
                                  });
                    });
                });
            }

            $query->orWhere('division_id', $userDivisionId);
        });

        // Get the matrices and apply the same filtering as pendingApprovals method
        $matrices = $query->get();
        
        // Apply the same additional filtering as pendingApprovals method for consistency
        $filteredMatrices = $matrices->filter(function ($matrix) {
            return can_take_action($matrix);
        });
        
        return $filteredMatrices->count();
    }
}

if (!function_exists('get_pending_non_travel_memo_count')) {
    /**
     * Get count of pending non-travel memo activities that require action from the current staff member
     *
     * @param int $staffId
     * @return int
     */
    function get_pending_non_travel_memo_count(int $staffId): int
    {
        $userDivisionId = user_session('division_id');
        
        // Use the same logic as the pendingApprovals method for consistency
        $query = NonTravelMemo::where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);

        $query->where(function($query) use ($userDivisionId, $staffId) {
            // Case 1: Division-specific approval - check if user's division matches memo division
            if ($userDivisionId) {
                $query->whereHas('forwardWorkflow.workflowDefinitions', function($subQ): void {
                    $subQ->where('is_division_specific', 1)
                    ->whereNull('division_reference_column')
                          ->where('approval_order', \Illuminate\Support\Facades\DB::raw('non_travel_memos.approval_level'));
                })
                ->where('division_id', $userDivisionId);
            }

            // Case 1b: Division-specific approval with division_reference_column - check if user's staff_id matches the value in the division_reference_column
            if ($staffId) {
                $query->orWhere(function($subQ) use ($staffId, $userDivisionId) {
                    $divisionsTable = (new \App\Models\Division())->getTable();
                    $subQ->whereRaw("EXISTS (
                        SELECT 1 FROM workflow_definition wd 
                        JOIN {$divisionsTable} d ON d.id = non_travel_memos.division_id 
                        WHERE wd.workflow_id = non_travel_memos.forward_workflow_id 
                        AND wd.is_division_specific = 1 
                        AND wd.division_reference_column IS NOT NULL 
                        AND wd.approval_order = non_travel_memos.approval_level
                        AND ( d.focal_person = ? OR
                            d.division_head = ? OR
                            d.admin_assistant = ? OR
                            d.finance_officer = ? OR
                            d.head_oic_id = ? OR
                            d.director_id = ? OR
                            d.director_oic_id = ?
                            OR (d.id=non_travel_memos.division_id AND d.id=?)
                        )
                    )", [$staffId, $staffId, $staffId, $staffId, $staffId, $staffId, $staffId, $userDivisionId])
                    ->orWhere(function($subQ2) use ($staffId) {
                        $subQ2->where('approval_level', $staffId)
                              ->orWhereHas('approvalTrails', function($trailQ) use ($staffId) {
                                $trailQ->where('staff_id', '=',$staffId);
                              });
                    });
                });
            }
            
            // Case 2: Non-division-specific approval - check workflow definition and approver
            if ($staffId) {
                $query->orWhere(function($subQ) use ($staffId) {
                    $subQ->whereHas('forwardWorkflow.workflowDefinitions', function($workflowQ) use ($staffId) {
                        $workflowQ->where('is_division_specific','=', 0)
                                  ->where('approval_order', \Illuminate\Support\Facades\DB::raw('non_travel_memos.approval_level'))
                                  ->whereHas('approvers', function($approverQ) use ($staffId) {
                                      $approverQ->where('staff_id', $staffId);
                                  });
                    });
                });
            }

            $query->orWhere('division_id', $userDivisionId);
        });

        // Get the memos and apply the same filtering as pendingApprovals method
        $memos = $query->get();
        
        // Apply the same additional filtering as pendingApprovals method for consistency
        $filteredMemos = $memos->filter(function ($memo) {
            return can_take_action_generic($memo);
        });
        
        return $filteredMemos->count();
    }
}

if (!function_exists('get_pending_special_memo_count')) {
    /**
     * Get count of pending special memos that require action from the current staff member
     *
     * @param int $staffId
     * @return int
     */
    function get_pending_special_memo_count(int $staffId): int
    {
        $userDivisionId = user_session('division_id');
        
        // Simplified query that directly checks if the user can approve the memo
        $query = SpecialMemo::where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0);

        $query->where(function($query) use ($userDivisionId, $staffId) {
            // Case 1: Check if user is an approver for the current approval level
            $query->whereHas('forwardWorkflow.workflowDefinitions', function($subQ) use ($staffId) {
                $subQ->where('approval_order', \Illuminate\Support\Facades\DB::raw('special_memos.approval_level'))
                      ->where(function($workflowQ) use ($staffId) {
                          // Check if user is in approvers table
                          $workflowQ->whereHas('approvers', function($approverQ) use ($staffId) {
                              $approverQ->where('staff_id', $staffId);
                          });
                      });
            });

            // Case 2: Check if user has division-specific role for the current approval level
            if ($userDivisionId) {
                $query->orWhere(function($subQ) use ($userDivisionId, $staffId) {
                    $subQ->whereHas('forwardWorkflow.workflowDefinitions', function($workflowQ) use ($userDivisionId, $staffId) {
                        $workflowQ->where('is_division_specific', 1)
                                  ->where('approval_order', \Illuminate\Support\Facades\DB::raw('special_memos.approval_level'))
                                  ->where(function($divQ) use ($userDivisionId, $staffId) {
                                      // Check division roles
                                      $divQ->whereRaw("EXISTS (
                                          SELECT 1 FROM divisions d 
                                          WHERE d.id = special_memos.division_id 
                                          AND d.id = ?
                                          AND (
                                              d.focal_person = ? OR
                                              d.division_head = ? OR
                                              d.admin_assistant = ? OR
                                              d.finance_officer = ? OR
                                              d.head_oic_id = ? OR
                                              d.director_id = ? OR
                                              d.director_oic_id = ?
                                          )
                                      )", [$userDivisionId, $staffId, $staffId, $staffId, $staffId, $staffId, $staffId, $staffId]);
                                  });
                    });
                });
            }
        });

        // Get the memos and apply the can_take_action_generic filter
        $memos = $query->get();
        
        // Apply the same additional filtering as pendingApprovals method for consistency
        $filteredMemos = $memos->filter(function ($memo) use ($staffId) {
            return can_take_action_generic($memo, $staffId);
        });
        
        return $filteredMemos->count();
    }
}

if (!function_exists('get_pending_service_requests_count')) {
    /**
     * Get count of pending service requests that require action from the current staff member
     *
     * @param int $staffId
     * @return int
     */
    function get_pending_service_requests_count(int $staffId): int
    {
        // For now, just return count of pending service requests
        // TODO: Implement proper approval logic when ServiceRequest approval system is added
        return ServiceRequest::where('approval_status', 'pending')
            ->where('workflow_id', '!=', null)
            ->count();
    }
}

if (!function_exists('get_pending_request_arf_count')) {
    /**
     * Get count of pending ARF requests that require action from the current staff member
     *
     * @param int $staffId
     * @return int
     */
    function get_pending_request_arf_count(int $staffId): int
    {
        // For now, just return count of pending ARF requests
        // TODO: Implement proper approval logic when RequestARF approval system is added
        return RequestARF::where('status', 'submitted')
            ->where('forward_workflow_id', '!=', null)
            ->count();
    }
}

if (!function_exists('get_pending_single_memo_count')) {
    /**
     * Get count of pending single memos that require action from the current staff member
     *
     * @param int $staffId
     * @return int
     */
    function get_pending_single_memo_count(int $staffId): int
    {
        // Single memos are activities, so check activity approval trails
        return DB::table('activities')
            ->join('approval_trails', function($join) {
                $join->on('activities.id', '=', 'approval_trails.model_id')
                     ->where('approval_trails.model_type', 'App\\Models\\Activity');
            })
            ->where('activities.overall_status', 'pending')
            ->where('activities.forward_workflow_id', '!=', null)
            ->where('activities.approval_level', '>', 0)
            ->where('approval_trails.staff_id', '!=', $staffId) // Not approved by current user
            ->whereNotExists(function($query) use ($staffId) {
                $query->select(DB::raw(1))
                      ->from('approval_trails as at2')
                      ->whereRaw('at2.model_id = activities.id')
                      ->where('at2.model_type', 'App\\Models\\Activity')
                      ->where('at2.staff_id', $staffId)
                      ->where('at2.action', 'approved');
            })
            ->count();
    }
}

if (!function_exists('get_pending_change_request_count')) {
    /**
     * Get count of pending change requests that require action from the current staff member
     *
     * @param int $staffId
     * @return int
     */
    function get_pending_change_request_count(int $staffId): int
    {
        // Change requests are typically activities with specific request types
        // For now, return 0 as this needs to be implemented based on your change request structure
        return 0;
    }
}

if (!function_exists('get_staff_total_pending_count')) {
    /**
     * Get the total count of all pending actions across all modules for the current staff member
     *
     * @return int
     */
    function get_staff_total_pending_count(): int
    {
        $modules = ['matrices', 'non-travel', 'special-memo', 'service-requests', 'request-arf', 'single-memo', 'change-request'];
        $total = 0;
        
        foreach ($modules as $module) {
            $total += get_staff_pending_action_count($module);
        }
        
        return $total;
    }
}

if (!function_exists('get_staff_recent_activities')) {
    /**
     * Get recent activities for the current staff member across all modules
     *
     * @param int $limit
     * @return array
     */
    function get_staff_recent_activities(int $limit = 5): array
    {
        $user = session('user', []);
        $staffId = $user['staff_id'] ?? null;
        
        if (!$staffId) {
            return [];
        }

        $activities = [];
        
        // Get recent matrices
        $recentMatrices = Matrix::where(function ($query) use ($staffId) {
            $query->where('staff_id', $staffId)
                ->orWhere('focal_person_id', $staffId);
        })
        ->orderBy('updated_at', 'desc')
        ->limit($limit)
        ->get(['id', 'title', 'overall_status', 'updated_at']);
        
        foreach ($recentMatrices as $matrix) {
            $activities[] = [
                'type' => 'matrix',
                'id' => $matrix->id,
                'title' => $matrix->title,
                'status' => $matrix->overall_status,
                'updated_at' => $matrix->updated_at,
                'url' => route('matrices.show', $matrix->id)
            ];
        }
        
        // Sort by updated_at and return limited results
        usort($activities, function ($a, $b) {
            return $b['updated_at'] <=> $a['updated_at'];
        });
        
        return array_slice($activities, 0, $limit);
    }
} 