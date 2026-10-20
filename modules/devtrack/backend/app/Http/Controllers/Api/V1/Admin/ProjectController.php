<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\EnhancementStatus;
use App\Enums\NamedProjectRole;
use App\Enums\ProjectStage;
use App\Enums\RaidStatus;
use App\Enums\ReleaseStatus;
use App\Enums\WorkItemStatus;
use App\Enums\WorkRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ArchiveProjectRequest;
use App\Http\Requests\Api\V1\Admin\StoreProjectRequest;
use App\Models\Project;
use App\Models\ProjectModule;
use App\Models\WorkRequest;
use App\Services\ProjectModuleService;
use App\Support\DevTrackReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $openTaskStatuses = collect(WorkItemStatus::cases())
            ->filter(fn (WorkItemStatus $status) => $status->isOpen())
            ->map(fn (WorkItemStatus $status) => $status->value)
            ->all();

        $items = Project::query()
            ->with(['indicator', 'workRequest:id,reference,title'])
            ->withCount([
                'coreWorkItems as work_items_open_count' => fn ($q) => $q->whereIn('status', $openTaskStatuses),
                'coreWorkItems as work_items_done_count' => fn ($q) => $q->where('status', WorkItemStatus::Completed->value),
                'raidItems as raid_items_open_count' => fn ($q) => $q->whereIn('status', [
                    RaidStatus::Open->value,
                    RaidStatus::Mitigating->value,
                ]),
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('rag_status'), fn ($q) => $q->where('rag_status', $request->query('rag_status')))
            ->when($request->filled('stage'), fn ($q) => $q->where('stage', $request->query('stage')))
            ->when($request->filled('workplan_indicator_id'), fn ($q) => $q->where('workplan_indicator_id', $request->query('workplan_indicator_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->query('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)->orWhere('code', 'like', $term);
                });
            })
            ->orderByRaw("CASE rag_status WHEN 'red' THEN 0 WHEN 'amber' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get()
            ->map->toSummaryArray()
            ->values();

        return response()->json(['data' => $items]);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $data = $request->validated();
        $roles = $data['roles'] ?? [];
        unset($data['roles']);
        $data['code'] = DevTrackReference::next('PRJ', 'projects', 'code');
        $data['created_by'] = $request->user()?->id;

        $modules = $data['modules'] ?? [];
        unset($data['modules']);

        $project = Project::query()->create($data);
        $this->syncRoles($project, $roles);
        $this->syncWorkRequest($project, $data['work_request_id'] ?? null);
        $this->syncModuleNames($project, $modules, $request->user()?->id);

        $project->stageEvents()->create([
            'from_stage' => null,
            'to_stage' => $project->stage ?? ProjectStage::Intake,
            'approved_by' => $request->user()?->id,
            'notes' => 'Project registered',
        ]);

        return response()->json(['data' => $this->detail($project->fresh())], 201);
    }

    public function show(Project $project): JsonResponse
    {
        return response()->json(['data' => $this->detail($project)]);
    }

    public function archivedIndex(Request $request): JsonResponse
    {
        $openTaskStatuses = collect(WorkItemStatus::cases())
            ->filter(fn (WorkItemStatus $status) => $status->isOpen())
            ->map(fn (WorkItemStatus $status) => $status->value)
            ->all();

        $items = Project::query()
            ->onlyArchived()
            ->with([
                'indicator',
                'workRequest:id,reference,title',
                'archivedBy:id,name,email',
            ])
            ->withCount([
                'coreWorkItems as work_items_open_count' => fn ($q) => $q->whereIn('status', $openTaskStatuses),
                'coreWorkItems as work_items_done_count' => fn ($q) => $q->where('status', WorkItemStatus::Completed->value),
                'raidItems as raid_items_open_count' => fn ($q) => $q->whereIn('status', [
                    RaidStatus::Open->value,
                    RaidStatus::Mitigating->value,
                ]),
            ])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->query('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('archive_reason', 'like', $term);
                });
            })
            ->orderByDesc('archived_at')
            ->orderBy('name')
            ->get()
            ->map->toSummaryArray()
            ->values();

        return response()->json(['data' => $items]);
    }

    public function archive(ArchiveProjectRequest $request, Project $project): JsonResponse
    {
        if ($project->isArchived()) {
            throw ValidationException::withMessages([
                'project' => ['This project is already archived.'],
            ]);
        }

        $project->forceFill([
            'archived_at' => now(),
            'archived_by' => $request->user()?->id,
            'archive_reason' => $request->validated('archive_reason'),
        ])->save();

        return response()->json(['data' => $this->detail($project->fresh())]);
    }

    public function unarchive(Request $request, Project $project): JsonResponse
    {
        abort_unless($request->user()?->is_admin === true, 403);

        if (! $project->isArchived()) {
            throw ValidationException::withMessages([
                'project' => ['This project is not archived.'],
            ]);
        }

        $project->forceFill([
            'archived_at' => null,
            'archived_by' => null,
            'archive_reason' => null,
        ])->save();

        return response()->json(['data' => $this->detail($project->fresh())]);
    }

    public function update(StoreProjectRequest $request, Project $project): JsonResponse
    {
        $this->ensureNotArchived($project);

        $data = $request->validated();
        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        $modules = array_key_exists('modules', $data) ? ($data['modules'] ?? []) : null;
        unset($data['modules']);

        $fromStage = $project->stage;
        $project->update($data);
        $this->syncWorkRequest($project, array_key_exists('work_request_id', $data) ? ($data['work_request_id'] ?? null) : $project->work_request_id);

        if (isset($data['stage']) && $fromStage?->value !== $project->stage?->value) {
            $project->stageEvents()->create([
                'from_stage' => $fromStage,
                'to_stage' => $project->stage,
                'approved_by' => $request->user()?->id,
                'notes' => 'Stage updated',
            ]);
        }

        if (is_array($roles)) {
            $this->syncRoles($project, $roles);
        }

        if (is_array($modules)) {
            $this->syncModuleNames($project, $modules, $request->user()?->id);
        }

        return response()->json(['data' => $this->detail($project->fresh())]);
    }

    public function advanceStage(Request $request, Project $project): JsonResponse
    {
        $this->ensureNotArchived($project);

        $request->validate([
            'stage' => ['required', Rule::in(ProjectStage::values())],
            'notes' => ['nullable', 'string'],
        ]);

        $from = $project->stage;
        $project->stage = ProjectStage::from($request->string('stage')->toString());
        $project->save();

        $project->stageEvents()->create([
            'from_stage' => $from,
            'to_stage' => $project->stage,
            'approved_by' => $request->user()?->id,
            'notes' => $request->input('notes'),
        ]);

        return response()->json(['data' => $this->detail($project->fresh())]);
    }

    /**
     * @param  list<array{role: string, staff_id?: int|null, user_id?: int|null}>  $roles
     */
    private function syncRoles(Project $project, array $roles): void
    {
        $project->roleAssignments()->delete();

        foreach ($roles as $role) {
            if (empty($role['role'])) {
                continue;
            }

            $staffId = isset($role['staff_id']) && is_numeric($role['staff_id'])
                ? (int) $role['staff_id']
                : null;
            $userId = isset($role['user_id']) && is_numeric($role['user_id'])
                ? (int) $role['user_id']
                : null;

            if ($staffId === null && $userId === null) {
                continue;
            }

            // Prefer staff; optionally mirror a linked local user when present.
            if ($staffId !== null && $userId === null) {
                $staff = \App\Models\Staff::query()->find($staffId);
                if ($staff?->staff_id) {
                    $userId = \App\Models\User::query()
                        ->where('staff_id', $staff->staff_id)
                        ->value('id');
                }
            }

            $project->roleAssignments()->create([
                'role' => NamedProjectRole::from($role['role']),
                'staff_id' => $staffId,
                'user_id' => $userId,
            ]);
        }
    }

    /**
     * @param  list<string>  $names
     */
    private function syncModuleNames(Project $project, array $names, ?int $userId): void
    {
        $normalized = collect($names)
            ->map(fn ($name) => is_string($name) ? trim($name) : '')
            ->filter(fn (string $name) => $name !== '')
            ->unique(fn (string $name) => mb_strtolower($name))
            ->values();

        $project->update(['modules' => $normalized->all()]);

        $existing = $project->projectModules()->get()->keyBy(fn (ProjectModule $module) => mb_strtolower($module->name));

        foreach ($normalized as $name) {
            $key = mb_strtolower($name);
            if ($existing->has($key)) {
                $existing->forget($key);
                continue;
            }

            $project->projectModules()->create([
                'code' => DevTrackReference::next('MOD', 'project_modules', 'code'),
                'name' => $name,
                'created_by' => $userId,
            ]);
        }

        // Keep orphaned structured modules that already have work/enhancements.
        foreach ($existing as $orphan) {
            if ($orphan->workItems()->exists() || $orphan->enhancements()->exists()) {
                continue;
            }
            $orphan->delete();
        }
    }

    private function syncWorkRequest(Project $project, ?int $workRequestId): void
    {
        WorkRequest::query()
            ->where('project_id', $project->id)
            ->when($workRequestId, fn ($q) => $q->where('id', '!=', $workRequestId))
            ->get()
            ->each(function (WorkRequest $request): void {
                $request->project_id = null;
                if ($request->status === WorkRequestStatus::Converted) {
                    $request->status = WorkRequestStatus::Acknowledged;
                }
                $request->save();
            });

        if ($workRequestId === null) {
            return;
        }

        $request = WorkRequest::query()->find($workRequestId);
        if ($request === null) {
            return;
        }

        $request->project_id = $project->id;
        if ($request->status !== WorkRequestStatus::Converted) {
            $request->status = WorkRequestStatus::Converted;
        }
        $request->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Project $project): array
    {
        app(ProjectModuleService::class)->moveProjectTasksToPrimary($project);

        $openTaskStatuses = collect(WorkItemStatus::cases())
            ->filter(fn (WorkItemStatus $status) => $status->isOpen())
            ->map(fn (WorkItemStatus $status) => $status->value)
            ->all();

        $project->load([
            'indicator',
            'workRequest:id,reference,title',
            'archivedBy:id,name,email',
            'roleAssignments.user:id,name,email',
            'roleAssignments.staff',
            'stageEvents.approver:id,name',
            'projectModules.businessOwner',
            'projectModules.responsibleDeveloper',
            'enhancements.module',
            'enhancements.businessOwner',
            'enhancements.responsibleDeveloper',
            'releases.enhancements',
        ]);
        $project->loadCount([
            'coreWorkItems as work_items_open_count' => fn ($q) => $q->whereIn('status', $openTaskStatuses),
            'coreWorkItems as work_items_done_count' => fn ($q) => $q->where('status', WorkItemStatus::Completed->value),
            'raidItems as raid_items_open_count' => fn ($q) => $q->whereIn('status', [
                RaidStatus::Open->value,
                RaidStatus::Mitigating->value,
            ]),
            'enhancements as active_enhancements_count' => fn ($q) => $q->whereIn('status', EnhancementStatus::activeValues()),
        ]);

        $named = [];
        foreach (NamedProjectRole::cases() as $role) {
            $assignment = $project->roleAssignments->first(fn ($row) => $row->role === $role);
            $named[$role->value] = $assignment?->toApiArray() ?? [
                'role' => $role->value,
                'role_label' => $role->label(),
                'user' => null,
            ];
        }

        $moduleRows = $project->projectModules
            ->sortBy(fn (ProjectModule $module) => sprintf('%d-%010d', $module->is_primary ? 0 : 1, $module->id))
            ->values()
            ->map->toApiArray();

        $nextRelease = $project->releases
            ->filter(fn ($release) => in_array($release->status, [ReleaseStatus::Planned, ReleaseStatus::Proposed], true))
            ->sortBy(fn ($release) => [$release->status === ReleaseStatus::Planned ? 0 : 1, $release->target_date?->timestamp ?? PHP_INT_MAX, $release->version])
            ->first();

        return [
            ...$project->toSummaryArray(),
            'scope' => $project->scope,
            'success_measures' => $project->success_measures,
            'modules' => $moduleRows->pluck('name')->values()->all(),
            'project_modules' => $moduleRows->all(),
            'active_enhancements' => (int) ($project->active_enhancements_count ?? 0),
            'next_planned_release' => $nextRelease?->toApiArray(),
            'enhancements' => $project->enhancements
                ->sortByDesc('id')
                ->values()
                ->map->toApiArray()
                ->all(),
            'releases' => $project->releases
                ->sortBy('version')
                ->values()
                ->map->toApiArray()
                ->all(),
            'roles' => $named,
            'role_list' => $project->roleAssignments->map->toApiArray()->values(),
            'stage_history' => $project->stageEvents->map->toApiArray()->values(),
            'sprints' => $project->sprints()->with('project:id,code,name')->latest('start_date')->get()->map->toApiArray()->values(),
            'raid_items' => $project->raidItems()
                ->with(['owner:id,name', 'project:id,code,name'])
                ->latest()
                ->get()
                ->map->toApiArray()
                ->values(),
            'reports' => $project->statusReports()
                ->with(['submitter:id,name', 'project:id,code,name'])
                ->latest('period_end')
                ->get()
                ->map->toApiArray()
                ->values(),
        ];
    }

    private function ensureNotArchived(Project $project): void
    {
        if ($project->isArchived()) {
            throw ValidationException::withMessages([
                'project' => ['This project is archived. Restore it before making changes.'],
            ]);
        }
    }
}
