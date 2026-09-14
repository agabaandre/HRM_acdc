<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

class ActivityApprovalTrail extends Model
{
    use HasFactory, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'matrix_id',
        'activity_id',
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
    protected $casts = [
        'id' => 'integer',
        'matrix_id' => 'integer',
        'activity_id' => 'integer',
        'staff_id' => 'integer',
        'oic_staff_id' => 'integer',
        'approval_order' => 'integer',
        'forward_workflow_id' => 'integer',
        'is_archived' => 'boolean',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function matrix(): BelongsTo
    {
        return $this->belongsTo(Matrix::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class,"staff_id","staff_id");
    }

    public function inchargeStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class,"oic_staff_id","staff_id");
    }

    public function oicStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class,"oic_staff_id","staff_id");
    }

    public function approverRole(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'approval_order', 'approval_order');
    }

    public function workflowDefinition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'approval_order', 'approval_order')
            ->where('workflow_id', $this->forward_workflow_id);
    }

    /**
     * Get the approver role name for this approval.
     */
    public function getApproverRoleNameAttribute()
    {
       
        $workflowDefinition = WorkflowDefinition::where('approval_order', $this->approval_order)
            ->where('workflow_id', $this->forward_workflow_id)
            ->first();

        return $workflowDefinition ? $workflowDefinition->role : 'Focal Person';
    }

    /**
     * Map activity_approval_trails.action when promoting rows to approval_trails (convert to single memo).
     * Matrix activity trails use "passed"; single-memo workflow expects "approved".
     * The convert action is stored as "convert_to_single_memo"; promoted trail should read as "returned".
     */
    public static function mapActionForPromotionToApprovalTrail(?string $action): string
    {
        $normalized = strtolower((string) $action);

        return match ($normalized) {
            'passed' => 'approved',
            'convert_to_single_memo', 'converted_to_single_memo' => 'returned',
            default => $action ?? '',
        };
    }

    /**
     * Insert a row into approval_trails when promoting matrix activity trails to single-memo workflow.
     * Preserves created_at / updated_at from the source activity trail.
     */
    public static function createPromotedApprovalTrail(int $activityId, self $t): ApprovalTrail
    {
        $trail = new ApprovalTrail([
            'model_id' => $activityId,
            'model_type' => Activity::class,
            'matrix_id' => $t->matrix_id,
            'staff_id' => $t->staff_id,
            'oic_staff_id' => $t->oic_staff_id,
            'action' => self::mapActionForPromotionToApprovalTrail($t->action),
            'remarks' => $t->remarks,
            'approval_order' => $t->approval_order,
            'forward_workflow_id' => $t->forward_workflow_id,
            'is_archived' => $t->is_archived ?? 0,
        ]);
        $trail->created_at = $t->created_at;
        $trail->updated_at = $t->updated_at ?? $t->created_at;
        $trail->save(['timestamps' => false]);

        return $trail;
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
