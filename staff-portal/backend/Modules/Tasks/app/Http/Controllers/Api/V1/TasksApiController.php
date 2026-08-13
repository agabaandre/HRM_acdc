<?php

namespace Modules\Tasks\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Models\PortalUser;
use Modules\Core\Support\PortalPermission;

class TasksApiController extends Controller
{
    /** @var array<int, string> */
    protected const STATUS_LABELS = [
        1 => 'Pending',
        2 => 'Completed',
        3 => 'Carried Forward',
        4 => 'Cancelled',
    ];

    public function hub(): JsonResponse
    {
        PortalPermission::authorize(78);

        $stats = [
            'total' => 0,
            'pending' => 0,
            'completed' => 0,
            'carried_forward' => 0,
            'cancelled' => 0,
            'overdue' => 0,
            'execution_rate' => 0.0,
        ];
        $workplanCount = 0;

        if (Schema::hasTable('work_plan_weekly_tasks')) {
            $divisionId = $this->resolveDivisionId(request());
            $base = $this->weeklyBaseQuery();
            $this->applyWeeklyFilters($base, $divisionId, null, null, null, null, null, '');
            $stats = $this->buildStats((clone $base)->get(['w.status', 'w.end_date']));
        }

        if (Schema::hasTable('workplan_tasks')) {
            $workplanCount = (int) DB::table('workplan_tasks')->count();
        }

        $links = [
            [
                'to' => '/tasks/weekly',
                'label' => 'Weekly tasks',
                'description' => 'Plan and track weekly activities, status, and execution rate.',
                'icon' => 'fa-solid fa-calendar-week',
                'count' => $stats['total'],
                'permission' => 75,
            ],
            [
                'to' => '/workplan',
                'label' => 'Workplan activities',
                'description' => 'Browse PRA-aligned workplan indicators and sub-activities.',
                'icon' => 'fa-solid fa-diagram-project',
                'count' => $workplanCount,
                'permission' => 79,
            ],
        ];

        return response()->json([
            'data' => [
                'summary' => [
                    'total' => $stats['total'],
                    'pending' => $stats['pending'],
                    'overdue' => $stats['overdue'],
                    'execution_rate' => $stats['execution_rate'],
                    'completed' => $stats['completed'],
                ],
                'links' => $links,
            ],
        ]);
    }

