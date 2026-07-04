<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Activity;
use App\Models\Division;
use App\Models\Matrix;
use App\Models\Staff;
use App\Support\MemoFundTypeFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait ActivityListIndexResponses
{
    public function getActivitiesIndexAjax(Request $request): JsonResponse
    {
        try {
            $tab = (string) $request->get('tab', 'my-division');
            $page = max(1, (int) $request->get('page', 1));
            $pageSize = min(100, max(1, (int) $request->get('pageSize', 20)));

            $paginator = $this->paginateActivitiesIndexTab($request, $tab, $page, $pageSize);
            $startIndex = ($paginator->currentPage() - 1) * $paginator->perPage();
            $userStaffId = user_session('staff_id');

            $items = $paginator->getCollection()
                ->values()
                ->map(fn (Activity $activity, int $index) => $this->serializeActivityIndexRow(
                    $activity,
                    $startIndex + $index + 1,
                    (int) $userStaffId
                ));

            $counts = $this->activitiesIndexTabCounts($request);

            return response()->json([
                'data' => $items,
                'pagination' => [
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem() ?? 0,
                    'to' => $paginator->lastItem() ?? 0,
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                ],
                'counts' => $counts,
            ]);
        } catch (\Throwable $e) {
            Log::error('Activities index AJAX error: '.$e->getMessage());

            return response()->json(['error' => 'An error occurred while loading activities.'], 500);
        }
    }

    public function getSingleMemosIndexAjax(Request $request): JsonResponse
    {
        try {
            $tab = (string) $request->get('tab', 'mySubmitted');
            $page = max(1, (int) $request->get('page', 1));
            $pageSize = min(100, max(1, (int) $request->get('pageSize', 10)));

            $paginator = $this->paginateSingleMemosIndexTab($request, $tab, $page, $pageSize);
            $startIndex = ($paginator->currentPage() - 1) * $paginator->perPage();
            $userStaffId = user_session('staff_id');

            $items = $paginator->getCollection()
                ->values()
                ->map(fn (Activity $memo, int $index) => $this->serializeSingleMemoIndexRow(
                    $memo,
                    $startIndex + $index + 1,
                    (int) $userStaffId
                ));

            $counts = $this->singleMemosIndexTabCounts($request);

            return response()->json([
                'data' => $items,
                'pagination' => [
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem() ?? 0,
                    'to' => $paginator->lastItem() ?? 0,
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                ],
                'counts' => $counts,
            ]);
        } catch (\Throwable $e) {
            Log::error('Single memos index AJAX error: '.$e->getMessage());

            return response()->json(['error' => 'An error occurred while loading single memos.'], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildActivitiesIndexPageConfig(Request $request): array
    {
        $filters = $this->resolveActivitiesIndexFilters($request);
        $currentYear = (int) now()->year;
        $minYear = max(2025, $currentYear - 10);
        $canViewAll = in_array(87, user_session('permissions', []));
        $counts = $this->activitiesIndexTabCounts($request);

        $divisions = Division::orderBy('division_name')->get();
        $staff = Staff::whereNotIn('status', ['Expired', 'Separated'])
            ->orderBy('fname')->orderBy('lname')->get();

        return [
            'currentYear' => $currentYear,
            'currentQuarter' => 'Q'.now()->quarter,
            'defaults' => [
                'tab' => $canViewAll ? 'all-activities' : 'my-division',
                'year' => (string) $filters['selectedYear'],
                'quarter' => (string) $filters['selectedQuarter'],
                'division_id' => (string) $filters['selectedDivisionId'],
                'document_number' => (string) $filters['selectedDocumentNumber'],
                'staff_id' => (string) $filters['selectedStaffId'],
                'status' => (string) $filters['selectedStatus'],
                'search' => (string) $filters['searchTerm'],
                'fund_type_id' => (string) $filters['selectedFundTypeId'],
            ],
            'counts' => $counts,
            'canViewAllActivities' => $canViewAll,
            'perPage' => 20,
            'yearOptions' => $this->buildYearSelectOptions(range($currentYear, $minYear)),
            'quarterOptions' => $this->buildQuarterSelectOptions(),
            'divisionOptions' => $this->buildDivisionSelectOptions($divisions),
            'staffOptions' => $this->buildStaffSelectOptions($staff),
            'statusOptions' => $this->buildMemoStatusSelectOptions(),
            'fundTypeOptions' => $this->buildFundTypeSelectOptions(),
            'routes' => [
                'ajax' => route('activities.index.ajax'),
            ],
            'csrf' => csrf_token(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSingleMemosIndexPageConfig(Request $request): array
    {
        $period = $this->resolveSingleMemosIndexPeriod($request);
        $canViewAll = in_array(87, user_session('permissions', []));
        $counts = $this->singleMemosIndexTabCounts($request);
        $userDivisionId = user_session('division_id');
        $currentStaffId = user_session('staff_id');
        $apmCurrentYear = current_apm_year();
        $apmCurrentQuarter = current_apm_quarter();
        $currentQuarterMatrix = null;
        if ($userDivisionId) {
            $currentQuarterMatrix = Matrix::query()
                ->where('division_id', $userDivisionId)
                ->where('year', $apmCurrentYear)
                ->where('quarter', $apmCurrentQuarter)
                ->first();
        }

        $staff = Staff::active()->get();
        $divisions = Staff::select('division_id', 'division_name')
            ->whereNotNull('division_id')
            ->distinct()
            ->orderBy('division_name')
            ->get();
        $currentYear = (int) now()->year;
        $minYear = max(2025, $currentYear - 10);

        return [
            'currentYear' => $currentYear,
            'currentQuarter' => 'Q'.now()->quarter,
            'apmCurrentYear' => $apmCurrentYear,
            'apmCurrentQuarter' => $apmCurrentQuarter,
            'currentQuarterLabel' => $apmCurrentQuarter.' '.$apmCurrentYear,
            'showCreateInstructions' => (bool) ($userDivisionId && $currentStaffId),
            'currentQuarterMatrix' => $currentQuarterMatrix ? [
                'id' => $currentQuarterMatrix->id,
                'overall_status' => $currentQuarterMatrix->overall_status,
                'show_url' => route('matrices.show', $currentQuarterMatrix->id),
            ] : null,
            'defaults' => [
                'tab' => 'mySubmitted',
                'year' => (string) $period['selectedYear'],
                'quarter' => (string) $period['selectedQuarter'],
                'division_id' => (string) $request->get('division_id', ''),
                'staff_id' => (string) $request->get('staff_id', ''),
                'status' => (string) $request->get('status', ''),
                'document_number' => (string) $request->get('document_number', ''),
                'search' => (string) $request->get('search', ''),
                'fund_type_id' => (string) MemoFundTypeFilter::selectedId($request),
            ],
            'counts' => $counts,
            'canViewAllMemos' => $canViewAll,
            'perPage' => 10,
            'yearOptions' => $this->buildYearSelectOptions(range($currentYear, $minYear)),
            'quarterOptions' => $this->buildQuarterSelectOptions(),
            'divisionOptions' => $this->buildDivisionSelectOptions($divisions),
            'staffOptions' => $this->buildStaffSelectOptions($staff),
            'statusOptions' => $this->buildMemoStatusSelectOptions(),
            'fundTypeOptions' => $this->buildFundTypeSelectOptions(),
            'routes' => [
                'ajax' => route('activities.single-memos.ajax'),
                'matricesIndex' => route('matrices.index', [
                    'year' => $apmCurrentYear,
                    'quarter' => $apmCurrentQuarter,
                ]),
            ],
            'csrf' => csrf_token(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveActivitiesIndexFilters(Request $request): array
    {
        $currentYear = now()->year;
        $currentQuarter = now()->quarter;
        $selectedYear = $request->get('year', $currentYear);
        $selectedQuarter = $request->get('quarter', 'Q'.$currentQuarter);
        if (! str_starts_with((string) $selectedQuarter, 'Q')) {
            $selectedQuarter = 'Q'.$selectedQuarter;
        }

        return [
            'selectedYear' => $selectedYear,
            'selectedQuarter' => $selectedQuarter,
            'selectedDivisionId' => (string) $request->get('division_id', ''),
            'selectedDocumentNumber' => (string) $request->get('document_number', ''),
            'selectedStaffId' => (string) $request->get('staff_id', ''),
            'selectedStatus' => (string) $request->get('status', ''),
            'searchTerm' => (string) $request->get('search', ''),
            'selectedFundTypeId' => MemoFundTypeFilter::selectedId($request),
            'userStaffId' => user_session('staff_id'),
            'userDivisionId' => user_session('division_id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveSingleMemosIndexPeriod(Request $request): array
    {
        $explicitPeriod = $request->filled('year') || $request->filled('quarter');
        $selectedYear = (int) $request->get('year', current_apm_year());
        $selectedQuarter = (string) $request->get('quarter', current_apm_quarter());
        if (! str_starts_with($selectedQuarter, 'Q')) {
            $selectedQuarter = 'Q'.$selectedQuarter;
        }

        if (! $explicitPeriod) {
            $hasForPeriod = Activity::query()
                ->where('is_single_memo', true)
                ->whereHas('matrix', function ($query) use ($selectedYear, $selectedQuarter) {
                    $query->where('year', $selectedYear)->where('quarter', $selectedQuarter);
                })
                ->exists();

            if (! $hasForPeriod) {
                $latestMatrix = Matrix::query()
                    ->whereIn('id', Activity::query()
                        ->where('is_single_memo', true)
                        ->whereNotNull('matrix_id')
                        ->select('matrix_id'))
                    ->orderByDesc('year')
                    ->orderByRaw("FIELD(quarter, 'Q4', 'Q3', 'Q2', 'Q1')")
                    ->first(['year', 'quarter']);

                if ($latestMatrix) {
                    $selectedYear = (int) $latestMatrix->year;
                    $selectedQuarter = (string) $latestMatrix->quarter;
                }
            }
        }

        return compact('selectedYear', 'selectedQuarter');
    }

    protected function newActivitiesIndexBaseQuery(array $filters): Builder
    {
        $query = Activity::with([
            'matrix.division',
            'responsiblePerson',
            'staff',
            'fundType',
        ])->whereHas('matrix', function ($matrixQuery) use ($filters) {
            $matrixQuery->where('year', $filters['selectedYear'])
                ->where('quarter', $filters['selectedQuarter']);
        });

        if ($filters['selectedDocumentNumber'] !== '') {
            $query->where('activities.document_number', 'like', '%'.$filters['selectedDocumentNumber'].'%');
        }
        if ($filters['selectedStaffId'] !== '') {
            $query->where('activities.responsible_person_id', $filters['selectedStaffId']);
        }
        if ($filters['searchTerm'] !== '') {
            $query->where('activities.activity_title', 'like', '%'.$filters['searchTerm'].'%');
        }
        if ($filters['selectedStatus'] !== '') {
            $query->where('activities.overall_status', $filters['selectedStatus']);
        }
        if ($filters['selectedFundTypeId'] !== '' && array_key_exists($filters['selectedFundTypeId'], MemoFundTypeFilter::options())) {
            $query->where('activities.fund_type_id', (int) $filters['selectedFundTypeId']);
        }

        return $query;
    }

    protected function applyActivitiesIndexOrdering(Builder $query): Builder
    {
        return $query->join('matrices', 'activities.matrix_id', '=', 'matrices.id')
            ->orderBy('matrices.year', 'desc')
            ->orderByRaw("CASE 
                WHEN matrices.quarter = 'Q4' THEN 4
                WHEN matrices.quarter = 'Q3' THEN 3
                WHEN matrices.quarter = 'Q2' THEN 2
                WHEN matrices.quarter = 'Q1' THEN 1
                ELSE 0
            END DESC")
            ->orderBy('activities.created_at', 'desc')
            ->select('activities.*');
    }

    protected function paginateActivitiesIndexTab(Request $request, string $tab, int $page, int $pageSize): LengthAwarePaginator
    {
        $filters = $this->resolveActivitiesIndexFilters($request);
        $query = $this->newActivitiesIndexBaseQuery($filters);

        if ($tab === 'all-activities') {
            if (! in_array(87, user_session('permissions', []))) {
                return new LengthAwarePaginator([], 0, $pageSize, $page);
            }
            if ($filters['selectedDivisionId'] !== '') {
                $query->whereHas('matrix', function ($matrixQuery) use ($filters) {
                    $matrixQuery->where('division_id', $filters['selectedDivisionId']);
                });
            }
        } elseif ($tab === 'shared-activities') {
            $userStaffId = $filters['userStaffId'];
            $userDivisionId = $filters['userDivisionId'];
            if (! $userStaffId) {
                return new LengthAwarePaginator([], 0, $pageSize, $page);
            }
            $query->where(function ($staffQuery) use ($userStaffId) {
                $staffQuery->where('activities.staff_id', $userStaffId)
                    ->orWhere('activities.responsible_person_id', $userStaffId)
                    ->orWhereHas('participantSchedules', function ($scheduleQuery) use ($userStaffId) {
                        $scheduleQuery->where('participant_id', $userStaffId);
                    });
            })->whereHas('matrix', function ($matrixQuery) use ($userDivisionId) {
                $matrixQuery->where('division_id', '!=', $userDivisionId);
            })->where('activities.overall_status', '!=', 'archived');
        } else {
            $userDivisionId = $filters['userDivisionId'];
            $userStaffId = $filters['userStaffId'];
            if (! $userDivisionId) {
                return new LengthAwarePaginator([], 0, $pageSize, $page);
            }
            $query->where(function ($divisionQuery) use ($userDivisionId, $userStaffId) {
                $divisionQuery->whereHas('matrix', function ($matrixQuery) use ($userDivisionId) {
                    $matrixQuery->where('division_id', $userDivisionId);
                })->orWhere('activities.responsible_person_id', $userStaffId);
            })->where('activities.overall_status', '!=', 'archived');
        }

        return $this->applyActivitiesIndexOrdering($query)->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * @return array{all_activities:int,my_division:int,shared_activities:int}
     */
    protected function activitiesIndexTabCounts(Request $request): array
    {
        return [
            'all_activities' => $this->paginateActivitiesIndexTab($request, 'all-activities', 1, 1)->total(),
            'my_division' => $this->paginateActivitiesIndexTab($request, 'my-division', 1, 1)->total(),
            'shared_activities' => $this->paginateActivitiesIndexTab($request, 'shared-activities', 1, 1)->total(),
        ];
    }

    protected function newSingleMemosIndexBaseQuery(Request $request, array $period): Builder
    {
        $query = Activity::with([
            'staff',
            'responsiblePerson',
            'matrix.division',
            'division',
            'fundType',
        ])->where('is_single_memo', true);

        if ($request->filled('staff_id')) {
            $query->where(function ($staffQuery) use ($request) {
                $staffQuery->where('staff_id', $request->staff_id)
                    ->orWhere('responsible_person_id', $request->staff_id);
            });
        }
        if ($request->filled('division_id')) {
            $query->where('division_id', $request->division_id);
        }
        if ($request->filled('status')) {
            $query->where('overall_status', $request->status);
        }
        if ($request->filled('document_number')) {
            $query->where('document_number', 'like', '%'.$request->document_number.'%');
        }
        if ($request->filled('search')) {
            $query->where('activity_title', 'like', '%'.$request->search.'%');
        }

        MemoFundTypeFilter::apply($query, $request, 'activities.fund_type_id');

        $query->whereHas('matrix', function ($matrixQuery) use ($period) {
            $matrixQuery->where('year', $period['selectedYear'])
                ->where('quarter', $period['selectedQuarter']);
        });

        return $query;
    }

    protected function paginateSingleMemosIndexTab(Request $request, string $tab, int $page, int $pageSize): LengthAwarePaginator
    {
        $period = $this->resolveSingleMemosIndexPeriod($request);
        $query = $this->newSingleMemosIndexBaseQuery($request, $period);
        $userDivisionId = user_session('division_id');
        $currentStaffId = user_session('staff_id');

        if ($tab === 'allMemos') {
            if (! in_array(87, user_session('permissions', []))) {
                return new LengthAwarePaginator([], 0, $pageSize, $page);
            }
        } elseif ($tab === 'sharedMemos') {
            if ($currentStaffId) {
                $query->where(function ($staffQuery) use ($currentStaffId) {
                    $staffQuery->where('activities.staff_id', $currentStaffId)
                        ->orWhere('activities.responsible_person_id', $currentStaffId)
                        ->orWhereHas('participantSchedules', function ($scheduleQuery) use ($currentStaffId) {
                            $scheduleQuery->where('participant_id', $currentStaffId);
                        });
                })->whereHas('matrix', function ($matrixQuery) use ($userDivisionId) {
                    $matrixQuery->where('division_id', '!=', $userDivisionId);
                });
            }
            $query->where('activities.overall_status', '!=', 'archived');
        } else {
            if ($userDivisionId) {
                $query->where('activities.division_id', $userDivisionId);
            }
            $query->where('activities.overall_status', '!=', 'archived');
        }

        return $query
            ->orderBy('activities.created_at', 'desc')
            ->orderBy('activities.id', 'desc')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * @return array{my_submitted:int,all_memos:int,shared_memos:int}
     */
    protected function singleMemosIndexTabCounts(Request $request): array
    {
        return [
            'my_submitted' => $this->paginateSingleMemosIndexTab($request, 'mySubmitted', 1, 1)->total(),
            'all_memos' => $this->paginateSingleMemosIndexTab($request, 'allMemos', 1, 1)->total(),
            'shared_memos' => $this->paginateSingleMemosIndexTab($request, 'sharedMemos', 1, 1)->total(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeActivityIndexRow(Activity $activity, int $rowNum, int $userStaffId): array
    {
        $matrix = $activity->matrix;
        $status = $activity->overall_status ?? 'draft';
        $workflowRole = null;
        $actorName = null;

        if ($status === 'pending') {
            if ($activity->is_single_memo) {
                $statusMeta = $activity->memoIndexStatusMeta();
                $workflowRole = $statusMeta['role'] ?? null;
                $actorName = $activity->current_actor
                    ? trim(($activity->current_actor->fname ?? '').' '.($activity->current_actor->lname ?? ''))
                    : ($statusMeta['actor_name'] ?? null);
            } elseif ($matrix) {
                $workflowRole = $matrix->workflow_definition->role ?? null;
                $actorName = $matrix->current_actor
                    ? trim(($matrix->current_actor->fname ?? '').' '.($matrix->current_actor->lname ?? ''))
                    : null;
            }
        }

        $canDelete = $activity->responsible_person_id == $userStaffId
            && in_array($status, ['draft', 'returned'], true)
            && $matrix;

        return [
            'row_num' => $rowNum,
            'id' => $activity->id,
            'activity_title' => $activity->activity_title ?: 'Untitled activity',
            'is_single_memo' => (bool) $activity->is_single_memo,
            'matrix_label' => $matrix ? ($matrix->year.' '.$matrix->quarter) : 'N/A',
            'matrix_url' => $matrix ? route('matrices.show', $matrix->id) : null,
            'division_name' => $matrix?->division?->division_name ?? 'N/A',
            'document_number' => $activity->document_number,
            'responsible_person_name' => $activity->responsiblePerson
                ? trim(($activity->responsiblePerson->fname ?? '').' '.($activity->responsiblePerson->lname ?? ''))
                : null,
            'date_range' => $this->formatActivityDateRange($activity),
            'fund_type_name' => $activity->fundType->name ?? 'N/A',
            'overall_status' => $status,
            'workflow_role' => $workflowRole,
            'current_actor_name' => $actorName && $actorName !== 'N/A' ? $actorName : null,
            'show_url' => ($matrix && ! $activity->is_single_memo)
                ? route('matrices.activities.show', [$matrix->id, $activity->id])
                : route('activities.single-memos.show', $activity->id),
            'delete_url' => $canDelete
                ? route('matrices.activities.destroy', [$matrix->id, $activity->id])
                : null,
            'print_url' => ($status === 'approved' && $matrix && ! $activity->is_single_memo)
                ? route('matrices.activities.memo-pdf', [$matrix->id, $activity->id])
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeSingleMemoIndexRow(Activity $memo, int $rowNum, int $userStaffId): array
    {
        $status = $memo->overall_status ?? 'draft';
        $statusMeta = in_array($status, ['pending', 'returned'], true) ? $memo->memoIndexStatusMeta() : null;
        $fundCodes = $memo->fundCodes ?? collect();
        $budgetCodeLabels = $fundCodes->isNotEmpty()
            ? $fundCodes->pluck('code')->filter()->unique()->values()->all()
            : [];
        $canDelete = $memo->responsible_person_id == $userStaffId
            && in_array($status, ['draft', 'returned'], true);
        $canCopy = function_exists('can_copy_memo') && can_copy_memo($memo);

        $responsibleName = null;
        $responsibleRole = null;
        if ($memo->responsiblePerson) {
            $responsibleName = trim(($memo->responsiblePerson->fname ?? '').' '.($memo->responsiblePerson->lname ?? ''));
            $responsibleRole = 'Responsible person';
        } elseif ($memo->staff) {
            $responsibleName = trim(($memo->staff->fname ?? '').' '.($memo->staff->lname ?? ''));
            $responsibleRole = 'Creator';
        }

        return [
            'row_num' => $rowNum,
            'id' => $memo->id,
            'document_number' => $memo->document_number,
            'activity_title' => strip_tags((string) $memo->activity_title),
            'background_preview' => Str::limit(strip_tags((string) $memo->background), 80),
            'responsible_person_name' => $responsibleName,
            'responsible_person_role' => $responsibleRole,
            'division_name' => $memo->matrix?->division?->division_name ?? 'N/A',
            'date_range' => $this->formatActivityDateRange($memo),
            'fund_type_name' => $memo->fundType->name ?? 'N/A',
            'fund_code_labels' => $budgetCodeLabels,
            'overall_status' => $status,
            'status_level' => $statusMeta['level'] ?? null,
            'workflow_role' => $statusMeta['role'] ?? null,
            'current_actor_name' => ($statusMeta['actor_name'] ?? null) !== 'N/A' ? ($statusMeta['actor_name'] ?? null) : null,
            'show_url' => route('activities.single-memos.show', $memo->id),
            'copy_url' => $canCopy ? route('activities.single-memos.copy', $memo->id) : null,
            'delete_url' => $canDelete ? route('activities.single-memos.destroy', $memo->id) : null,
            'print_url' => $status === 'approved' ? route('activities.single-memos.show', $memo->id) : null,
        ];
    }

    protected function formatActivityDateRange(Activity $activity): ?string
    {
        if (! $activity->date_from || ! $activity->date_to) {
            return null;
        }

        $from = $activity->date_from instanceof Carbon
            ? $activity->date_from
            : Carbon::parse($activity->date_from);
        $to = $activity->date_to instanceof Carbon
            ? $activity->date_to
            : Carbon::parse($activity->date_to);

        return $from->format('M d').' – '.$to->format('M d, Y');
    }

    /**
     * @param  iterable<int|string, int|string>  $years
     * @return list<array{title:string,value:string}>
     */
    protected function buildYearSelectOptions(iterable $years): array
    {
        $options = [];
        foreach ($years as $year) {
            $options[] = ['title' => (string) $year, 'value' => (string) $year];
        }

        return $options;
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildQuarterSelectOptions(): array
    {
        return [
            ['title' => 'Q1', 'value' => 'Q1'],
            ['title' => 'Q2', 'value' => 'Q2'],
            ['title' => 'Q3', 'value' => 'Q3'],
            ['title' => 'Q4', 'value' => 'Q4'],
        ];
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildDivisionSelectOptions(iterable $divisions): array
    {
        $options = [['title' => 'All divisions', 'value' => '']];
        foreach ($divisions as $division) {
            $divisionId = $division->division_id ?? $division->id ?? null;
            if ($divisionId === null) {
                continue;
            }
            $options[] = [
                'title' => $division->division_name ?? '',
                'value' => (string) $divisionId,
            ];
        }

        return $options;
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildStaffSelectOptions(iterable $staff): array
    {
        $options = [['title' => 'All staff', 'value' => '']];
        foreach ($staff as $member) {
            $staffId = $member->staff_id ?? $member->id ?? null;
            if ($staffId === null) {
                continue;
            }
            $label = isset($member->fname)
                ? trim(($member->fname ?? '').' '.($member->lname ?? ''))
                : ($member->name ?? '');
            $options[] = ['title' => $label, 'value' => (string) $staffId];
        }

        return $options;
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildMemoStatusSelectOptions(): array
    {
        return [
            ['title' => 'All statuses', 'value' => ''],
            ['title' => 'Draft', 'value' => 'draft'],
            ['title' => 'Pending', 'value' => 'pending'],
            ['title' => 'Approved', 'value' => 'approved'],
            ['title' => 'Returned', 'value' => 'returned'],
            ['title' => 'Rejected', 'value' => 'rejected'],
            ['title' => 'Archived', 'value' => 'archived'],
        ];
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildFundTypeSelectOptions(): array
    {
        $options = [['title' => 'All fund types', 'value' => '']];
        foreach (MemoFundTypeFilter::options() as $id => $label) {
            $options[] = ['title' => $label, 'value' => (string) $id];
        }

        return $options;
    }
}
