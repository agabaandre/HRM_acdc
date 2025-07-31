<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasApprovalWorkflow;

class NonTravelMemo extends Model
{
    use HasFactory, HasApprovalWorkflow;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'forward_workflow_id',
        'reverse_workflow_id',
        'overall_status',
        'approval_level',
        'next_approval_level',
        'workplan_activity_code',
        'staff_id',
        'memo_date',
        'location_id',
        'non_travel_memo_category_id',
        'budget_id',
        'activity_title',
        'background',
        'activity_request_remarks',
        'justification',
        'budget_breakdown',
        'attachment',
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
            'forward_workflow_id' => 'integer',
            'reverse_workflow_id' => 'integer',
            'approval_level' => 'integer',
            'next_approval_level' => 'integer',
            'staff_id' => 'integer',
            'memo_date' => 'date',
            'location_id' => 'array',
            'non_travel_memo_category_id' => 'integer',
            'budget_id' => 'array',
            'budget_breakdown' => 'array',
            'attachment' => 'array',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function nonTravelMemoCategory(): BelongsTo
    {
        return $this->belongsTo(NonTravelMemoCategory::class, 'non_travel_memo_category_id');
    }

    public function forwardWorkflow(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Workflow::class, 'forward_workflow_id');
    }

    public function reverseWorkflow(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Workflow::class, 'reverse_workflow_id');
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function serviceRequestApprovalTrails(): HasMany
    {
        return $this->hasMany(ServiceRequestApprovalTrail::class);
    }

    public function approvalTrails()
    {
        return $this->morphMany(\App\Models\ApprovalTrail::class, 'model', 'model_type', 'model_id');
    }
}
