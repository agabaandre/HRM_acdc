<?php

namespace App\Models;

use App\Traits\HasApprovalWorkflow;
use App\Traits\HasDocumentNumber;
use iamfarhad\LaravelAuditLog\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityReport extends Model
{
    use Auditable;
    use HasApprovalWorkflow;
    use HasDocumentNumber;

    protected $table = 'activity_reports';

    protected $fillable = [
        'source_id',
        'source_type',
        'mission_type',
        'document_number',
        'division_id',
        'staff_id',
        'responsible_person_id',
        'forward_workflow_id',
        'reverse_workflow_id',
        'approval_level',
        'next_approval_level',
        'overall_status',
        'approval_order_map',
        'not_executed',
        'has_been_submitted',
        'tier2',
        'tier4',
        'activity_name_code',
        'broad_activity_amert',
        'start_date',
        'end_date',
        'duration_days',
        'objectives',
        'location_venue',
        'lead_organiser_staff_id',
        'other_organiser_staff_ids',
        'participants_count',
        'member_states_covered',
        'key_strategic_needs',
        'narrative_achievements',
        'challenges_recommendations',
        'means_of_verification',
        'budget_code',
        'total_cost_usd',
        'logistics_planned',
        'logistics_actuals',
        'mission_extras',
        'attachments',
        'created_by',
        'updated_by',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'division_id' => 'integer',
            'staff_id' => 'integer',
            'responsible_person_id' => 'integer',
            'forward_workflow_id' => 'integer',
            'reverse_workflow_id' => 'integer',
            'approval_order_map' => 'array',
            'not_executed' => 'boolean',
            'has_been_submitted' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'duration_days' => 'integer',
            'lead_organiser_staff_id' => 'integer',
            'other_organiser_staff_ids' => 'array',
            'participants_count' => 'integer',
            'total_cost_usd' => 'decimal:2',
            'mission_extras' => 'array',
            'attachments' => 'array',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    public function relatedLinks(): HasMany
    {
        return $this->hasMany(ActivityReportRelated::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function responsiblePerson(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_person_id', 'staff_id');
    }

    public function forwardWorkflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'forward_workflow_id');
    }
}
