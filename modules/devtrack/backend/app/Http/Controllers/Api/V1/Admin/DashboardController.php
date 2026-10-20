<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ModuleStatus;
use App\Enums\NamedProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\RaidStatus;
use App\Enums\RagStatus;
use App\Enums\WorkItemStatus;
use App\Enums\WorkRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectModule;
use App\Models\ProjectRoleAssignment;
use App\Models\RaidItem;
use App\Models\StatusReport;
use App\Models\WorkItem;
use App\Models\WorkplanIndicator;
use App\Models\WorkRequest;
use App\Support\DevTrackCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $activeStatuses = ProjectStatus::openValues();
        $openTaskStatuses = collect(WorkItemStatus::cases())
            ->filter(fn (WorkItemStatus $status) => $status->isOpen())
            ->map(fn (WorkItemStatus $status) => $status->value)
            ->all();

        $projects = Project::query()->with('indicator')->get();
        $activeProjects = $projects->filter(
            fn (Project $project) => $project->status instanceof ProjectStatus && $project->status->isOpen(),
        );

        $stageCounts = $activeProjects
            ->groupBy(fn (Project $project) => $project->stage?->value)
            ->map(fn ($group, $stage) => [
                'stage' => $stage,
                'label' => $group->first()?->stage?->shortLabel() ?? $stage,
                'count' => $group->count(),
            ])
            ->values();

        $classCounts = $activeProjects
            ->groupBy(fn (Project $project) => $project->work_class?->value)
            ->map(fn ($group, $class) => [
                'work_class' => $class,
                'label' => $group->first()?->work_class?->label() ?? $class,
                'count' => $group->count(),
            ])
            ->values();

        $indicators = WorkplanIndicator::query()
            ->withCount(['projects'])
            ->orderBy('code')
            ->get()
            ->map(function (WorkplanIndicator $indicator) use ($openTaskStatuses) {
                $projectIds = $indicator->projects()->pluck('id');

                return [
                    ...$indicator->toApiArray(),
                    'open_tasks' => WorkItem::query()
                        ->whereIn('project_id', $projectIds)
                        ->whereIn('status', $openTaskStatuses)
                        ->count(),
                    'done_tasks' => WorkItem::query()
                        ->whereIn('project_id', $projectIds)
                        ->where('status', WorkItemStatus::Completed->value)
                        ->count(),
                ];
            });

        $portfolio = Project::query()
            ->with(['indicator', 'workRequest:id,reference,title'])
            ->withCount([
                'coreWorkItems as work_items_open_count' => fn ($q) => $q->whereIn('status', $openTaskStatuses),
                'coreWorkItems as work_items_done_count' => fn ($q) => $q->where('status', WorkItemStatus::Completed->value),
                'raidItems as raid_items_open_count' => fn ($q) => $q->whereIn('status', [
                    RaidStatus::Open->value,
                    RaidStatus::Mitigating->value,
                ]),
            ])
            ->whereIn('status', $activeStatuses)
            ->orderByRaw("CASE rag_status WHEN 'red' THEN 0 WHEN 'amber' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get()
            ->map->toSummaryArray()
            ->values();

        $attentionTasks = WorkItem::query()
            ->with(['project:id,code,name', 'assignee:id,name'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->where('status', '!=', WorkItemStatus::Completed->value)
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map->toApiArray()
            ->values();

        $myWork = WorkItem::query()
            ->with(['project:id,code,name', 'assignee:id,name'])
            ->where('assignee_id', $user->id)
            ->whereIn('status', $openTaskStatuses)
            ->orderByRaw("CASE priority WHEN 'must_do' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderBy('due_date')
            ->limit(8)
            ->get()
            ->map->toApiArray()
            ->values();

        return response()->json([
            'stats' => [
                'active_projects' => $activeProjects->where('status', ProjectStatus::InProgress)->count(),
                'red_projects' => $activeProjects->where('rag_status', RagStatus::Red)->count(),
                'amber_projects' => $activeProjects->where('rag_status', RagStatus::Amber)->count(),
                'open_tasks' => WorkItem::query()->whereIn('status', $openTaskStatuses)->count(),
                'overdue_tasks' => WorkItem::query()
                    ->whereIn('status', $openTaskStatuses)
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', now()->toDateString())
                    ->count(),
                'open_requests' => WorkRequest::query()
                    ->whereNotIn('status', [
                        WorkRequestStatus::Converted->value,
                        WorkRequestStatus::Declined->value,
                    ])
                    ->count(),
                'open_raid' => RaidItem::query()
                    ->whereIn('status', [RaidStatus::Open->value, RaidStatus::Mitigating->value])
                    ->count(),
                'reports_this_month' => StatusReport::query()
                    ->whereMonth('period_start', now()->month)
                    ->whereYear('period_start', now()->year)
                    ->count(),
                'total_projects' => $projects->count(),
                'completed_projects' => $projects->where('status', ProjectStatus::Completed)->count(),
                'in_progress_projects' => $projects->where('status', ProjectStatus::InProgress)->count(),
                'not_started_projects' => $projects->where('status', ProjectStatus::NotStarted)->count(),
                'total_modules' => ProjectModule::query()->count(),
                'completed_modules' => ProjectModule::query()->where('status', ModuleStatus::Completed)->count(),
                'in_progress_modules' => ProjectModule::query()->where('status', ModuleStatus::InProgress)->count(),
                'not_started_modules' => ProjectModule::query()->where('status', ModuleStatus::NotStarted)->count(),
            ],
            'rag' => [
                'green' => $activeProjects->where('rag_status', RagStatus::Green)->count(),
                'amber' => $activeProjects->where('rag_status', RagStatus::Amber)->count(),
                'red' => $activeProjects->where('rag_status', RagStatus::Red)->count(),
            ],
            'stages' => $stageCounts,
            'work_classes' => $classCounts,
            'indicators' => $indicators,
            'task_activity' => $this->taskActivity(),
            'project_managers' => $this->projectManagersSummary($openTaskStatuses),
            'portfolio' => $portfolio,
            'my_work' => $myWork,
            'attention' => $attentionTasks,
            'recent_reports' => StatusReport::query()
                ->with(['project:id,code,name', 'submitter:id,name'])
                ->latest('period_end')
                ->limit(5)
                ->get()
                ->map->toApiArray()
                ->values(),
            'options' => DevTrackCatalog::options(),
        ]);
    }

    /**
     * @param  list<string>  $openTaskStatuses
     * @return list<array<string, mixed>>
     */
    private function projectManagersSummary(array $openTaskStatuses): array
    {
        $assignments = ProjectRoleAssignment::query()
            ->with([
                'staff',
                'user:id,name,email',
                'project:id,code,name,status',
            ])
            ->where('role', NamedProjectRole::ProjectManager->value)
            ->whereHas('project')
            ->get();

        /** @var Collection<string, Collection<int, ProjectRoleAssignment>> $grouped */
        $grouped = $assignments->groupBy(function (ProjectRoleAssignment $row) {
            if ($row->staff_id) {
                return 'staff:'.$row->staff_id;
            }
            if ($row->user_id) {
                return 'user:'.$row->user_id;
            }

            return 'unassigned:'.$row->id;
        });

        return $grouped
            ->map(function (Collection $rows) use ($openTaskStatuses) {
                /** @var ProjectRoleAssignment $first */
                $first = $rows->first();
                $name = $first->staff?->fullName()
                    ?? $first->user?->name
                    ?? 'Unassigned';
                $email = $first->staff?->work_email
                    ?? $first->user?->email;

                $projects = $rows
                    ->map(fn (ProjectRoleAssignment $row) => $row->project)
                    ->filter()
                    ->unique('id')
                    ->values();

                $projectIds = $projects->pluck('id')->all();

                $statusCounts = [
                    ProjectStatus::NotStarted->value => 0,
                    ProjectStatus::InProgress->value => 0,
                    ProjectStatus::UnderReview->value => 0,
                    ProjectStatus::Completed->value => 0,
                ];
                foreach ($projects as $project) {
                    $key = $project->status instanceof ProjectStatus
                        ? $project->status->value
                        : (string) $project->status;
                    if (array_key_exists($key, $statusCounts)) {
                        $statusCounts[$key]++;
                    }
                }

                $completedByProject = $projectIds === []
                    ? collect()
                    : WorkItem::query()
                        ->selectRaw('project_id, COUNT(*) as aggregate')
                        ->whereIn('project_id', $projectIds)
                        ->whereNull('enhancement_id')
                        ->whereNotNull('project_module_id')
                        ->where('status', WorkItemStatus::Completed->value)
                        ->groupBy('project_id')
                        ->pluck('aggregate', 'project_id');

                $pendingByProject = $projectIds === []
                    ? collect()
                    : WorkItem::query()
                        ->selectRaw('project_id, COUNT(*) as aggregate')
                        ->whereIn('project_id', $projectIds)
                        ->whereNull('enhancement_id')
                        ->whereNotNull('project_module_id')
                        ->whereIn('status', $openTaskStatuses)
                        ->groupBy('project_id')
                        ->pluck('aggregate', 'project_id');

                $completedTasks = (int) $completedByProject->sum();
                $pendingTasks = (int) $pendingByProject->sum();

                $statusParts = [];
                foreach ([
                    ProjectStatus::NotStarted->value => 'Not Started',
                    ProjectStatus::InProgress->value => 'In Progress',
                    ProjectStatus::UnderReview->value => 'Under Review',
                    ProjectStatus::Completed->value => 'Completed',
                ] as $value => $label) {
                    if (($statusCounts[$value] ?? 0) > 0) {
                        $statusParts[] = $statusCounts[$value].' '.$label;
                    }
                }

                $projectDetails = $projects->map(function (Project $project) use ($completedByProject, $pendingByProject) {
                    $statusValue = $project->status instanceof ProjectStatus
                        ? $project->status->value
                        : (string) $project->status;

                    return [
                        'id' => $project->id,
                        'code' => $project->code,
                        'name' => $project->name,
                        'status' => $statusValue,
                        'status_label' => $project->status instanceof ProjectStatus
                            ? $project->status->label()
                            : (string) $project->status,
                        'completed_tasks' => (int) ($completedByProject[$project->id] ?? 0),
                        'pending_tasks' => (int) ($pendingByProject[$project->id] ?? 0),
                    ];
                })->values()->all();

                return [
                    'key' => $first->staff_id
                        ? 'staff:'.$first->staff_id
                        : ($first->user_id ? 'user:'.$first->user_id : 'unknown'),
                    'name' => $name,
                    'email' => $email,
                    'staff_id' => $first->staff_id,
                    'user_id' => $first->user_id,
                    'projects_count' => $projects->count(),
                    'status_counts' => [
                        'not_started' => $statusCounts[ProjectStatus::NotStarted->value],
                        'in_progress' => $statusCounts[ProjectStatus::InProgress->value],
                        'under_review' => $statusCounts[ProjectStatus::UnderReview->value],
                        'completed' => $statusCounts[ProjectStatus::Completed->value],
                    ],
                    'status_summary' => $statusParts !== [] ? implode(' · ', $statusParts) : '—',
                    'completed_tasks' => $completedTasks,
                    'pending_tasks' => $pendingTasks,
                    'projects' => $projectDetails,
                ];
            })
            ->sortByDesc('projects_count')
            ->values()
            ->all();
    }

    /**
     * @return list<array{date: string, label: string, created: int, completed: int}>
     */
    private function taskActivity(): array
    {
        $activity = [];

        for ($weeksAgo = 7; $weeksAgo >= 0; $weeksAgo--) {
            $start = now()->startOfWeek()->subWeeks($weeksAgo);
            $end = (clone $start)->endOfWeek();

            $activity[] = [
                'date' => $start->toDateString(),
                'label' => $weeksAgo === 0 ? 'This week' : $start->format('d M'),
                'created' => WorkItem::query()
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
                'completed' => WorkItem::query()
                    ->where('status', WorkItemStatus::Completed->value)
                    ->whereBetween('completed_at', [$start, $end])
                    ->count(),
            ];
        }

        return $activity;
    }
}
