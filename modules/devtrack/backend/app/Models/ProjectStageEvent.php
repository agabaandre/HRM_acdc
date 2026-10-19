<?php

namespace App\Models;

use App\Enums\ProjectStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStageEvent extends Model
{
    protected $fillable = [
        'project_id',
        'from_stage',
        'to_stage',
        'approved_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'from_stage' => ProjectStage::class,
            'to_stage' => ProjectStage::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'from_stage' => $this->from_stage?->value,
            'from_stage_label' => $this->from_stage?->label(),
            'to_stage' => $this->to_stage?->value,
            'to_stage_label' => $this->to_stage?->label(),
            'approved_by' => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ] : null,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
