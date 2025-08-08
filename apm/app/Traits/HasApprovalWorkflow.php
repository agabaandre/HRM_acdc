<?php

namespace App\Traits;

use App\Models\ApprovalTrail;
use App\Models\WorkflowDefinition;
use App\Models\Approver;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasApprovalWorkflow
{
    /**
     * Get the approval trails for this model.
     */
    public function approvalTrails(): MorphMany
    {
        return $this->morphMany(ApprovalTrail::class, 'model');
    }

    /**
     * Get the workflow definition for the current approval level.
     */
    public function getWorkflowDefinitionAttribute()
    {
        if (!$this->forward_workflow_id || !$this->approval_level) {
            return null;
        }

        $definitions = WorkflowDefinition::where('approval_order', $this->approval_level)
            ->where('workflow_id', $this->forward_workflow_id)
            ->where('is_enabled', 1)
            ->get();

        if ($definitions->count() > 1 && $definitions[0]->category) {
            // For models that have division relationship
            if (method_exists($this, 'division') && $this->division) {
                return $definitions->where('category', $this->division->category)->first();
            }
        }

        return $definitions->count() > 0 ? $definitions[0] : null;
    }

    /**
     * Get the current actor (approver) for this model.
     */
    public function getCurrentActorAttribute()
    {
        if ($this->overall_status == 'approved') {
            return null;
        }

        $role = $this->workflow_definition;
        if (!$role) {
            return null;
        }

        $staff_id = null;

        if ($role->is_division_specific && method_exists($this, 'division') && $this->division) {
            $staff_id = $this->division->{$role->division_reference_column};
        } else {
            $approver = Approver::where('workflow_dfn_id', $role->id)->first();
            $staff_id = $approver ? $approver->staff_id : null;
        }

        return $staff_id ? Staff::select('lname', 'fname', 'staff_id')->where('staff_id', $staff_id)->first() : null;
    }

    /**
     * Check if the model is still with the creator.
     */
    public function isWithCreator(): bool
    {
        return $this->forward_workflow_id === null || $this->approval_level === 0 || $this->approval_level === null;
    }

    /**
     * Check if the model is approved.
     */
    public function isApproved(): bool
    {
        return $this->overall_status === 'approved';
    }

    /**
     * Check if the model is pending approval.
     */
    public function isPending(): bool
    {
        return $this->overall_status === 'pending';
    }

    /**
     * Check if the model is returned.
     */
    public function isReturned(): bool
    {
        return $this->overall_status === 'returned';
    }

    /**
     * Check if the model is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->overall_status === 'draft';
    }

    /**
     * Check if the model is in draft status using the is_draft flag.
     */
    public function isDraftStatus(): bool
    {
        // Check if the model has the is_draft property and it's true
        if (property_exists($this, 'is_draft')) {
            return $this->is_draft === true;
        }
        
        // Fallback to overall_status check
        return $this->overall_status === 'draft';
    }

    /**
     * Get the next approver in the workflow.
     */
    public function getNextApprover()
    {
        if (!$this->forward_workflow_id || !$this->approval_level) {
            return null;
        }

        $current_definition = WorkflowDefinition::where('workflow_id', $this->forward_workflow_id)
            ->where('is_enabled', 1)
            ->where('approval_order', $this->approval_level)
            ->first();

        if (!$current_definition) {
            return null;
        }

        // Check if we need to trigger category check
        $go_to_category_check = false;
        if (method_exists($this, 'division') && $this->division) {
            $go_to_category_check = (!$this->has_extramural && !$this->has_intramural && 
                ($this->approval_level != null && $current_definition->approval_order > $this->approval_level));
        }

        if (($current_definition && $current_definition->triggers_category_check) || $go_to_category_check) {
            if (method_exists($this, 'division') && $this->division) {
                $category_definition = WorkflowDefinition::where('workflow_id', $this->forward_workflow_id)
                    ->where('is_enabled', 1)
                    ->where('category', $this->division->category)
                    ->orderBy('approval_order', 'asc')
                    ->first();

                return $category_definition;
            }
        }

        $nextStepIncrement = 1;

        // Skip Directorate from HOD if no directorate
        if ($this->forward_workflow_id > 0 && $current_definition->approval_order == 1) {
            if (method_exists($this, 'division') && $this->division && !$this->division->director_id) {
                $nextStepIncrement = 2;
            }
        }

        $next_approval_order = $this->approval_level + $nextStepIncrement;

        return WorkflowDefinition::where('workflow_id', $this->forward_workflow_id)
            ->where('is_enabled', 1)
            ->where('approval_order', $next_approval_order)
            ->first();
    }

    /**
     * Save an approval trail entry.
     */
    public function saveApprovalTrail(string $comment, string $action, int $approvalOrder = null): ApprovalTrail
    {
        $trail = new ApprovalTrail();
        $trail->model_id = $this->id;
        $trail->model_type = get_class($this);
        $trail->remarks = $comment;
        $trail->action = $action;
        
        // For submission, use approval level 0, otherwise use the current approval level
        if ($action === 'submitted') {
            $trail->approval_order = 1;
        } else {
            $trail->approval_order = $approvalOrder ?? $this->approval_level ?? 1;
        }
        
        $trail->staff_id = user_session('staff_id');

        // For activities, also save matrix_id
        if (method_exists($this, 'matrix_id') && $this->matrix_id) {
            $trail->matrix_id = $this->matrix_id;
        }

        $trail->save();

        return $trail;
    }

    /**
     * Update the approval status.
     */
    public function updateApprovalStatus(string $action, string $comment = null): void
    {
        $this->saveApprovalTrail($comment ?? '', $action);

        if ($action !== 'approved') {
            $this->forward_workflow_id = 1;
            $this->approval_level = 1;
            $this->overall_status = 'returned';
        } else {
            $next_approver = $this->getNextApprover();
            
            if ($next_approver) {
                $this->forward_workflow_id = $next_approver->workflow_id;
                $this->approval_level = $next_approver->approval_order;
                $this->next_approval_level = $next_approver->approval_order;
                $this->overall_status = 'pending';
            } else {
                $this->overall_status = 'approved';
            }
        }

        $this->update();
    }

    /**
     * Submit for approval.
     */
    public function submitForApproval(): void
    {
        $this->overall_status = 'pending';
        $this->approval_level = 1;
        $this->forward_workflow_id = 1;
        
        // Set is_draft to false when submitting for approval
        if (property_exists($this, 'is_draft')) {
            $this->is_draft = false;
        }
        
        $this->save();

        $this->saveApprovalTrail('Submitted for approval', 'submitted');
    }

    /**
     * Get the last approval trail entry.
     */
    public function getLastApprovalTrail()
    {
        return $this->approvalTrails()
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Get approval trails by action.
     */
    public function getApprovalTrailsByAction(string $action)
    {
        return $this->approvalTrails()
            ->where('action', $action)
            ->orderBy('id')
            ->get();
    }
} 