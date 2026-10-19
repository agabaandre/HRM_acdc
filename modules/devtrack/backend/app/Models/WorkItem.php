<?php

namespace App\Models;

use App\Enums\WorkItemPriority;
use App\Enums\WorkItemStatus;
use App\Enums\WorkItemType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkItem extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'not_started',
    ];

    protected $fillable = [
        'key',
        'project_id',
        'enhancement_id',
        'project_module_id',
        'sprint_id',
        'parent_id',
        'work_request_id',
        'title',
        'description',
        'acceptance_criteria',
        'type',
        'status',
        'priority',
        'assignee_id',
        'reporter_id',
        'estimate_hours',
        'story_points',
        'due_date',
        'blocked_reason',
        'completed_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkItemType::class,
            'status' => WorkItemStatus::class,
            'priority' => WorkItemPriority::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'estimate_hours' => 'integer',
            'story_points' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            if ($item->status === WorkItemStatus::Completed && $item->completed_at === null) {
                $item->completed_at = now();
            }

            if ($item->status !== WorkItemStatus::Completed) {
                $item->completed_at = null;
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function enhancement(): BelongsTo
    {
        return $this->belongsTo(Enhancement::class);
    }

    public function projectModule(): BelongsTo
    {
        return $this->belongsTo(ProjectModule::class, 'project_module_id');
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'project_id' => $this->project_id,
            'enhancement_id' => $this->enhancement_id,
            'project_module_id' => $this->project_module_id,
            'project' => $this->project ? [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ] : null,
            'sprint_id' => $this->sprint_id,
            'sprint' => $this->sprint ? [
                'id' => $this->sprint->id,
                'name' => $this->sprint->name,
            ] : null,
            'parent_id' => $this->parent_id,
            'title' => $this->title,
            'description' => $this->description,
            'acceptance_criteria' => $this->acceptance_criteria,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),
            'assignee' => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
            ] : null,
            'assignee_id' => $this->assignee_id,
            'reporter' => $this->reporter ? [
                'id' => $this->reporter->id,
                'name' => $this->reporter->name,
            ] : null,
            'estimate_hours' => $this->estimate_hours,
            'story_points' => $this->story_points,
            'due_date' => $this->due_date?->toDateString(),
            'blocked_reason' => $this->blocked_reason,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_overdue' => $this->due_date !== null
                && $this->status?->isOpen()
                && $this->due_date->endOfDay()->isPast(),
            'children_count' => $this->children_count ?? $this->children()->count(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
