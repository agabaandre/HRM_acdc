<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\WorkItemStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TasksListReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(WorkItemStatus::values())],
            'project_id' => ['nullable', 'integer'],
            'assignee_id' => ['nullable', 'integer'],
        ]);

        $tasks = WorkItem::query()
            ->with([
                'project:id,code,name',
                'projectModule:id,code,name,project_id',
                'enhancement:id,reference,name,project_id,project_module_id',
                'assignee:id,name,email',
            ])
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->when(filled($filters['project_id'] ?? null), fn ($query) => $query->where('project_id', $filters['project_id']))
            ->when(filled($filters['assignee_id'] ?? null), fn ($query) => $query->where('assignee_id', $filters['assignee_id']))
            ->orderBy('key')
            ->get()
            ->map(function (WorkItem $item) {
                $module = $item->projectModule;
                $enhancement = $item->enhancement;

                $attachedTo = 'Project';
                if ($module) {
                    $attachedTo = 'Module';
                } elseif ($enhancement) {
                    $attachedTo = 'Enhancement';
                }

                return [
                    'id' => $item->id,
                    'key' => $item->key,
                    'title' => $item->title,
                    'type' => $item->type?->value,
                    'type_label' => $item->type?->label(),
                    'status' => $item->status?->value,
                    'status_label' => $item->status?->label(),
                    'priority' => $item->priority?->value,
                    'priority_label' => $item->priority?->label(),
                    'due_date' => $item->due_date?->toDateString(),
                    'assignee' => $item->assignee ? [
                        'id' => $item->assignee->id,
                        'name' => $item->assignee->name,
                        'email' => $item->assignee->email,
                    ] : null,
                    'project' => $item->project ? [
                        'id' => $item->project->id,
                        'code' => $item->project->code,
                        'name' => $item->project->name,
                    ] : null,
                    'module' => $module ? [
                        'id' => $module->id,
                        'code' => $module->code,
                        'name' => $module->name,
                    ] : null,
                    'enhancement' => $enhancement ? [
                        'id' => $enhancement->id,
                        'reference' => $enhancement->reference,
                        'name' => $enhancement->name,
                    ] : null,
                    'attached_to' => $attachedTo,
                    'attachment_label' => match ($attachedTo) {
                        'Module' => ($module?->code ? $module->code.' · ' : '').($module?->name ?? 'Module'),
                        'Enhancement' => ($enhancement?->reference ? $enhancement->reference.' · ' : '').($enhancement?->name ?? 'Enhancement'),
                        default => $item->project
                            ? $item->project->code.' · '.$item->project->name
                            : 'Unattached',
                    },
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'key' => 'tasks_list',
                'name' => 'All Tasks',
                'description' => 'Complete list of tasks with project or module attachment.',
                'generated_at' => now()->toIso8601String(),
                'filters' => [
                    'status' => $filters['status'] ?? null,
                    'project_id' => isset($filters['project_id']) ? (int) $filters['project_id'] : null,
                    'assignee_id' => isset($filters['assignee_id']) ? (int) $filters['assignee_id'] : null,
                ],
                'summary' => [
                    'total_tasks' => count($tasks),
                ],
                'filter_options' => $this->filterOptions(),
                'tasks' => $tasks,
            ],
        ]);
    }

    /**
     * @return array{statuses: list<array{value: string, label: string}>, projects: list<array{id: int, code: string, name: string, label: string}>, assignees: list<array{id: int, name: string, label: string}>}
     */
    private function filterOptions(): array
    {
        $projectIds = WorkItem::query()->whereNotNull('project_id')->distinct()->pluck('project_id');
        $assigneeIds = WorkItem::query()->whereNotNull('assignee_id')->distinct()->pluck('assignee_id');

        return [
            'statuses' => array_map(
                fn (WorkItemStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                WorkItemStatus::cases(),
            ),
            'projects' => Project::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $projectIds)
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'code' => $project->code,
                    'name' => $project->name,
                    'label' => $project->code.' · '.$project->name,
                ])
                ->values()
                ->all(),
            'assignees' => User::query()
                ->whereIn('id', $assigneeIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'label' => $user->name,
                ])
                ->values()
                ->all(),
        ];
    }
}
