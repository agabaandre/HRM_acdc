<?php

namespace App\Models;

use App\Enums\DataClassification;
use App\Enums\DeploymentReadiness;
use App\Enums\ModuleStatus;
use App\Enums\PriorityCategory;
use App\Enums\ProjectStage;
use App\Enums\RagStatus;
use App\Enums\WorkClass;
use App\Enums\WorkItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'is_primary',
        'code',
        'name',
        'description',
        'work_class',
        'stage',
        'rag_status',
        'rag_reason',
        'priority_category',
        'status',
        'start_date',
        'target_end_date',
        'actual_end_date',
        'sprint_goal',
        'budget_status',
        'resource_status',
        'uat_progress_percent',
        'deployment_readiness',
        'data_classification',
        'scope',
        'success_measures',
        'requesting_division',
        'business_owner_staff_id',
        'responsible_developer_staff_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'work_class' => WorkClass::class,
            'stage' => ProjectStage::class,
            'rag_status' => RagStatus::class,
            'priority_category' => PriorityCategory::class,
            'status' => ModuleStatus::class,
            'deployment_readiness' => DeploymentReadiness::class,
            'data_classification' => DataClassification::class,
            'start_date' => 'date',
            'target_end_date' => 'date',
            'actual_end_date' => 'date',
            'uat_progress_percent' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function businessOwner(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'business_owner_staff_id');
    }

    public function responsibleDeveloper(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_developer_staff_id');
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class, 'project_module_id');
    }

    public function enhancements(): HasMany
    {
        return $this->hasMany(Enhancement::class);
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
            'project_id' => $this->project_id,
            'is_primary' => (bool) $this->is_primary,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'work_class' => $this->work_class?->value,
            'work_class_label' => $this->work_class?->label(),
            'stage' => $this->stage?->value,
            'stage_label' => $this->stage?->shortLabel(),
            'stage_full_label' => $this->stage?->label(),
            'rag_status' => $this->rag_status?->value,
            'rag_label' => $this->rag_status?->label(),
            'rag_reason' => $this->rag_reason,
            'priority_category' => $this->priority_category?->value,
            'priority_label' => $this->priority_category?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'start_date' => $this->start_date?->toDateString(),
            'target_end_date' => $this->target_end_date?->toDateString(),
            'actual_end_date' => $this->actual_end_date?->toDateString(),
            'sprint_goal' => $this->sprint_goal,
            'budget_status' => $this->budget_status,
            'resource_status' => $this->resource_status,
            'uat_progress_percent' => $this->uat_progress_percent,
            'deployment_readiness' => $this->deployment_readiness?->value,
            'deployment_readiness_label' => $this->deployment_readiness?->label(),
            'data_classification' => $this->data_classification?->value,
            'scope' => $this->scope,
            'success_measures' => $this->success_measures,
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
            'done_tasks' => $progress['done'],
            'total_tasks' => $progress['total'],
            'progress_percent' => $progress['percent'],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if (! $detail) {
            return $base;
        }

        $this->loadMissing([
            'project:id,code,name',
            'businessOwner',
            'responsibleDeveloper',
            'workItems.assignee:id,name,email',
            'enhancements.businessOwner',
            'enhancements.responsibleDeveloper',
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
            'enhancements' => $this->enhancements
                ->sortByDesc('id')
                ->values()
                ->map(fn (Enhancement $enhancement) => $enhancement->toApiArray())
                ->all(),
        ];
    }
}
