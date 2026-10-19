<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\NamedProjectRole;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectRoleAssignment;
use App\Models\WorkItem;
use Illuminate\Http\JsonResponse;

class DataQualityReportController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $requiredRoles = NamedProjectRole::cases();

        $projects = Project::query()
            ->with([
                'roleAssignments:id,project_id,role,staff_id,user_id',
                'projectModules:id,project_id',
            ])
            ->orderBy('code')
            ->get();

        $missingRoles = [];
        $missingModules = [];
        $missingProjectDates = [];

        foreach ($projects as $project) {
            $filledRoles = $project->roleAssignments
                ->filter(fn (ProjectRoleAssignment $row) => $row->staff_id || $row->user_id)
                ->map(fn (ProjectRoleAssignment $row) => $row->role instanceof NamedProjectRole
                    ? $row->role->value
                    : (string) $row->role)
                ->unique()
                ->values()
                ->all();

            $absentRoles = [];
            foreach ($requiredRoles as $role) {
                if (! in_array($role->value, $filledRoles, true)) {
                    $absentRoles[] = [
                        'value' => $role->value,
                        'label' => $role->label(),
                    ];
                }
            }

            $moduleCount = $project->projectModules->count();

            if ($absentRoles !== []) {
                $missingRoles[] = [
                    'id' => $project->id,
                    'code' => $project->code,
                    'name' => $project->name,
                    'status' => $project->status?->value,
                    'status_label' => $project->status?->label(),
                    'filled_roles_count' => count($filledRoles),
                    'required_roles_count' => count($requiredRoles),
                    'missing_roles' => $absentRoles,
                    'missing_roles_label' => implode(', ', array_column($absentRoles, 'label')),
                    'modules_count' => $moduleCount,
                ];
            }

            if ($moduleCount < 1) {
                $missingModules[] = [
                    'id' => $project->id,
                    'code' => $project->code,
                    'name' => $project->name,
                    'status' => $project->status?->value,
                    'status_label' => $project->status?->label(),
                    'modules_count' => $moduleCount,
                ];
            }

            $missingStart = $project->start_date === null;
            $missingEnd = $project->target_end_date === null;
            if ($missingStart || $missingEnd) {
                $missingLabels = [];
                if ($missingStart) {
                    $missingLabels[] = 'Start date';
                }
                if ($missingEnd) {
                    $missingLabels[] = 'End date';
                }

                $missingProjectDates[] = [
                    'id' => $project->id,
                    'code' => $project->code,
                    'name' => $project->name,
                    'status' => $project->status?->value,
                    'status_label' => $project->status?->label(),
                    'start_date' => $project->start_date?->toDateString(),
                    'target_end_date' => $project->target_end_date?->toDateString(),
                    'missing_start_date' => $missingStart,
                    'missing_end_date' => $missingEnd,
                    'missing_dates_label' => implode(', ', $missingLabels),
                ];
            }
        }

        $missingTaskDates = WorkItem::query()
            ->with([
                'project:id,code,name',
                'projectModule:id,code,name',
                'enhancement:id,reference,name',
                'assignee:id,name',
            ])
            ->whereNull('due_date')
            ->orderBy('key')
            ->get()
            ->map(function (WorkItem $item) {
                return [
                    'id' => $item->id,
                    'key' => $item->key,
                    'title' => $item->title,
                    'status' => $item->status?->value,
                    'status_label' => $item->status?->label(),
                    'priority' => $item->priority?->value,
                    'priority_label' => $item->priority?->label(),
                    'assignee' => $item->assignee ? [
                        'id' => $item->assignee->id,
                        'name' => $item->assignee->name,
                    ] : null,
                    'project' => $item->project ? [
                        'id' => $item->project->id,
                        'code' => $item->project->code,
                        'name' => $item->project->name,
                    ] : null,
                    'module' => $item->projectModule ? [
                        'id' => $item->projectModule->id,
                        'code' => $item->projectModule->code,
                        'name' => $item->projectModule->name,
                    ] : null,
                    'enhancement' => $item->enhancement ? [
                        'id' => $item->enhancement->id,
                        'reference' => $item->enhancement->reference,
                        'name' => $item->enhancement->name,
                    ] : null,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'key' => 'data_quality',
                'name' => 'Data Quality',
                'description' => 'Projects and tasks with incomplete required data.',
                'generated_at' => now()->toIso8601String(),
                'summary' => [
                    'total_projects' => $projects->count(),
                    'missing_roles_count' => count($missingRoles),
                    'missing_modules_count' => count($missingModules),
                    'missing_project_dates_count' => count($missingProjectDates),
                    'missing_task_dates_count' => count($missingTaskDates),
                ],
                'required_roles' => array_map(
                    fn (NamedProjectRole $role) => [
                        'value' => $role->value,
                        'label' => $role->label(),
                    ],
                    $requiredRoles,
                ),
                'missing_roles' => $missingRoles,
                'missing_modules' => $missingModules,
                'missing_project_dates' => $missingProjectDates,
                'missing_task_dates' => $missingTaskDates,
            ],
        ]);
    }
}