    /**
     * List weekly tasks with filters, execution stats, and division-scoped specific activities.
     * Specific activities = PRA specific_activities synced into work_planner_tasks.
     */
    public function weekly(Request $request): JsonResponse
    {
        PortalPermission::authorize(75);

        $divisionId = $this->resolveDivisionId($request);
        $staffId = $request->filled('staff_id') ? (int) $request->query('staff_id') : null;
        $status = $request->filled('status') ? (int) $request->query('status') : null;
        $startDate = $request->filled('start_date') ? (string) $request->query('start_date') : null;
        $endDate = $request->filled('end_date') ? (string) $request->query('end_date') : null;
        $specificId = $request->filled('work_planner_tasks_id')
            ? (int) $request->query('work_planner_tasks_id')
            : null;
        $q = trim((string) $request->query('q', ''));

        $tasks = collect();
        $stats = [
            'total' => 0,
            'pending' => 0,
            'completed' => 0,
            'carried_forward' => 0,
            'cancelled' => 0,
            'overdue' => 0,
            'execution_rate' => 0.0,
        ];

        if (Schema::hasTable('work_plan_weekly_tasks')) {
            $base = $this->weeklyBaseQuery();
            $this->applyWeeklyFilters($base, $divisionId, $staffId, $status, $startDate, $endDate, $specificId, $q);

            $statRows = (clone $base)->get(['w.status', 'w.end_date']);
            $stats = $this->buildStats($statRows);

            $select = [
                'w.activity_id',
                'w.staff_id',
                'w.work_planner_tasks_id',
                'w.activity_name',
                'w.week',
                'w.start_date',
                'w.end_date',
                'w.status',
                'w.comments',
                'w.created_by',
                'wt.id as workplan_id',
                'wt.activity_name as workplan_activity',
                'wt.division_id',
                'pt.activity_name as specific_activity',
                'pt.activity_name as planner_activity',
                'd.division_name',
                'd.division_short_name',
            ];
            if (Schema::hasColumn('work_planner_tasks', 'pra_activity_id')) {
                $select[] = 'pt.pra_activity_id';
            }

            $rows = (clone $base)
                ->orderByRaw('CASE WHEN w.status = 1 THEN 1 ELSE 0 END DESC')
                ->orderByDesc('w.start_date')
                ->limit(500)
                ->get($select);

            $staffNameMap = $this->staffNameMap($rows->pluck('staff_id')->all());
            $today = now()->toDateString();

            $tasks = $rows->map(function ($row) use ($staffNameMap, $today) {
                $status = (int) ($row->status ?? 0);
                $end = (string) ($row->end_date ?? '');
                $overdue = $end !== '' && $end < $today && in_array($status, [1, 3], true);

                return [
                    'activity_id' => (int) $row->activity_id,
                    'staff_id' => $row->staff_id,
                    'staff_name' => $this->resolveStaffNames((string) ($row->staff_id ?? ''), $staffNameMap),
                    'work_planner_tasks_id' => $row->work_planner_tasks_id ? (int) $row->work_planner_tasks_id : null,
                    'activity_name' => $row->activity_name,
                    'week' => $row->week,
                    'start_date' => $row->start_date,
                    'end_date' => $row->end_date,
                    'status' => $status,
                    'status_label' => self::STATUS_LABELS[$status] ?? (string) $status,
                    'overdue' => $overdue,
                    'comments' => $row->comments,
                    'workplan_id' => $row->workplan_id ? (int) $row->workplan_id : null,
                    'workplan_activity' => $row->workplan_activity,
                    'specific_activity' => $row->specific_activity,
                    'planner_activity' => $row->planner_activity,
                    'pra_activity_id' => isset($row->pra_activity_id) && $row->pra_activity_id
                        ? (int) $row->pra_activity_id
                        : null,
                    'division_id' => $row->division_id ? (int) $row->division_id : null,
                    'division_name' => $row->division_name,
                    'division_short_name' => $row->division_short_name,
                ];
            });
        }

        $staffList = $divisionId > 0 ? $this->staffListForDivision($divisionId) : collect();
        $financialYear = $this->currentFinancialYear();
        $specificActivities = $divisionId > 0
            ? $this->specificActivitiesForDivision($divisionId, $financialYear)
            : collect();

        return response()->json([
            'data' => $tasks,
            'meta' => [
                'division_id' => $divisionId,
                'financial_year' => $financialYear,
                'source' => 'work_plan_weekly_tasks',
                'ingested_from' => 'PRA specific_activities → work_planner_tasks → work_plan_weekly_tasks',
                'staff' => $staffList,
                'specific_activities' => $specificActivities,
                'status_options' => collect(self::STATUS_LABELS)
                    ->map(fn ($label, $value) => ['value' => $value, 'title' => $label])
                    ->values(),
                'stats' => $stats,
                'divisions' => DB::table('divisions')
                    ->orderBy('division_name')
                    ->get(['division_id', 'division_name', 'division_short_name']),
            ],
        ]);
    }

