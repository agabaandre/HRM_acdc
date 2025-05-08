<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
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
        'matrix_id',
        'staff_id',
        'date_from',
        'date_to',
        'location_id',
        'total_participants',
        'internal_participants',
        'budget_id',
        'key_result_area',
        'request_type_id',
        'activity_title',
        'background',
        'activity_request_remarks',
        'is_sepecial_memo',
        'budget',
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
            'matrix_id' => 'integer',
            'staff_id' => 'integer',
            'date_from' => 'date',
            'date_to' => 'date',
            'location_id' => 'array',
            'internal_participants' => 'array',
            'budget_id' => 'array',
            'request_type_id' => 'integer',
            'is_sepecial_memo' => 'boolean',
            'budget' => 'array',
            'attachment' => 'array',
        ];
    }

    public function matrix(): BelongsTo
    {
        return $this->belongsTo(Matrix::class);
    }

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
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

    public function activityApprovalTrails(): HasMany
    {
        return $this->hasMany(ActivityApprovalTrail::class);
    }
}
