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
        // Count matrices that are pending and the current user can approve
        return Matrix::where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0)
            ->where(function($query) use ($staffId) {
                // Check if user is an approver for the current workflow level
                $query->whereHas('workflowDefinition.approvers', function($q) use ($staffId) {
                    $q->where('staff_id', $staffId);
                })
                // Or check if user is division-specific approver
                ->orWhereHas('division', function($q) use ($staffId) {
                    $q->where('division_head', $staffId)
                      ->orWhere('focal_person', $staffId)
                      ->orWhere('admin_assistant', $staffId)
                      ->orWhere('finance_officer', $staffId);
                });
            })
            ->count();
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
        return NonTravelMemo::where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0)
            ->where(function($query) use ($staffId) {
                // Check if user is division-specific approver for the current level
                $query->whereHas('division', function($q) use ($staffId) {
                    $q->where('division_head', $staffId)
                      ->orWhere('focal_person', $staffId)
                      ->orWhere('admin_assistant', $staffId)
                      ->orWhere('finance_officer', $staffId);
                });
            })
            ->count();
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
        return SpecialMemo::where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0)
            ->where(function($query) use ($staffId) {
                // Check if user is division-specific approver for the current level
                $query->whereHas('division', function($q) use ($staffId) {
                    $q->where('division_head', $staffId)
                      ->orWhere('focal_person', $staffId)
                      ->orWhere('admin_assistant', $staffId)
                      ->orWhere('finance_officer', $staffId);
                });
            })
            ->count();
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
        return ServiceRequest::where('approval_status', 'pending')
            ->where('workflow_id', '!=', null)
            ->where(function($query) use ($staffId) {
                // Check if user is an approver for the current workflow level
                $query->whereHas('workflowDefinition.approvers', function($q) use ($staffId) {
                    $q->where('staff_id', $staffId);
                })
                // Or check if user is division-specific approver
                ->orWhereHas('division', function($q) use ($staffId) {
                    $q->where('division_head', $staffId)
                      ->orWhere('focal_person', $staffId)
                      ->orWhere('admin_assistant', $staffId)
                      ->orWhere('finance_officer', $staffId);
                });
            })
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
        return RequestARF::where('overall_status', 'pending')
            ->where('forward_workflow_id', '!=', null)
            ->where('approval_level', '>', 0)
            ->where(function($query) use ($staffId) {
                // Check if user is an approver for the current workflow level
                $query->whereHas('workflowDefinition.approvers', function($q) use ($staffId) {
                    $q->where('staff_id', $staffId);
                })
                // Or check if user is division-specific approver
                ->orWhereHas('division', function($q) use ($staffId) {
                    $q->where('division_head', $staffId)
                      ->orWhere('focal_person', $staffId)
                      ->orWhere('admin_assistant', $staffId)
                      ->orWhere('finance_officer', $staffId);
                });
            })
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