<?php

namespace App\Models;

use App\Enums\EnhancementStatus;
use App\Enums\EnhancementWorkType;
use App\Enums\WorkItemPriority;
use App\Enums\WorkItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enhancement extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'project_id',
        'project_module_id',
        'name',
        'work_type',
        'description',
        'scope',
        'requesting_division',
        'business_owner_staff_id',
        'responsible_developer_staff_id',
        'priority',
        'planned_start',
        'due_date',
        'status',
        'acceptance_criteria',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'work_type' => EnhancementWorkType::class,
            'status' => EnhancementStatus::class,
            'priority' => WorkItemPriority::class,
            'planned_start' => 'date',
            'due_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ProjectModule::class, 'project_module_id');
    }

    public function businessOwner(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'business_owner_staff_id');
    }

    public function responsibleDeveloper(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_developer_staff_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }

    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class)->withTimestamps();
    }

    /**
     * @return array{done: int, total: int, percent: int}
     */
    public function taskProgress(): array
    {
        $done = $this->workItems()->where('status', WorkItemStatus::Completed->value)->count();
        $open = $this->workItems()
            ->where('status', '!=', WorkItemStatus::Completed->value)
            ->count();
        $total = $done + $open;

        return [
            'done' => $done,
            'total' => $total,
            'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(bool $detail = false): array
    {
        $progress = $this->taskProgress();

        $base = [
            'id' => $this->id,
            'reference' => $this->reference,
            'project_id' => $this->project_id,
            'project_module_id' => $this->project_module_id,
            'name' => $this->name,
            'work_type' => $this->work_type?->value,
            'work_type_label' => $this->work_type?->label(),
            'description' => $this->description,
            'scope' => $this->scope,
            'requesting_division' => $this->requesting_division,
            'business_owner_staff_id' => $this->business_owner_staff_id,
            'business_owner' => $this->businessOwner ? [
                'id' => $this->businessOwner->id,
                'name' => $this->businessOwner->name,
                'email' => $this->businessOwner->work_email,
            ] : null,
            'responsible_developer_staff_id' => $this->responsible_developer_staff_id,
            'responsible_developer' => $this->responsibleDeveloper ? [
                'id' => $this->responsibleDeveloper->id,
                'name' => $this->responsibleDeveloper->name,
                'email' => $this->responsibleDeveloper->work_email,
            ] : null,
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),
            'planned_start' => $this->planned_start?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'acceptance_criteria' => $this->acceptance_criteria,
            'done_tasks' => $progress['done'],
            'total_tasks' => $progress['total'],
            'progress_percent' => $progress['percent'],
            'module' => $this->module ? [
                'id' => $this->module->id,
                'code' => $this->module->code,
                'name' => $this->module->name,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if (! $detail) {
            return $base;
        }

        $this->loadMissing([
            'project:id,code,name',
            'module:id,code,name',
            'businessOwner',
            'responsibleDeveloper',
            'workItems.assignee:id,name,email',
            'releases',
        ]);

        return [
            ...$base,
            'project' => $this->project ? [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ] : null,
            'work_items' => $this->workItems
                ->sortBy('id')
                ->values()
                ->map->toApiArray()
                ->all(),
            'releases' => $this->releases
                ->sortBy('version')
                ->values()
                ->map->toApiArray()
                ->all(),
            'release_ids' => $this->releases->pluck('id')->values()->all(),
        ];
    }
}
