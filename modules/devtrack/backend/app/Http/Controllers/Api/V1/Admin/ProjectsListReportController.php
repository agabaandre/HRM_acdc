<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\NamedProjectRole;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectRoleAssignment;
use App\Models\Staff;
use App\Support\SimpleCsvWriter;
use App\Support\SimpleExcelWriter;
use App\Support\SimplePdfWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class ProjectsListReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);
        $projects = $this->mapProjects($this->filteredQuery($filters)->get());

        return response()->json([
            'data' => [
                'key' => 'projects_list',
                'name' => 'All Projects',
                'description' => 'Complete list of registered projects.',
                'generated_at' => now()->toIso8601String(),
                'filters' => [
                    'project_id' => $filters['project_id'],
                    'project_manager_staff_id' => $filters['project_manager_staff_id'],
                    'technical_lead_staff_id' => $filters['technical_lead_staff_id'],
                    'start_date' => $filters['start_date'],
                    'end_date' => $filters['end_date'],
                ],
                'summary' => [
                    'total_projects' => $projects->count(),
                ],
                'filter_options' => [
                    'projects' => Project::query()
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
                    'project_managers' => $this->roleFilterOptions(NamedProjectRole::ProjectManager),
                    'technical_leads' => $this->roleFilterOptions(NamedProjectRole::TechnicalLead),
                ],
                'projects' => $projects->values()->all(),
            ],
        ]);
    }

    public function export(Request $request): Response
    {
        $filters = $this->validatedFilters($request);
        $format = $request->validate([
            'format' => ['required', Rule::in(['csv', 'xls', 'xlsx', 'pdf'])],
        ])['format'];

        if ($format === 'xlsx') {
            $format = 'xls';
        }

        $projects = $this->mapProjects($this->filteredQuery($filters)->get());
        $headers = [
            'Code',
            'Project',
            'Project manager',
            'Technical lead',
            'Status',
            'Stage',
            'RAG',
            'Work class',
            'Modules',
            'Start date',
            'End date',
            'Indicator',
        ];
        $rows = $projects->map(fn (array $project) => [
            $project['code'],
            $project['name'],
            $project['project_manager']['name'] ?? '',
            $project['technical_lead']['name'] ?? '',
            $project['status_label'] ?? '',
            $project['stage_label'] ?? '',
            $project['rag_label'] ?? $project['rag_status'] ?? '',
            $project['work_class_label'] ?? '',
            $project['modules_count'],
            $project['start_date'] ?? '',
            $project['target_end_date'] ?? '',
            $project['indicator']
                ? ($project['indicator']['code'].' · '.$project['indicator']['title'])
                : '',
        ])->values()->all();

        $stamp = now()->format('Ymd-His');
        $filterNote = $this->filterSubtitle($filters);

        return match ($format) {
            'csv' => response(SimpleCsvWriter::toCsv($headers, $rows), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="projects-report-'.$stamp.'.csv"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]),
            'pdf' => response(
                SimplePdfWriter::toPdf('All Projects', $headers, $rows, $filterNote),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="projects-report-'.$stamp.'.pdf"',
                    'Cache-Control' => 'no-store, no-cache, must-revalidate',
                ],
            ),
            default => response(SimpleExcelWriter::toXls($headers, $rows, 'Projects'), 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="projects-report-'.$stamp.'.xls"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]),
        };
    }

    /**
     * @return array{
     *   project_id: int|null,
     *   project_manager_staff_id: int|null,
     *   technical_lead_staff_id: int|null,
     *   start_date: string|null,
     *   end_date: string|null
     * }
     */
    private function validatedFilters(Request $request): array
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'project_manager_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'technical_lead_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        return [
            'project_id' => isset($data['project_id']) ? (int) $data['project_id'] : null,
            'project_manager_staff_id' => isset($data['project_manager_staff_id'])
                ? (int) $data['project_manager_staff_id']
                : null,
            'technical_lead_staff_id' => isset($data['technical_lead_staff_id'])
                ? (int) $data['technical_lead_staff_id']
                : null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
        ];
    }

    /**
     * @param  array{
     *   project_id: int|null,
     *   project_manager_staff_id: int|null,
     *   technical_lead_staff_id: int|null,
     *   start_date: string|null,
     *   end_date: string|null
     * }  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        return Project::query()
            ->withCount('projectModules')
            ->with([
                'indicator:id,code,title',
                'roleAssignments' => fn ($q) => $q
                    ->whereIn('role', [
                        NamedProjectRole::ProjectManager->value,
                        NamedProjectRole::TechnicalLead->value,
                    ])
                    ->with(['staff', 'user:id,name,email']),
            ])
            ->when($filters['project_id'], fn (Builder $q, int $projectId) => $q->where('id', $projectId))
            ->when(
                $filters['project_manager_staff_id'],
                fn (Builder $q, int $staffId) => $q->whereHas(
                    'roleAssignments',
                    fn (Builder $roleQuery) => $roleQuery
                        ->where('role', NamedProjectRole::ProjectManager->value)
                        ->where('staff_id', $staffId),
                ),
            )
            ->when(
                $filters['technical_lead_staff_id'],
                fn (Builder $q, int $staffId) => $q->whereHas(
                    'roleAssignments',
                    fn (Builder $roleQuery) => $roleQuery
                        ->where('role', NamedProjectRole::TechnicalLead->value)
                        ->where('staff_id', $staffId),
                ),
            )
            ->when($filters['start_date'], fn (Builder $q, string $date) => $q->whereDate('start_date', '>=', $date))
            ->when($filters['end_date'], fn (Builder $q, string $date) => $q->whereDate('target_end_date', '<=', $date))
            ->orderBy('code');
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return Collection<int, array<string, mixed>>
     */
    private function mapProjects(Collection $projects): Collection
    {
        return $projects->map(function (Project $project) {
            $projectManager = $this->roleAssignee(
                $project->roleAssignments,
                NamedProjectRole::ProjectManager,
            );
            $technicalLead = $this->roleAssignee(
                $project->roleAssignments,
                NamedProjectRole::TechnicalLead,
            );

            return [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->name,
                'status' => $project->status?->value,
                'status_label' => $project->status?->label(),
                'stage' => $project->stage?->value,
                'stage_label' => $project->stage?->shortLabel(),
                'rag_status' => $project->rag_status?->value,
                'rag_label' => $project->rag_status?->label(),
                'work_class' => $project->work_class?->value,
                'work_class_label' => $project->work_class?->label(),
                'start_date' => $project->start_date?->toDateString(),
                'target_end_date' => $project->target_end_date?->toDateString(),
                'modules_count' => (int) $project->project_modules_count,
                'project_manager' => $projectManager,
                'technical_lead' => $technicalLead,
                'indicator' => $project->indicator ? [
                    'id' => $project->indicator->id,
                    'code' => $project->indicator->code,
                    'title' => $project->indicator->title,
                ] : null,
            ];
        });
    }

    /**
     * @param  Collection<int, ProjectRoleAssignment>  $assignments
     * @return array{staff_id: int|null, user_id: int|null, name: string, email: string|null}|null
     */
    private function roleAssignee(Collection $assignments, NamedProjectRole $role): ?array
    {
        /** @var ProjectRoleAssignment|null $row */
        $row = $assignments->first(function (ProjectRoleAssignment $assignment) use ($role) {
            $value = $assignment->role instanceof NamedProjectRole
                ? $assignment->role->value
                : (string) $assignment->role;

            return $value === $role->value && ($assignment->staff_id || $assignment->user_id);
        });

        if (! $row) {
            return null;
        }

        if ($row->staff) {
            return [
                'staff_id' => $row->staff->id,
                'user_id' => $row->user_id,
                'name' => $row->staff->fullName(),
                'email' => $row->staff->work_email,
            ];
        }

        if ($row->user) {
            return [
                'staff_id' => null,
                'user_id' => $row->user->id,
                'name' => $row->user->name,
                'email' => $row->user->email,
            ];
        }

        return null;
    }

    /**
     * @return list<array{id: int, name: string, email: string|null, label: string}>
     */
    private function roleFilterOptions(NamedProjectRole $role): array
    {
        $staffIds = ProjectRoleAssignment::query()
            ->where('role', $role->value)
            ->whereNotNull('staff_id')
            ->distinct()
            ->pluck('staff_id')
            ->filter()
            ->values();

        if ($staffIds->isEmpty()) {
            return [];
        }

        return Staff::query()
            ->whereIn('id', $staffIds)
            ->get()
            ->map(fn (Staff $staff) => [
                'id' => $staff->id,
                'name' => $staff->fullName(),
                'email' => $staff->work_email,
                'label' => $staff->fullName().($staff->work_email ? ' · '.$staff->work_email : ''),
            ])
            ->sortBy(fn (array $row) => mb_strtolower($row['name']), SORT_NATURAL)
            ->values()
            ->all();
    }

    /**
     * @param  array{
     *   project_id: int|null,
     *   project_manager_staff_id: int|null,
     *   technical_lead_staff_id: int|null,
     *   start_date: string|null,
     *   end_date: string|null
     * }  $filters
     */
    private function filterSubtitle(array $filters): string
    {
        $parts = [];
        if ($filters['project_id']) {
            $project = Project::query()->find($filters['project_id']);
            if ($project) {
                $parts[] = 'Project: '.$project->code;
            }
        }
        if ($filters['project_manager_staff_id']) {
            $staff = Staff::query()->find($filters['project_manager_staff_id']);
            if ($staff) {
                $parts[] = 'PM: '.$staff->fullName();
            }
        }
        if ($filters['technical_lead_staff_id']) {
            $staff = Staff::query()->find($filters['technical_lead_staff_id']);
            if ($staff) {
                $parts[] = 'Tech lead: '.$staff->fullName();
            }
        }
        if ($filters['start_date']) {
            $parts[] = 'Start from: '.$filters['start_date'];
        }
        if ($filters['end_date']) {
            $parts[] = 'End by: '.$filters['end_date'];
        }

        return $parts !== [] ? implode(' · ', $parts) : 'All projects';
    }
}
