<?php

namespace Modules\Workplan\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Support\PortalPermission;
use Modules\Workplan\Services\PraWorkplanSyncService;

class WorkplanApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        PortalPermission::authorize(79);

        if (! Schema::hasTable('workplan_tasks')) {
            return response()->json(['data' => [], 'meta' => ['message' => 'workplan_tasks table missing']]);
        }

        $q = DB::table('workplan_tasks as wt')
            ->leftJoin('divisions as d', 'd.division_id', '=', 'wt.division_id')
            ->orderByDesc('wt.year')
            ->orderBy('wt.activity_name');

        if ($request->filled('division_id')) {
            $q->where('wt.division_id', (int) $request->query('division_id'));
        }
        if ($request->filled('year')) {
            $q->where('wt.year', (int) $request->query('year'));
        }
        if ($request->filled('q')) {
            $term = '%'.$request->query('q').'%';
            $q->where(function ($w) use ($term): void {
                $w->where('wt.activity_name', 'like', $term)
                    ->orWhere('wt.broad_activity', 'like', $term)
                    ->orWhere('wt.intermediate_outcome', 'like', $term)
                    ->orWhere('wt.output_indicator', 'like', $term);
            });
        }

        $select = [
            'wt.id',
            'wt.division_id',
            'd.division_name',
            'd.division_short_name',
            'wt.year',
            'wt.activity_name',
            'wt.broad_activity',
            'wt.intermediate_outcome',
            'wt.output_indicator',
            'wt.cumulative_target',
            'wt.has_budget',
        ];
        if (Schema::hasColumn('workplan_tasks', 'pra_indicator_id')) {
            $select[] = 'wt.pra_indicator_id';
            $select[] = 'wt.pra_division_code';
        }

        $rows = $q->limit(500)->get($select);

        $subCounts = [];
        if (Schema::hasTable('work_planner_tasks') && $rows->isNotEmpty()) {
            $ids = $rows->pluck('id')->all();
            $subCounts = DB::table('work_planner_tasks')
                ->whereIn('workplan_id', $ids)
                ->selectRaw('workplan_id, COUNT(*) as c')
                ->groupBy('workplan_id')
                ->pluck('c', 'workplan_id')
                ->all();
        }

        $data = $rows->map(function ($row) use ($subCounts) {
            $arr = (array) $row;
            $arr['sub_activity_count'] = (int) ($subCounts[$row->id] ?? 0);

            return $arr;
        })->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'source' => 'workplan_tasks',
                'pra_configured' => (string) config('workplan.pra.api_key') !== '',
                'divisions' => DB::table('divisions')
                    ->orderBy('division_name')
                    ->get(['division_id', 'division_name', 'division_short_name']),
            ],
        ]);
    }

    public function sync(Request $request, PraWorkplanSyncService $sync): JsonResponse
    {
        PortalPermission::authorize(79);

        $validated = $request->validate([
            'year' => 'nullable|integer|min:2020|max:2100',
            'division' => 'nullable|string|max:32',
            'divisions' => 'nullable|array',
            'divisions.*' => 'string|max:32',
        ]);

        $codes = [];
        if (! empty($validated['division'])) {
            $codes[] = (string) $validated['division'];
        }
        if (! empty($validated['divisions']) && is_array($validated['divisions'])) {
            foreach ($validated['divisions'] as $code) {
                $codes[] = (string) $code;
            }
        }
        $codes = $codes !== [] ? array_values(array_unique($codes)) : null;

        try {
            $result = $sync->sync(
                isset($validated['year']) ? (int) $validated['year'] : null,
                $codes,
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => sprintf(
                'PRA sync finished: %d indicators, %d activities.',
                $result['indicators_upserted'],
                $result['activities_upserted'],
            ),
            'data' => $result,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        PortalPermission::authorize(79);

        $plan = DB::table('workplan_tasks as wt')
            ->leftJoin('divisions as d', 'd.division_id', '=', 'wt.division_id')
            ->where('wt.id', $id)
            ->first([
                'wt.*',
                'd.division_name',
            ]);

        if (! $plan) {
            return response()->json(['message' => 'Workplan activity not found.'], 404);
        }

        $subs = Schema::hasTable('work_planner_tasks')
            ? DB::table('work_planner_tasks')->where('workplan_id', $id)->orderBy('start_date')->get()
            : collect();

        $weekly = collect();
        if (Schema::hasTable('work_plan_weekly_tasks') && $subs->isNotEmpty()) {
            $plannerIds = $subs->pluck('activity_id')->filter()->all();
            $weekly = DB::table('work_plan_weekly_tasks as w')
                ->leftJoin('staff as s', 's.staff_id', '=', 'w.staff_id')
                ->whereIn('w.work_planner_tasks_id', $plannerIds)
                ->orderByDesc('w.start_date')
                ->limit(300)
                ->get([
                    'w.activity_id',
                    'w.work_planner_tasks_id',
                    'w.staff_id',
                    'w.activity_name',
                    'w.week',
                    'w.start_date',
                    'w.end_date',
                    'w.status',
                    'w.comments',
                    DB::raw("TRIM(CONCAT(COALESCE(s.fname,''), ' ', COALESCE(s.lname,''))) as staff_name"),
                ]);
        }

        return response()->json([
            'data' => [
                'plan' => $plan,
                'sub_activities' => $subs,
                'weekly_tasks' => $weekly,
            ],
            'meta' => [
                'ingested_from' => 'workplan_tasks → work_planner_tasks → work_plan_weekly_tasks',
            ],
        ]);
    }
}
