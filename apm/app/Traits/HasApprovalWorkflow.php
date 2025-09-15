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
            // Division-specific approver (e.g., HOD, Director)
            $referenceColumn = $role->division_reference_column;
            $today = Carbon::today();
            
            // Check for active OIC first (if available)
            // Map reference columns to their OIC column names
            $oicColumnMap = [
                'division_head' => 'head_oic_id',
                'finance_officer' => 'finance_officer_oic_id', // This might need to be added to the division table
                'director_id' => 'director_oic_id'
            ];
            
            $oicColumn = $oicColumnMap[$referenceColumn] ?? $referenceColumn . '_oic_id';
            $oicStartColumn = str_replace('_oic_id', '_oic_start_date', $oicColumn);
            $oicEndColumn = str_replace('_oic_id', '_oic_end_date', $oicColumn);
            
            if (isset($this->division->$oicColumn) && $this->division->$oicColumn) {
                $isOicActive = true;
                if (isset($this->division->$oicStartColumn) && $this->division->$oicStartColumn) {
                    $isOicActive = $isOicActive && $this->division->$oicStartColumn <= $today;
                }
                if (isset($this->division->$oicEndColumn) && $this->division->$oicEndColumn) {
                    $isOicActive = $isOicActive && $this->division->$oicEndColumn >= $today;
                }
                
                if ($isOicActive) {
                    $staff_id = $this->division->$oicColumn;
                }
            }
            
            // If no active OIC, check primary approver
            if (!$staff_id && isset($this->division->$referenceColumn)) {
                $staff_id = $this->division->$referenceColumn;
            }
        } else {
            // Regular approver from approvers table
            $today = Carbon::today();
            
            // First, check for OIC (Officer in Charge) if active
            $oicApprover = Approver::where('workflow_dfn_id', $role->id)
                ->whereNotNull('oic_staff_id')
                ->where(function ($query) use ($today) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', $today);
                })
                ->with('oicStaff')
                ->first();
            
            if ($oicApprover && $oicApprover->oicStaff) {
                return $oicApprover->oicStaff;
            }
            
            // If no active OIC, get regular approver
            $approver = Approver::where('workflow_dfn_id', $role->id)
                ->where(function ($query) use ($today) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', $today);
                })
                ->with('staff')
                ->first();
            
            if ($approver && $approver->staff) {
                return $approver->staff;
            }
            
            $staff_id = $approver ? $approver->staff_id : null;
        }

        return $staff_id ? Staff::select('lname', 'fname', 'staff_id', 'job_name', 'division_name')
            ->where('staff_id', $staff_id)
            ->first() : null;
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
        // if (property_exists($this, 'is_draft')) {
        //     return $this->is_draft === true;
        // }
        
        // Fallback to overall_status check
        return $this->overall_status === 'draft';
    }

    /**
     * Get approval level display with role name and approver name.
     */
    public function getApprovalLevelDisplayAttribute(): string
    {
        if ($this->isDraft() && method_exists($this, 'staff') && $this->staff) {
            return 'Draft (' . $this->staff->fname . ' ' . $this->staff->lname . ')';
        }

        if ($this->approval_level && $this->forward_workflow_id && $this->workflow_definition) {
            $display = $this->workflow_definition->role;
            
            if ($this->current_actor) {
                $display .= ' (' . $this->current_actor->fname . ' ' . $this->current_actor->lname . ')';
            }
            
            return $display;
        }

        return 'N/A';
    }

    /**
     * Get status badge CSS class.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        $statusClasses = [
            'draft' => 'bg-secondary',
            'pending' => 'bg-warning',
            'approved' => 'bg-success',
            'rejected' => 'bg-danger',
            'returned' => 'bg-info',
        ];

        return $statusClasses[$this->overall_status] ?? 'bg-secondary';
    }

    /**
     * Get formatted date range.
     */
    public function getFormattedDateRangeAttribute(): string
    {
        if ($this->date_from && $this->date_to) {
            return $this->date_from->format('M d, Y') . ' - ' . $this->date_to->format('M d, Y');
        }
        
        return 'N/A';
    }

    /**
     * Get the next approver in the workflow.
     */
    public function getNextApprover()
    {
        if (!$this->forward_workflow_id || !$this->approval_level) {
            return null;
        }

        // Use the ApprovalService for consistent logic
        $approvalService = app(\App\Services\ApprovalService::class);
        return $approvalService->getNextApprover($this);
    }

    /**
     * Save an approval trail entry.
     */
    public function saveApprovalTrail(string $comment, string $action, int $approvalOrder = null): ApprovalTrail
    {
        $trail = new ApprovalTrail();
        $trail->model_id = $this->id;
        $trail->model_type = get_class($this);
        $trail->forward_workflow_id = $this->forward_workflow_id;
        $trail->remarks = $comment;
        $trail->action = $action;
        
        // For submission, use approval level 0, otherwise use the current approval level
        if ($action === 'submitted') {
            $trail->approval_order = (($this->approval_level)==1)?0:1;
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
            // Get the assigned workflow ID for this model
            $modelName = class_basename($this);
            $assignedWorkflowId = \App\Models\WorkflowModel::getWorkflowIdForModel($modelName);
            if (!$assignedWorkflowId) {
                $assignedWorkflowId = 1; // Default fallback
            }
            
            $this->forward_workflow_id = $assignedWorkflowId;
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

        send_matrix_email_notification($this, $action);
        $this->update();
    }

    /**
     * Submit for approval.
     */
    public function submitForApproval(): void
    {

        $last_approval_trail = ApprovalTrail::where('model_id',$this->id)->where('model_type', get_class($this))->whereNotIn('action',['approved','submitted'])->orderByDesc('id')->first();
        if($last_approval_trail){
            $workflow_defn       = WorkflowDefinition::where('approval_order', $last_approval_trail->approval_order)->first();
            $last_workflow_id    = $workflow_defn->workflow_id;
            $last_approval_order = $last_approval_trail->approval_order;
        }
        else {
            // Get the assigned workflow ID for this model
            $modelName = class_basename($this);
            $assignedWorkflowId = \App\Models\WorkflowModel::getWorkflowIdForModel($modelName);
            if (!$assignedWorkflowId) {
                $assignedWorkflowId = 1; // Default fallback
            }
            $last_approval_order = 1; 
            $last_workflow_id = $assignedWorkflowId;
        }
        $this->overall_status = 'pending';
        $this->approval_level = $last_approval_order;
        $this->forward_workflow_id = $last_workflow_id;
        
        // Set is_draft to false when submitting for approval
        if (property_exists($this, 'is_draft')) {
            $this->is_draft = false;
        }
        
        $this->save();

        send_matrix_email_notification($this, 'approval');

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
    public function hasActivities(): bool
    { 
        if(method_exists($this, 'activities')){
            return $this->activities()->exists();
        }
        return false;
    }


    public function getHasIntramuralAttribute()
    {
        if (method_exists($this, 'activities')) {
            // If the model has activities, check for any with fund_type_id = 1 (intramural)
            return $this->activities()->where('fund_type_id', 1)->exists();
            
        } elseif ($this->budget_breakdown && !empty($this->budget_breakdown)) {
            
            // If the model has a budget_breakdown property, check for any fund_code with fund_type_id = 1
            // Handle both JSON string and PHP array formats
            $breakdown = $this->budget_breakdown;
            
            // If it's a string, decode it to array
            if (is_string($breakdown)) {
                $breakdown = json_decode($breakdown, true);
            }
            
            // If it's an array, process it
            if (is_array($breakdown)) {
                foreach ($breakdown as $fund_code_id => $items) {
                    // Skip non-numeric keys (like 'grand_total')
                    if (!is_numeric($fund_code_id)) {
                        continue;
                    }
                    $fundCode = \App\Models\FundCode::find($fund_code_id);
                    if ($fundCode && $fundCode->fund_type_id == 1) {
                        return true;
                    }
                }
            }
            return false;
        }
        return false;
    }

    public function getHasExtramuralAttribute(): bool
    {
        if (method_exists($this, 'activities')) {
            // If the model has activities, check for any with fund_type_id = 2 (extramural)
            return $this->activities()->where('fund_type_id', 2)->exists();
            
        } elseif ($this->budget_breakdown && !empty($this->budget_breakdown)) {
            
            // If the model has a budget_breakdown property, check for any fund_code with fund_type_id = 2 (extramural)
            // Handle both JSON string and PHP array formats
            $breakdown = $this->budget_breakdown;
            
            // If it's a string, decode it to array
            if (is_string($breakdown)) {
                $breakdown = json_decode($breakdown, true);
            }
            
            // If it's an array, process it
            if (is_array($breakdown)) {
                foreach ($breakdown as $fund_code_id => $items) {
                    // Skip non-numeric keys (like 'grand_total')
                    if (!is_numeric($fund_code_id)) {
                        continue;
                    }
                    $fundCode = \App\Models\FundCode::find($fund_code_id);
                    if ($fundCode && $fundCode->fund_type_id == 2) {
                        return true;
                    }
                }
            }
            return false;
        }
        return false;
    }

  
    
} 