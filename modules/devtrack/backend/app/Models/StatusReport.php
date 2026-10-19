<?php

namespace App\Models;

use App\Enums\RagStatus;
use App\Enums\ReportPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'period',
        'period_start',
        'period_end',
        'title',
        'rag_status',
        'summary',
        'accomplishments',
        'next_period_plan',
        'blockers',
        'decisions_required',
        'submitted_by',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'period' => ReportPeriod::class,
            'rag_status' => RagStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project' => $this->project ? [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ] : null,
            'period' => $this->period?->value,
            'period_label' => $this->period?->label(),
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'title' => $this->title,
            'rag_status' => $this->rag_status?->value,
            'rag_label' => $this->rag_status?->label(),
            'summary' => $this->summary,
            'accomplishments' => $this->accomplishments,
            'next_period_plan' => $this->next_period_plan,
            'blockers' => $this->blockers,
            'decisions_required' => $this->decisions_required,
            'submitted_by' => $this->submitter ? [
                'id' => $this->submitter->id,
                'name' => $this->submitter->name,
            ] : null,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
