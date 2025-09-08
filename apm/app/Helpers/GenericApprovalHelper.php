<?php

use App\Services\ApprovalService;
use App\Models\ApprovalTrail;
use App\Models\WorkflowDefinition;
use App\Models\Approver;
use App\Models\Staff;
use Carbon\Carbon;

if (!function_exists('can_take_action_generic')) {
    
    /**
     * Check if user can take action on any model.
     */
    function can_take_action_generic($model, $userId = null)
    {
        // Use the ApprovalService for consistent logic
        $approvalService = app(\App\Services\ApprovalService::class);
        return $approvalService->canTakeAction($model, $userId ?? user_session('staff_id'));
    }
}

if (!function_exists('done_approving_generic')) {
    /**
     * Check if user has already approved a model.
     */
    function done_approving_generic($model, $userId = null)
    {
        $userId = $userId ?? user_session('staff_id');
        
        $approval = ApprovalTrail::where('model_id', $model->id)
            ->where('model_type', get_class($model))
            ->where('action', 'approved')
            ->where('approval_order', $model->approval_level)
            ->where('staff_id', $userId)
            ->first();

        return $approval !== null;
    }
}

if (!function_exists('is_with_creator_generic')) {
    /**
     * Check if model is still with creator.
     */
    function is_with_creator_generic($model)
    {
         // Use the ApprovalService for consistent logic
         $approvalService = app(\App\Services\ApprovalService::class);
         return $approvalService->isWithCreator($model);
    }
}

if (!function_exists('can_division_head_edit_generic')) {
    /**
     * Check if model is still with creator.
     */
    function can_division_head_edit_generic($model)
    {
         // Use the ApprovalService for consistent logic
         $approvalService = app(\App\Services\ApprovalService::class);
         return $approvalService->canDivisionHeadEdit($model);
    }
}


if (!function_exists('get_approval_recipient_generic')) {
    /**
     * Get the notification recipient for any model.
     */
    function get_approval_recipient_generic($model)
    {
        if ($model->overall_status === 'approved') {
            return null;
        }

        $today = Carbon::today();
        $current_approval_point = WorkflowDefinition::where('approval_order', $model->approval_level)
            ->where('workflow_id', $model->forward_workflow_id)
            ->first();

        if (!$current_approval_point) {
            return null;
        }

        // Check for regular approvers first
        $approver = Approver::where('workflow_dfn_id', $current_approval_point->id)
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today);
            })
            ->first();

        if ($approver) {
            return Staff::where('staff_id', $approver->staff_id)->first();
        }

        // Check for OIC approvers
        $oic_approver = Approver::where('workflow_dfn_id', $current_approval_point->id)
            ->where('oic_staff_id', '!=', null)
            ->where('end_date', '>=', $today)
            ->first();

        if ($oic_approver) {
            return Staff::where('staff_id', $oic_approver->oic_staff_id)->first();
        }

        // Check for division-specific approvers
        if ($current_approval_point->is_division_specific && method_exists($model, 'division') && $model->division) {
            $division = $model->division;
            $staff_id = $division->{$current_approval_point->division_reference_column};
            if ($staff_id) {
                return Staff::where('staff_id', $staff_id)->first();
            }
        }

        return null;
    }
}

if (!function_exists('get_next_approver_generic')) {
    /**
     * Get the next approver for any model.
     */
    function get_next_approver_generic($model)
    {
        if (!$model->forward_workflow_id || !$model->approval_level) {
            return null;
        }

        $current_definition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
            ->where('is_enabled', 1)
            ->where('approval_order', $model->approval_level)
            ->first();

        if (!$current_definition) {
            return null;
        }

        // Check if we need to trigger category check
        $go_to_category_check = false;
        if (method_exists($model, 'division') && $model->division) {
            $go_to_category_check = (!$model->has_extramural && !$model->has_intramural && 
                ($model->approval_level != null && $current_definition->approval_order > $model->approval_level));
        }

        if (($current_definition && $current_definition->triggers_category_check) || $go_to_category_check) {
            if (method_exists($model, 'division') && $model->division) {
                $category_definition = WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
                    ->where('is_enabled', 1)
                    ->where('category', $model->division->category)
                    ->orderBy('approval_order', 'asc')
                    ->first();

                return $category_definition;
            }
        }

        $nextStepIncrement = 1;

        // Skip Directorate from HOD if no directorate
        if ($model->forward_workflow_id > 0 && $current_definition->approval_order == 1) {
            if (method_exists($model, 'division') && $model->division && !$model->division->director_id) {
                $nextStepIncrement = 2;
            }
        }

        $next_approval_order = $model->approval_level + $nextStepIncrement;

        return WorkflowDefinition::where('workflow_id', $model->forward_workflow_id)
            ->where('is_enabled', 1)
            ->where('approval_order', $next_approval_order)
            ->first();
    }
}

if (!function_exists('save_approval_trail_generic')) {
    /**
     * Save approval trail for any model.
     */
    function save_approval_trail_generic($model, $comment, $action, $approvalOrder = null)
    {
        $trail = new ApprovalTrail();
        $trail->model_id = $model->id;
        $trail->model_type = get_class($model);
        $trail->remarks = $comment;
        $trail->action = $action;
        $trail->approval_order = $approvalOrder ?? $model->approval_level ?? 1;
        $trail->staff_id = user_session('staff_id');

        // For activities, also save matrix_id
        if (method_exists($model, 'matrix_id') && $model->matrix_id) {
            $trail->matrix_id = $model->matrix_id;
        }

        $trail->save();

        return $trail;
    }
}

if (!function_exists('get_approval_trails_generic')) {
    /**
     * Get approval trails for any model.
     */
    function get_approval_trails_generic($model)
    {
        return ApprovalTrail::where('model_id', $model->id)
            ->where('model_type', get_class($model))
            ->orderBy('id', 'asc')
            ->get();
    }
}

// Legacy functions for backward compatibility
if (!function_exists('can_take_action')) {
    function can_take_action($matrix)
    {
        return can_take_action_generic($matrix);
    }
}

if (!function_exists('done_approving')) {
    function done_approving($matrix)
    {
        return done_approving_generic($matrix);
    }
}

if (!function_exists('still_with_creator')) {
    function still_with_creator($matrix)
    {
        return is_with_creator_generic($matrix);
    }
}

if (!function_exists('get_matrix_notification_recipient')) {
    function get_matrix_notification_recipient($matrix)
    {
        return get_approval_recipient_generic($matrix);
    }
} 
