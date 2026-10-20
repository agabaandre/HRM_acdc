<?php

namespace App\Models;

use App\Enums\DataClassification;
use App\Enums\DeploymentReadiness;
use App\Enums\OperationalStatus;
use App\Enums\PriorityCategory;
use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Enums\RagStatus;
use App\Enums\WorkClass;
use App\Enums\WorkItemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Project extends Model
{
    use HasFactory;

    public const NOT_ARCHIVED_SCOPE = 'not_archived';

    protected $fillable = [
        'code',
        'name',
        'description',
        'work_class',
        'workplan_indicator_id',
        'work_request_id',
        'stage',
        'rag_status',
        'rag_reason',
        'priority_category',
        'status',
        'operational_status',
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
        'modules',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'work_class' => WorkClass::class,
            'stage' => ProjectStage::class,
            'rag_status' => RagStatus::class,
            'priority_category' => PriorityCategory::class,
            'status' => ProjectStatus::class,
            'operational_status' => OperationalStatus::class,
            'deployment_readiness' => DeploymentReadiness::class,
            'data_classification' => DataClassification::class,
            'start_date' => 'date',
            'target_end_date' => 'date',
            'actual_end_date' => 'date',
            'archived_at' => 'datetime',
            'uat_progress_percent' => 'integer',
            'modules' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(self::NOT_ARCHIVED_SCOPE, function (Builder $builder) {
            if (! Schema::hasColumn((new static)->getTable(), 'archived_at')) {
                return;
            }
            $builder->whereNull($builder->getModel()->getTable().'.archived_at');
        });
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return static::withoutGlobalScope(self::NOT_ARCHIVED_SCOPE)
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeWithArchived(Builder $query): Builder
    {
        return $query->withoutGlobalScope(self::NOT_ARCHIVED_SCOPE);
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeOnlyArchived(Builder $query): Builder
    {
        return $query->withoutGlobalScope(self::NOT_ARCHIVED_SCOPE)
            ->whereNotNull($query->getModel()->getTable().'.archived_at');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(WorkplanIndicator::class, 'workplan_indicator_id');
    }

    public function workRequest(): BelongsTo
    {
        return $this->belongsTo(WorkRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(ProjectRoleAssignment::class);
    }

    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class);
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }

    /** Tasks that live on this project's modules (enhancement tasks stay with the enhancement). */
    public function coreWorkItems(): HasMany
    {
        return $this->hasMany(WorkItem::class)
            ->whereNull('enhancement_id')
            ->whereNotNull('project_module_id');
    }

    public function projectModules(): HasMany
    {
        return $this->hasMany(ProjectModule::class);
    }

    public function enhancements(): HasMany
    {
        return $this->hasMany(Enhancement::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    public function raidItems(): HasMany
    {
        return $this->hasMany(RaidItem::class);
    }

    public function statusReports(): HasMany
    {
        return $this->hasMany(StatusReport::class);
    }

    public function stageEvents(): HasMany
    {
        return $this->hasMany(ProjectStageEvent::class)->latest();
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummaryArray(): array
    {
        // Progress rolls up tasks on the project's modules.
        $openTasks = $this->work_items_open_count
            ?? $this->coreWorkItems()->where('status', '!=', WorkItemStatus::Completed->value)->count();
        $doneTasks = $this->work_items_done_count
            ?? $this->coreWorkItems()->where('status', WorkItemStatus::Completed->value)->count();
        $totalTasks = $openTasks + $doneTasks;
        $progress = $totalTasks > 0 ? (int) round(($doneTasks / max(1, $openTasks + $doneTasks)) * 100) : 0;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'work_class' => $this->work_class?->value,
            'work_class_label' => $this->work_class?->label(),
            'work_class_code' => $this->work_class?->code(),
            'stage' => $this->stage?->value,
            'stage_label' => $this->stage?->shortLabel(),
            'stage_full_label' => $this->stage?->label(),
            'next_gate' => $this->stage?->next()?->label(),
            'next_gate_approver' => $this->stage?->next()?->approver() ?? $this->stage?->approver(),
            'rag_status' => $this->rag_status?->value,
            'rag_label' => $this->rag_status?->label(),
            'rag_reason' => $this->rag_reason,
            'priority_category' => $this->priority_category?->value,
            'priority_label' => $this->priority_category?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_short_label' => $this->status?->shortLabel(),
            'operational_status' => $this->operational_status?->value,
            'operational_status_label' => $this->operational_status?->label(),
            'start_date' => $this->start_date?->toDateString(),
            'target_end_date' => $this->target_end_date?->toDateString(),
            'actual_end_date' => $this->actual_end_date?->toDateString(),
            'original_completion_date' => $this->actual_end_date?->toDateString(),
            'sprint_goal' => $this->sprint_goal,
            'budget_status' => $this->budget_status,
            'resource_status' => $this->resource_status,
            'uat_progress_percent' => $this->uat_progress_percent,
            'deployment_readiness' => $this->deployment_readiness?->value,
            'deployment_readiness_label' => $this->deployment_readiness?->label(),
            'data_classification' => $this->data_classification?->value,
            'indicator' => $this->indicator ? [
                'id' => $this->indicator->id,
                'code' => $this->indicator->code,
                'title' => $this->indicator->title,
                'directorate' => $this->indicator->directorate,
                'programme' => $this->indicator->programme,
            ] : null,
            'work_request_id' => $this->work_request_id,
            'work_request' => $this->workRequest ? [
                'id' => $this->workRequest->id,
                'reference' => $this->workRequest->reference,
                'title' => $this->workRequest->title,
            ] : null,
            'open_tasks' => $openTasks,
            'done_tasks' => $doneTasks,
            'progress_percent' => $progress,
            'open_raid' => $this->raid_items_open_count
                ?? $this->raidItems()->whereIn('status', ['open', 'mitigating'])->count(),
            'is_archived' => $this->isArchived(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'archive_reason' => $this->archive_reason,
            'archived_by' => $this->archived_by,
            'archived_by_user' => $this->relationLoaded('archivedBy') && $this->archivedBy
                ? [
                    'id' => $this->archivedBy->id,
                    'name' => $this->archivedBy->name,
                    'email' => $this->archivedBy->email,
                ]
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
