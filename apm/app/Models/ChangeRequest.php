<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Traits\HasApprovalWorkflow;
use App\Traits\HasDocumentNumber;
use iamfarhad\LaravelAuditLog\Traits\Auditable;

class ChangeRequest extends Model
{
    use HasFactory, HasApprovalWorkflow, HasDocumentNumber, Auditable;

    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $table = 'change_request';

    protected $fillable = [
        'parent_memo_id',
        'parent_memo_model',
        'activity_id',
        'special_memo_id',
        'non_travel_memo_id',
        'request_arf_id',
        'service_request_id',
        'has_budget_id_changed',
        'has_internal_participants_changed',
        'has_request_type_id_changed',
        'request_type_id',
        'has_total_external_participants_changed',
        'has_location_changed',
        'has_memo_date_changed',
        'has_activity_title_changed',
        'has_activity_request_remarks_changed',
        'has_is_single_memo_changed',
        'has_budget_breakdown_changed',
        'has_status_changed',
        'has_fund_type_id_changed',
        'document_number',
        'forward_workflow_id',
        'workplan_activity_code',
        'matrix_id',
        'division_id',
        'staff_id',
        'responsible_person_id',
        'supporting_reasons',
        'date_from',
        'date_to',
        'memo_date',
        'location_id',
        'total_participants',
        'internal_participants',
        'total_external_participants',
        'division_staff_request',
        'budget_id',
        'key_result_area',
        'justification',
        'non_travel_memo_category_id',
        'activity_title',
        'background',
        'activity_request_remarks',
        'is_single_memo',
        'budget_breakdown',
        'available_budget',
        'attachment',
        'status',
        'fund_type_id',
        'activity_ref',
        'approval_level',
        'next_approval_level',
        'overall_status',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    protected $appends = ['formatted_dates', 'status', 'my_last_action', 'has_passed_at_current_level', 'my_current_level_action'];

    protected $casts = [
        'id' => 'integer',
        'parent_memo_id' => 'integer',
        'activity_id' => 'integer',
        'special_memo_id' => 'integer',
        'non_travel_memo_id' => 'integer',
        'request_arf_id' => 'integer',
        'service_request_id' => 'integer',
        'request_type_id' => 'integer',
        'fund_type_id' => 'integer',
        'matrix_id' => 'integer',
        'division_id' => 'integer',
        'staff_id' => 'integer',
        'responsible_person_id' => 'integer',
        'non_travel_memo_category_id' => 'integer',
        'forward_workflow_id' => 'integer',
        'approval_level' => 'integer',
        'next_approval_level' => 'integer',
        'total_participants' => 'integer',
        'total_external_participants' => 'integer',
        'available_budget' => 'decimal:2',
        'date_from' => 'date',
        'date_to' => 'date',
        'memo_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        
        // JSON fields
        'location_id' => 'array',
        'internal_participants' => 'array',
        'division_staff_request' => 'array',
        'budget_id' => 'array',
        'budget_breakdown' => 'array',
        'attachment' => 'array',
        
        // Boolean fields
        'has_budget_id_changed' => 'boolean',
        'has_internal_participants_changed' => 'boolean',
        'has_request_type_id_changed' => 'boolean',
        'has_total_external_participants_changed' => 'boolean',
        'has_location_changed' => 'boolean',
        'has_memo_date_changed' => 'boolean',
        'has_activity_title_changed' => 'boolean',
        'has_activity_request_remarks_changed' => 'boolean',
        'has_is_single_memo_changed' => 'boolean',
        'has_budget_breakdown_changed' => 'boolean',
        'has_status_changed' => 'boolean',
        'has_fund_type_id_changed' => 'boolean',
        'is_single_memo' => 'boolean',
    ];

    /**
     * Get the parent memo (polymorphic relationship)
     */
    public function parentMemo(): MorphTo
    {
        return $this->morphTo('parent_memo', 'parent_memo_model', 'parent_memo_id');
    }

    /**
     * Get the activity that this change request is for
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Get the special memo that this change request is for
     */
    public function specialMemo(): BelongsTo
    {
        return $this->belongsTo(SpecialMemo::class);
    }

    /**
     * Get the non-travel memo that this change request is for
     */
    public function nonTravelMemo(): BelongsTo
    {
        return $this->belongsTo(NonTravelMemo::class);
    }

    /**
     * Get the request ARF that this change request is for
     */
    public function requestArf(): BelongsTo
    {
        return $this->belongsTo(RequestArf::class);
    }

    /**
     * Get the service request that this change request is for
     */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /**
     * Get the matrix that this change request belongs to
     */
    public function matrix(): BelongsTo
    {
        return $this->belongsTo(Matrix::class);
    }

    /**
     * Get the division that this change request belongs to
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Get the staff member who created this change request
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get the responsible person for this change request
     */
    public function responsiblePerson(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_person_id');
    }

    /**
     * Get the request type for this change request
     */
    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    /**
     * Get the fund type for this change request
     */
    public function fundType(): BelongsTo
    {
        return $this->belongsTo(FundType::class);
    }

    /**
     * Get the non-travel memo category for this change request
     */
    public function nonTravelMemoCategory(): BelongsTo
    {
        return $this->belongsTo(NonTravelMemoCategory::class);
    }

    /**
     * Get the forward workflow for this change request
     */
    public function forwardWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'forward_workflow_id');
    }

    /**
     * Get the approval trails for this change request
     */
    public function approvalTrails(): HasMany
    {
        return $this->hasMany(ApprovalTrail::class, 'model_id')
            ->where('model_type', 'change_request');
    }

    /**
     * Get the budget items for this change request
     * Note: These models don't exist yet, so commenting out for now
     */
    // public function budgetItems(): HasMany
    // {
    //     return $this->hasMany(ChangeRequestBudget::class);
    // }

    /**
     * Get the participants for this change request
     * Note: These models don't exist yet, so commenting out for now
     */
    // public function participants(): HasMany
    // {
    //     return $this->hasMany(ChangeRequestParticipant::class);
    // }

    /**
     * Scope to get change requests by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('overall_status', $status);
    }

    /**
     * Scope to get change requests by approval level
     */
    public function scopeByApprovalLevel($query, $level)
    {
        return $query->where('approval_level', $level);
    }

    /**
     * Scope to get change requests for a specific staff member
     */
    public function scopeForStaff($query, $staffId)
    {
        return $query->where('staff_id', $staffId);
    }

    /**
     * Scope to get change requests for a specific division
     */
    public function scopeForDivision($query, $divisionId)
    {
        return $query->where('division_id', $divisionId);
    }

    /**
     * Get the current approver role for this change request
     */
    public function getCurrentApproverRoleAttribute()
    {
        // This would be implemented based on your workflow logic
        return 'Approver';
    }

    /**
     * Check if this change request has any changes
     */
    public function hasAnyChanges(): bool
    {
        return $this->has_budget_id_changed ||
               $this->has_internal_participants_changed ||
               $this->has_request_type_id_changed ||
               $this->has_total_external_participants_changed ||
               $this->has_location_changed ||
               $this->has_memo_date_changed ||
               $this->has_activity_title_changed ||
               $this->has_activity_request_remarks_changed ||
               $this->has_is_single_memo_changed ||
               $this->has_budget_breakdown_changed ||
               $this->has_status_changed ||
               $this->has_fund_type_id_changed;
    }

    /**
     * Get the summary of changes made
     */
    public function getChangesSummaryAttribute(): array
    {
        $changes = [];
        
        if ($this->has_budget_id_changed) $changes[] = 'Budget Code';
        if ($this->has_internal_participants_changed) $changes[] = 'Internal Participants';
        if ($this->has_request_type_id_changed) $changes[] = 'Request Type';
        if ($this->has_total_external_participants_changed) $changes[] = 'External Participants';
        if ($this->has_location_changed) $changes[] = 'Location';
        if ($this->has_memo_date_changed) $changes[] = 'Memo Date';
        if ($this->has_activity_title_changed) $changes[] = 'Activity Title';
        if ($this->has_activity_request_remarks_changed) $changes[] = 'Activity Remarks';
        if ($this->has_is_single_memo_changed) $changes[] = 'Single Memo Status';
        if ($this->has_budget_breakdown_changed) $changes[] = 'Budget Breakdown';
        if ($this->has_status_changed) $changes[] = 'Status';
        if ($this->has_fund_type_id_changed) $changes[] = 'Fund Type';
        
        return $changes;
    }
}