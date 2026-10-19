<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkItem;
use Illuminate\Http\JsonResponse;

class TasksListReportController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $tasks = WorkItem::query()
            ->with([
                'project:id,code,name',
                'projectModule:id,code,name,project_id',
                'enhancement:id,reference,name,project_id,project_module_id',
                'assignee:id,name,email',
            ])
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
                'summary' => [
                    'total_tasks' => count($tasks),
                ],
                'tasks' => $tasks,
            ],
        ]);
    }
}
