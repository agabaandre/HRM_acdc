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
        return Matrix::where('overall_status', '!=', 'approved')
            ->where('overall_status', '!=', 'draft')
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
        // This should be implemented based on your NonTravelMemo model structure
        // For now, returning 0 as placeholder
        return 0;
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
        // This should be implemented based on your SpecialMemo model structure
        // For now, returning 0 as placeholder
        return 0;
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
        // This should be implemented based on your ServiceRequest model structure
        // For now, returning 0 as placeholder
        return 0;
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
        // This should be implemented based on your RequestARF model structure
        // For now, returning 0 as placeholder
        return 0;
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
        // This should be implemented based on your SingleMemo model structure
        // For now, returning 0 as placeholder
        return 0;
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
        // This should be implemented based on your ChangeRequest model structure
        // For now, returning 0 as placeholder
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