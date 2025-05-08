<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NonTravelMemo extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'forward_workflow_id',
        'reverse_workflow_id',
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
        return $this->belongsTo(NonTravelMemoCategory::class);
    }

    public function forwardWorkflow(): BelongsTo
    {
        return $this->belongsTo(ForwardWorkflow::class);
    }

    public function reverseWorkflow(): BelongsTo
    {
        return $this->belongsTo(ReverseWorkflow::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function serviceRequestApprovalTrails(): HasMany
    {
        return $this->hasMany(ServiceRequestApprovalTrail::class);
    }
}
