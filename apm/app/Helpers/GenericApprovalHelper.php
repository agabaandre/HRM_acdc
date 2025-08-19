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
        $userId = $userId ?? user_session('staff_id');
        
        if (empty($userId) || done_approving_generic($model, $userId) || 
            in_array($model->overall_status, ['approved', 'draft', 'returned'])) {
            return false;
        }

        // Check if the model is still in draft status (using is_draft flag if available)
        if (property_exists($model, 'is_draft') && $model->is_draft) {
            return false;
        }

        // Check if user is the creator and has no special approval authority
        $isCreator = is_with_creator_generic($model);
        $hasNoWorkflow = !$model->forward_workflow_id;
        
        // If user is creator with no workflow, they can't approve
        if ($isCreator && $hasNoWorkflow) {
            return false;
        }

        $today = Carbon::today();
        $current_approval_point = WorkflowDefinition::where('approval_order', $model->approval_level)
            ->where('workflow_id', $model->forward_workflow_id)
            ->first();

        // Debug: Log the current approval point
        if (config('app.debug')) {
            \Log::info('Current Approval Point Debug', [
                'model_id' => $model->id,
                'approval_level' => $model->approval_level,
                'workflow_id' => $model->forward_workflow_id,
                'current_approval_point' => $current_approval_point ? $current_approval_point->toArray() : null
            ]);
        }

        if (!$current_approval_point) {
            return false;
        }

        $workflow_dfns = Approver::where('staff_id', $userId)
            ->where('workflow_dfn_id', $current_approval_point->id)
            ->orWhere(function ($query) use ($today, $userId, $current_approval_point) {
                $query->where('workflow_dfn_id', $current_approval_point->id)
                    ->where('oic_staff_id', $userId)
                    ->where('end_date', '>=', $today);
            })
            ->orderBy('id', 'desc')
            ->pluck('workflow_dfn_id');

        // Debug: Log the workflow definitions found
        if (config('app.debug')) {
            \Log::info('Workflow Definitions Debug', [
                'user_id' => $userId,
                'current_approval_point_id' => $current_approval_point->id,
                'workflow_dfns' => $workflow_dfns->toArray()
            ]);
        }

        $division_specific_access = false;
        $is_at_my_approval_level = false;

        if ($workflow_dfns->isEmpty()) {
            if ($current_approval_point && $current_approval_point->is_division_specific) {
                if (method_exists($model, 'division') && $model->division || $model->staff->division) {
                    $division = $model->division ?? $model->staff->division;
                      if (config('app.debug')) {
                        \Log::info('Division Data', $division->toArray());
                      }

                    if ($division && $division->{$current_approval_point->division_reference_column} == $userId) {
                        $division_specific_access = true;

                     if (config('app.debug')) {
                        \Log::info('Division Access', ['Has Access'=>$division_specific_access]);
                      }
                    }else{
                        if (config('app.debug')) {
                        \Log::info('No Division Access', [
                            'Ref column'=>$current_approval_point->division_reference_column,
                            'div'=>$division
                        ]);
                      }
                    }
                }else{
                     if (config('app.debug')) {
                        \Log::info('Division Data', []);
                      }
                }
            }
        } else {
            $next_definition = WorkflowDefinition::whereIn('workflow_id', $workflow_dfns->toArray())
                ->where('approval_order', (int) $model->approval_level)
                ->where('is_enabled', 1)
                ->orderBy('approval_order')
                ->get();

            if ($next_definition->count() > 1) {
                if (method_exists($model, 'has_extramural') && $model->has_extramural && 
                    $model->approval_level !== $current_approval_point->first()->approval_order) {
                    $current_approval_point = $next_definition->where('fund_type', 2);
                } else {
                    $current_approval_point = $next_definition->where('fund_type', 1);
                }
            }

            $is_at_my_approval_level = ($current_approval_point) ? 
                ($current_approval_point->workflow_id === $model->forward_workflow_id && 
                 $model->approval_level == $current_approval_point->approval_order) : false;
        }

        // Allow approval if:
        // 1. User is at the correct approval level, OR
        // 2. User has division-specific access (even if they are the creator), OR
        // 3. User is not the creator but has workflow access
        $canTakeAction = (($is_at_my_approval_level || $division_specific_access || 
                          (!$isCreator && $workflow_dfns->isNotEmpty())) && 
                          $model->overall_status !== 'approved');

        // Debug: Log the final decision
        if (config('app.debug')) {
            \Log::info('Can Take Action Final Debug', [
                'model_id' => $model->id,
                'user_id' => $userId,
                'is_at_my_approval_level' => $is_at_my_approval_level,
                'is_with_creator' => $isCreator,
                'division_specific_access' => $division_specific_access,
                'has_workflow_access' => $workflow_dfns->isNotEmpty(),
                'overall_status' => $model->overall_status,
                'can_take_action' => $canTakeAction
            ]);
        }

        return $canTakeAction;
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
        return $model->forward_workflow_id === null || $model->approval_level === 0;
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