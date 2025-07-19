<?php

namespace App\Services;

use App\Models\ApprovalTrail;
use App\Models\WorkflowDefinition;
use App\Models\Approver;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ApprovalService
{
    /**
     * Check if a user can take action on a model.
     */
    public function canTakeAction(Model $model, int $userId): bool
    {
        if (empty($userId) || $this->hasUserApproved($model, $userId) || 
            in_array($model->overall_status, ['approved', 'draft', 'returned'])) {
            return false;
        }

        if ($model->isWithCreator() || !$model->forward_workflow_id) {
            return false;
        }

        $today = Carbon::today();
        $current_approval_point = WorkflowDefinition::where('approval_order', $model->approval_level)
            ->where('workflow_id', $model->forward_workflow_id)
            ->first();

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

        $division_specific_access = false;
        $is_at_my_approval_level = false;

        if ($workflow_dfns->isEmpty()) {
            if ($current_approval_point && $current_approval_point->is_division_specific) {
                if (method_exists($model, 'division') && $model->division) {
                    $division = $model->division;
                    if ($division && $division->{$current_approval_point->division_reference_column} == $userId) {
                        $division_specific_access = true;
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

        return (($is_at_my_approval_level || $model->isWithCreator() || $division_specific_access) && 
                $model->overall_status !== 'approved');
    }

    /**
     * Check if a user has already approved a model.
     */
    public function hasUserApproved(Model $model, int $userId): bool
    {
        $approval = ApprovalTrail::where('model_id', $model->id)
            ->where('model_type', get_class($model))
            ->where('action', 'approved')
            ->where('approval_order', $model->approval_level)
            ->where('staff_id', $userId)
            ->first();

        return $approval !== null;
    }

    /**
     * Get the notification recipient for a model.
     */
    public function getNotificationRecipient(Model $model)
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

    /**
     * Process approval action for any model.
     */
    public function processApproval(Model $model, string $action, string $comment = null, int $userId = null): void
    {
        $userId = $userId ?? user_session('staff_id');
        
        // Save approval trail
        $trail = new ApprovalTrail();
        $trail->model_id = $model->id;
        $trail->model_type = get_class($model);
        $trail->remarks = $comment ?? '';
        $trail->action = $action;
        $trail->approval_order = $model->approval_level ?? 1;
        $trail->staff_id = $userId;

        // For activities, also save matrix_id
        if (method_exists($model, 'matrix_id') && $model->matrix_id) {
            $trail->matrix_id = $model->matrix_id;
        }

        $trail->save();

        // Update model status
        if ($action !== 'approved') {
            $model->forward_workflow_id = 1;
            $model->approval_level = 1;
            $model->overall_status = 'returned';
        } else {
            $next_approver = $this->getNextApprover($model);
            
            if ($next_approver) {
                $model->forward_workflow_id = $next_approver->workflow_id;
                $model->approval_level = $next_approver->approval_order;
                $model->next_approval_level = $next_approver->approval_order;
                $model->overall_status = 'pending';
            } else {
                $model->overall_status = 'approved';
            }
        }

        $model->update();
    }

    /**
     * Get the next approver for a model.
     */
    public function getNextApprover(Model $model)
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

    /**
     * Get approval trails for a model.
     */
    public function getApprovalTrails(Model $model)
    {
        return ApprovalTrail::where('model_id', $model->id)
            ->where('model_type', get_class($model))
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Check if model is still with creator.
     */
    public function isWithCreator(Model $model): bool
    {
        return $model->forward_workflow_id === null || $model->approval_level === 0;
    }
} 