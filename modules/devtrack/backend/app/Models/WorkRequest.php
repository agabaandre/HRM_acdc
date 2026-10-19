<?php

namespace App\Models;

use App\Enums\DataClassification;
use App\Enums\PriorityCategory;
use App\Enums\UrgencyLevel;
use App\Enums\WorkClass;
use App\Enums\WorkRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'title',
        'requesting_unit',
        'directorate',
        'requester_name',
        'requester_email',
        'phone',
        'requester_id',
        'business_owner',
        'problem_statement',
        'proposed_solution',
        'rationale',
        'stakeholders',
        'mandate_alignment',
        'desired_timeline',
        'budget_estimate',
        'existing_alternatives',
        'additional_comments',
        'expected_outcomes',
        'success_measures',
        'target_users',
        'urgency',
        'deadline',
        'urgency_reason',
        'data_classification',
        'existing_systems',
        'integrations_needed',
        'budget_notes',
        'risk_of_not_delivering',
        'work_class',
        'priority_category',
        'score_strategic_alignment',
        'score_impact',
        'score_urgency_risk',
        'score_user_reach',
        'score_feasibility',
        'score_cost_effort',
        'score_security',
        'weighted_score',
        'status',
        'workplan_indicator_id',
        'project_id',
        'created_by',
        'acknowledged_by',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'urgency' => UrgencyLevel::class,
            'data_classification' => DataClassification::class,
            'work_class' => WorkClass::class,
            'priority_category' => PriorityCategory::class,
            'status' => WorkRequestStatus::class,
            'deadline' => 'date',
            'acknowledged_at' => 'datetime',
            'score_strategic_alignment' => 'integer',
            'score_impact' => 'integer',
            'score_urgency_risk' => 'integer',
            'score_user_reach' => 'integer',
            'score_feasibility' => 'integer',
            'score_cost_effort' => 'integer',
            'score_security' => 'integer',
            'weighted_score' => 'integer',
        ];
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(WorkplanIndicator::class, 'workplan_indicator_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'title' => $this->title,
            'requesting_unit' => $this->requesting_unit,
            'directorate' => $this->directorate,
            'requester_name' => $this->requester_name,
            'requester_email' => $this->requester_email,
            'phone' => $this->phone,
            'requester_id' => $this->requester_id,
            'business_owner' => $this->business_owner,
            'problem_statement' => $this->problem_statement,
            'proposed_solution' => $this->proposed_solution,
            'rationale' => $this->rationale,
            'stakeholders' => $this->stakeholders,
            'mandate_alignment' => $this->mandate_alignment,
            'desired_timeline' => $this->desired_timeline,
            'budget_estimate' => $this->budget_estimate,
            'existing_alternatives' => $this->existing_alternatives,
            'additional_comments' => $this->additional_comments,
            'expected_outcomes' => $this->expected_outcomes,
            'success_measures' => $this->success_measures,
            'target_users' => $this->target_users,
            'urgency' => $this->urgency?->value,
            'urgency_label' => $this->urgency?->label(),
            'deadline' => $this->deadline?->toDateString(),
            'urgency_reason' => $this->urgency_reason,
            'data_classification' => $this->data_classification?->value,
            'data_classification_label' => $this->data_classification?->label(),
            'existing_systems' => $this->existing_systems,
            'integrations_needed' => $this->integrations_needed,
            'budget_notes' => $this->budget_notes,
            'risk_of_not_delivering' => $this->risk_of_not_delivering,
            'work_class' => $this->work_class?->value,
            'work_class_label' => $this->work_class?->label(),
            'priority_category' => $this->priority_category?->value,
            'priority_label' => $this->priority_category?->label(),
            'scores' => [
                'strategic_alignment' => $this->score_strategic_alignment,
                'impact' => $this->score_impact,
                'urgency_risk' => $this->score_urgency_risk,
                'user_reach' => $this->score_user_reach,
                'feasibility' => $this->score_feasibility,
                'cost_effort' => $this->score_cost_effort,
                'security' => $this->score_security,
            ],
            'weighted_score' => $this->weighted_score,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'indicator' => $this->indicator ? [
                'id' => $this->indicator->id,
                'code' => $this->indicator->code,
                'title' => $this->indicator->title,
            ] : null,
            'workplan_indicator_id' => $this->workplan_indicator_id,
            'project' => $this->project ? [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ] : null,
            'project_id' => $this->project_id,
            'acknowledged_by' => $this->acknowledger?->name,
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