    /**
     * Create one or more weekly task rows against a division-scoped specific activity.
     */
    public function storeWeekly(Request $request): JsonResponse
    {
        PortalPermission::authorize(75);

        if (! Schema::hasTable('work_plan_weekly_tasks')) {
            return response()->json(['message' => 'Weekly tasks table is missing.'], 422);
        }

        $validated = $request->validate([
            'work_planner_tasks_id' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => ['integer', 'min:1'],
            'activities' => ['required', 'array', 'min:1'],
            'activities.*.activity_name' => ['required', 'string', 'max:500'],
            'activities.*.comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $session = $this->sessionUser();
        $actorId = (int) ($session['staff_id'] ?? 0);
        $divisionId = (int) ($session['division_id'] ?? 0);

        $plannerId = (int) $validated['work_planner_tasks_id'];
        $financialYear = $this->currentFinancialYear();
        if ($divisionId > 0 && ! $this->specificActivityBelongsToDivision($plannerId, $divisionId, $financialYear)) {
            return response()->json([
                'message' => 'That specific activity is not in your division for financial year '.$financialYear.'.',
            ], 422);
        }

        $staffIds = array_values(array_filter(array_map('intval', $validated['staff_ids'] ?? [])));
        $staffCsv = $staffIds !== [] ? implode(',', $staffIds) : (string) max(0, $actorId);
        $start = (string) $validated['start_date'];
        $end = (string) $validated['end_date'];
        $week = $this->weekLabel($start);

        $saved = 0;
        foreach ($validated['activities'] as $activity) {
            $name = trim((string) ($activity['activity_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $insert = [
                'staff_id' => $staffCsv,
                'work_planner_tasks_id' => $plannerId,
                'activity_name' => $name,
                'week' => $week,
                'start_date' => $start,
                'end_date' => $end,
                'comments' => trim((string) ($activity['comments'] ?? '')),
                'status' => 1,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ];
            if (Schema::hasColumn('work_plan_weekly_tasks', 'created_at')) {
                $insert['created_at'] = now();
            }
            DB::table('work_plan_weekly_tasks')->insert($insert);
            $saved++;
        }

        return response()->json([
            'message' => "{$saved} weekly task(s) saved.",
            'data' => ['saved' => $saved],
        ], 201);
    }

    /**
     * Update a pending weekly task (status / comments / staff / name). Carried Forward clones next week.
     */
    public function updateWeekly(Request $request, int $id): JsonResponse
    {
        PortalPermission::authorize(75);

        if (! Schema::hasTable('work_plan_weekly_tasks')) {
            return response()->json(['message' => 'Weekly tasks table is missing.'], 422);
        }

        $task = DB::table('work_plan_weekly_tasks')->where('activity_id', $id)->first();
        if (! $task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }
        if ((int) $task->status !== 1) {
            return response()->json(['message' => 'Only pending tasks can be edited.'], 422);
        }

        $validated = $request->validate([
            'activity_name' => ['required', 'string', 'max:500'],
            'comments' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'integer', 'in:1,2,3,4'],
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => ['integer', 'min:1'],
        ]);

        $session = $this->sessionUser();
        $actorId = (int) ($session['staff_id'] ?? 0);
        $staffIds = array_values(array_filter(array_map('intval', $validated['staff_ids'] ?? [])));
        $staffCsv = $staffIds !== []
            ? implode(',', $staffIds)
            : (string) ($task->staff_id ?: max(0, $actorId));

        $newStatus = (int) $validated['status'];
        DB::table('work_plan_weekly_tasks')->where('activity_id', $id)->update([
            'activity_name' => trim((string) $validated['activity_name']),
            'comments' => trim((string) ($validated['comments'] ?? '')),
            'status' => $newStatus,
            'staff_id' => $staffCsv,
            'updated_by' => $actorId,
        ]);

        if ($newStatus === 3) {
            $fresh = DB::table('work_plan_weekly_tasks')->where('activity_id', $id)->first();
            $newStart = date('Y-m-d', strtotime((string) $fresh->start_date.' +7 days'));
            $newEnd = date('Y-m-d', strtotime((string) $fresh->end_date.' +7 days'));
            $clone = [
                'staff_id' => $fresh->staff_id,
                'work_planner_tasks_id' => $fresh->work_planner_tasks_id,
                'activity_name' => $fresh->activity_name,
                'start_date' => $newStart,
                'end_date' => $newEnd,
                'week' => $this->weekLabel($newStart),
                'comments' => 'Auto-copied from previous week',
                'status' => 1,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ];
            if (Schema::hasColumn('work_plan_weekly_tasks', 'created_at')) {
                $clone['created_at'] = now();
            }
            DB::table('work_plan_weekly_tasks')->insert($clone);
        }

        return response()->json(['message' => 'Task updated successfully.']);
    }

    protected function weeklyBaseQuery(): Builder
    {
        $q = DB::table('work_plan_weekly_tasks as w')
            ->leftJoin('work_planner_tasks as pt', 'pt.activity_id', '=', 'w.work_planner_tasks_id')
            ->leftJoin('workplan_tasks as wt', 'wt.id', '=', 'pt.workplan_id');

        if (Schema::hasTable('divisions')) {
            $q->leftJoin('divisions as d', 'd.division_id', '=', 'wt.division_id');
        }

        return $q;
    }

    protected function applyWeeklyFilters(
        Builder $q,
        int $divisionId,
        ?int $staffId,
        ?int $status,
        ?string $startDate,
        ?string $endDate,
        ?int $specificId,
        string $search,
    ): void {
        if ($divisionId > 0) {
            $q->where('wt.division_id', $divisionId);
        }
        if ($staffId) {
            $q->whereRaw('FIND_IN_SET(?, w.staff_id) > 0', [$staffId]);
        }
        if ($status !== null && $status > 0) {
            $q->where('w.status', $status);
        }
        if ($startDate) {
            $q->where('w.start_date', '>=', $startDate);
        }
        if ($endDate) {
            $q->where('w.end_date', '<=', $endDate);
        }
        if ($specificId) {
            $q->where('w.work_planner_tasks_id', $specificId);
        }
        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function (Builder $w) use ($like): void {
                $w->where('w.activity_name', 'like', $like)
                    ->orWhere('w.comments', 'like', $like)
                    ->orWhere('pt.activity_name', 'like', $like)
                    ->orWhere('wt.activity_name', 'like', $like);
            });
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return array{
     *   total: int,
     *   pending: int,
     *   completed: int,
     *   carried_forward: int,
     *   cancelled: int,
     *   overdue: int,
     *   execution_rate: float
     * }
     */
    protected function buildStats($rows): array
    {
        $stats = [
            'total' => $rows->count(),
            'pending' => 0,
            'completed' => 0,
            'carried_forward' => 0,
            'cancelled' => 0,
            'overdue' => 0,
            'execution_rate' => 0.0,
        ];
        $today = now()->toDateString();

        foreach ($rows as $row) {
            $status = (int) ($row->status ?? 0);
            $end = (string) ($row->end_date ?? '');
            match ($status) {
                1 => $stats['pending']++,
                2 => $stats['completed']++,
                3 => $stats['carried_forward']++,
                4 => $stats['cancelled']++,
                default => null,
            };
            if ($end !== '' && $end < $today && in_array($status, [1, 3], true)) {
                $stats['overdue']++;
            }
        }

        if ($stats['total'] > 0) {
            $stats['execution_rate'] = round(($stats['completed'] / $stats['total']) * 100, 1);
        }

        return $stats;
    }

    /**
     * Specific activities for a division, limited to the current (or given) financial year.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function specificActivitiesForDivision(int $divisionId, ?string $financialYear = null)
    {
        if (! Schema::hasTable('work_planner_tasks') || ! Schema::hasTable('workplan_tasks')) {
            return collect();
        }

        $year = $financialYear ?: $this->currentFinancialYear();

        $cols = [
            'pt.activity_id',
            'pt.activity_name',
            'pt.workplan_id',
            'wt.activity_name as workplan_activity',
            'wt.year',
        ];
        if (Schema::hasColumn('work_planner_tasks', 'pra_activity_id')) {
            $cols[] = 'pt.pra_activity_id';
        }

        return DB::table('work_planner_tasks as pt')
            ->join('workplan_tasks as wt', 'wt.id', '=', 'pt.workplan_id')
            ->where('wt.division_id', $divisionId)
            ->where('wt.year', $year)
            ->orderBy('pt.activity_name')
            ->limit(1000)
            ->get($cols);
    }

    protected function specificActivityBelongsToDivision(
        int $plannerId,
        int $divisionId,
        ?string $financialYear = null,
    ): bool {
        if ($divisionId < 1) {
            return true;
        }

        $meta = $this->plannerActivityMeta($plannerId);
        if ($meta === null || (int) $meta['division_id'] !== $divisionId) {
            return false;
        }

        if ($financialYear !== null && (string) $meta['year'] !== (string) $financialYear) {
            return false;
        }

        return true;
    }

    /**
     * @return array{division_id: int, year: string}|null
     */
    protected function plannerActivityMeta(int $plannerId): ?array
    {
        if (! Schema::hasTable('work_planner_tasks')) {
            return null;
        }
        $row = DB::table('work_planner_tasks as pt')
            ->join('workplan_tasks as wt', 'wt.id', '=', 'pt.workplan_id')
            ->where('pt.activity_id', $plannerId)
            ->first(['wt.division_id', 'wt.year']);

        if (! $row) {
            return null;
        }

        return [
            'division_id' => (int) $row->division_id,
            'year' => (string) ($row->year ?? ''),
        ];
    }

    protected function divisionIdForPlanner(int $plannerId): ?int
    {
        $meta = $this->plannerActivityMeta($plannerId);

        return $meta['division_id'] ?? null;
    }

    /**
     * Current PRA / workplan financial year (calendar year unless PRA_WORKPLAN_FISCAL_YEAR is set).
     */
    protected function currentFinancialYear(): string
    {
        $configured = config('workplan.pra.fiscal_year');
        if ($configured !== null && $configured !== '') {
            return (string) (int) $configured;
        }

        return (string) now()->year;
    }

    /**
     * @param  list<mixed>  $staffIdCsvValues
     * @return array<int, string>
     */
    protected function staffNameMap(array $staffIdCsvValues): array
    {
        $ids = [];
        foreach ($staffIdCsvValues as $csv) {
            foreach (explode(',', (string) $csv) as $part) {
                $id = (int) trim($part);
                if ($id > 0) {
                    $ids[$id] = true;
                }
            }
        }
        if ($ids === []) {
            return [];
        }

        return DB::table('staff')
            ->whereIn('staff_id', array_keys($ids))
            ->get(['staff_id', 'fname', 'lname'])
            ->mapWithKeys(fn ($s) => [
                (int) $s->staff_id => trim(($s->fname ?? '').' '.($s->lname ?? '')),
            ])
            ->all();
    }

    /**
     * @param  array<int, string>  $map
     */
    protected function resolveStaffNames(string $csv, array $map): string
    {
        $names = [];
        foreach (explode(',', $csv) as $part) {
            $id = (int) trim($part);
            if ($id > 0) {
                $names[] = $map[$id] ?? ('#'.$id);
            }
        }

        return $names !== [] ? implode(', ', $names) : '—';
    }

    protected function staffListForDivision(int $divisionId)
    {
        return $this->staffInDivisionQuery($divisionId)
            ->orderBy('s.lname')
            ->orderBy('s.fname')
            ->limit(200)
            ->get(['s.staff_id', 's.fname', 's.lname']);
    }

    protected function staffInDivisionQuery(int $divisionId): Builder
    {
        $latest = DB::table('staff_contracts')
            ->selectRaw('staff_id, MAX(staff_contract_id) as cid')
            ->groupBy('staff_id');

        return DB::table('staff as s')
            ->joinSub($latest, 'lc', 'lc.staff_id', '=', 's.staff_id')
            ->join('staff_contracts as sc', 'sc.staff_contract_id', '=', 'lc.cid')
            ->where('sc.division_id', $divisionId);
    }

    protected function resolveDivisionId(Request $request): int
    {
        if ($request->filled('division_id')) {
            return (int) $request->query('division_id');
        }
        $session = $this->sessionUser();

        return (int) ($session['division_id'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sessionUser(): array
    {
        $user = auth()->user();
        if ($user instanceof PortalUser) {
            return $user->toSessionArray();
        }

        return (array) (session('user') ?? []);
    }

    protected function weekLabel(string $start): string
    {
        $startFmt = date('M d', strtotime($start) ?: time());
        $endFmt = date('M d, Y', strtotime($start.' +4 days') ?: time());

        return "Week: {$startFmt} - {$endFmt}";
    }
}
