<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

class ApprovalTrail extends Model
{
    use HasFactory, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'model_id',
        'model_type',
        'matrix_id', // For activities that are tied to matrices
        'staff_id',
        'oic_staff_id',
        'action',
        'remarks',
        'approval_order',
        'forward_workflow_id',
        'is_archived',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'model_id' => 'integer',
            'matrix_id' => 'integer',
            'staff_id' => 'integer',
            'oic_staff_id' => 'integer',
            'approval_order' => 'integer',
            'forward_workflow_id' => 'integer',
            'is_archived' => 'boolean',
        ];
    }

    /**
     * Get the model that owns the approval trail.
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo('model');
    }

    /**
     * Get the matrix that owns the approval trail (for activities).
     */
    public function matrix(): BelongsTo
    {
        return $this->belongsTo(Matrix::class);
    }

    /**
     * Get the staff member who made the approval action.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    /**
     * Get the OIC staff member.
     */
    public function oicStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'oic_staff_id', 'staff_id');
    }

    /**
     * Get the approver role for this approval.
     */
    public function approverRole(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'approval_order', 'approval_order');
    }

    /**
     * Get the workflow definition for this approval.
     */
    public function workflowDefinition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'approval_order', 'approval_order');
    }

    /**
     * Get the approver role name for this approval.
     */
    public function getApproverRoleNameAttribute()
    {
        $model = $this->approvable;
        if (!$model) {
            return 'Unknown';
        }

        // For resubmissions, always show "Head of Division" since resubmissions are typically done by HODs
        if ($this->action === 'resubmitted') {
            return 'Head of Division';
        }

        // For other actions, use category-based routing if multiple definitions exist at the same level
        $workflowDefinitions = WorkflowDefinition::where('approval_order', $this->approval_order)
            ->where('workflow_id', $this->forward_workflow_id)
            ->get();

        if ($workflowDefinitions->count() > 1) {
            // Multiple definitions at this level - use category-based routing
            $division = null;
            if ($model->matrix) {
                $division = $model->matrix->division;
            } elseif (isset($model->division)) {
                $division = $model->division;
            }
            
            if ($division && $division->category) {
                // Find the definition that matches the division category
                $workflowDefinition = $workflowDefinitions->where('category', $division->category)->first();
                if ($workflowDefinition) {
                    return $workflowDefinition->role;
                }
            }
        }

        // Fallback to first available definition
        $workflowDefinition = $workflowDefinitions->first();
        return $workflowDefinition ? $workflowDefinition->role : 'Focal Person';
    }

    /**
     * Scope to get approvals for a specific model type.
     */
    public function scopeForModel($query, $modelType)
    {
        return $query->where('model_type', $modelType);
    }

    /**
     * Scope to get approvals for a specific model instance.
     */
    public function scopeForModelInstance($query, $model)
    {
        return $query->where('model_type', get_class($model))
                    ->where('model_id', $model->id);
    }

    /**
     * Scope to get approvals by action.
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to get approvals by staff member.
     */
    public function scopeByStaff($query, $staffId)
    {
        return $query->where('staff_id', $staffId);
    }

    /**
     * Scope to get non-archived approval trails.
     */
    public function scopeActive($query)
    {
        return $query->where('is_archived', 0);
    }

    /**
     * Scope to get archived approval trails.
     */
    public function scopeArchived($query)
    {
        return $query->where('is_archived', 1);
    }
} 