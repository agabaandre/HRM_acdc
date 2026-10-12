<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityReport;
use App\Models\ActivityReportRelated;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use App\Services\ActivityReport\ActivityReportAccessService;
use App\Services\ActivityReport\ActivityReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ActivityReportController extends Controller
{
    public function __construct(
        private ActivityReportService $reports,
        private ActivityReportAccessService $access,
    ) {}

    public function open(Request $request): RedirectResponse|View
    {
        $source = $this->resolveSource($request);
        $staffId = (int) (user_session('staff_id') ?? 0);

        if (! $this->access->canView($source, $staffId) && ! $this->isAdminLike()) {
            abort(403, 'You are not a participant on this activity.');
        }

        $report = $this->reports->findOrCreateForSource($source, $staffId > 0 ? $staffId : (int) ($source->staff_id ?? 0));

        return redirect()->route('activity-reports.edit', $report);
    }

    public function edit(ActivityReport $activityReport): View
    {
        $staffId = (int) (user_session('staff_id') ?? 0);
        $activityReport->loadMissing('source');
        $source = $activityReport->source ?? $activityReport;

        if (! $this->access->canView($source, $staffId) && ! $this->isAdminLike()) {
            abort(403);
        }

        $activityReport->loadMissing('relatedLinks');
        $related = $activityReport->relatedLinks->map(fn (ActivityReportRelated $link) => [
            'value' => $link->related_source_type.'#'.$link->related_source_id,
            'related_source_type' => $link->related_source_type,
            'related_source_id' => (int) $link->related_source_id,
            'title' => $this->relatedLabel($link->related_source_type, (int) $link->related_source_id),
        ])->values()->all();

        $pageConfig = [
            'report' => $activityReport->toArray(),
            'related' => $related,
            'canEdit' => $this->access->canEdit($activityReport, $staffId),
            'missionTypes' => ActivityReportService::MISSION_TYPES,
            'updateUrl' => route('activity-reports.update', $activityReport),
            'relatedSearchUrl' => route('activity-reports.related.search', $activityReport),
            'relatedSyncUrl' => route('activity-reports.related.sync', $activityReport),
            'notExecutedUrl' => route('activity-reports.not-executed', $activityReport),
            'csrfToken' => csrf_token(),
        ];

        return view('activity-reports.form', compact('activityReport', 'pageConfig'));
    }

    public function update(Request $request, ActivityReport $activityReport): JsonResponse
    {
        $staffId = (int) (user_session('staff_id') ?? 0);
        $activityReport->loadMissing('source');
        if (! $this->access->canEdit($activityReport, $staffId) && ! $this->isAdminLike()) {
            abort(403);
        }

        $validated = $request->validate([
            'mission_type' => 'nullable|string|max:64',
            'location_venue' => 'nullable|string|max:500',
            'objectives' => 'nullable|string',
            'narrative_achievements' => 'nullable|string',
            'challenges_recommendations' => 'nullable|string',
            'participants_count' => 'nullable|integer|min:0',
            'member_states_covered' => 'nullable|string',
            'key_strategic_needs' => 'nullable|string',
            'means_of_verification' => 'nullable|string',
            'budget_code' => 'nullable|string|max:100',
            'total_cost_usd' => 'nullable|numeric',
            'logistics_actuals' => 'nullable|string',
            'mission_extras' => 'nullable|array',
        ]);

        $activityReport->fill($validated);
        $activityReport->updated_by = $staffId > 0 ? $staffId : $activityReport->updated_by;
        $activityReport->save();

        return response()->json(['data' => $activityReport->fresh()]);
    }

    public function searchRelated(Request $request, ActivityReport $activityReport): JsonResponse
    {
        $this->assertCanView($activityReport);
        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
        $excludeType = $activityReport->source_type;
        $excludeId = (int) $activityReport->source_id;
        $results = collect();

        foreach ([
            [Activity::class, 'Activity', 'activity_title'],
            [SpecialMemo::class, 'Special memo', 'activity_title'],
            [NonTravelMemo::class, 'Non-travel', 'activity_title'],
        ] as [$class, $label, $titleCol]) {
            $rows = $class::query()
                ->select(['id', $titleCol.' as title', 'document_number'])
                ->where(function ($w) use ($like, $titleCol) {
                    $w->where($titleCol, 'like', $like)
                        ->orWhere('document_number', 'like', $like);
                })
                ->when($class === $excludeType, fn ($w) => $w->where('id', '!=', $excludeId))
                ->orderByDesc('id')
                ->limit(15)
                ->get();

            foreach ($rows as $row) {
                if ($class === $excludeType && (int) $row->id === $excludeId) {
                    continue;
                }
                $results->push([
                    'value' => $class.'#'.$row->id,
                    'related_source_type' => $class,
                    'related_source_id' => (int) $row->id,
                    'title' => trim(($row->document_number ? $row->document_number.' — ' : '').($row->title ?? '').' ('.$label.')'),
                ]);
            }
        }

        return response()->json(['data' => $results->take(30)->values()->all()]);
    }

    public function syncRelated(Request $request, ActivityReport $activityReport): JsonResponse
    {
        $this->assertCanEdit($activityReport);
        $validated = $request->validate([
            'related' => 'nullable|array',
            'related.*.related_source_type' => 'required|string',
            'related.*.related_source_id' => 'required|integer|min:1',
        ]);

        $items = $validated['related'] ?? [];
        DB::transaction(function () use ($activityReport, $items) {
            ActivityReportRelated::query()->where('activity_report_id', $activityReport->id)->delete();
            foreach ($items as $item) {
                if ($item['related_source_type'] === $activityReport->source_type
                    && (int) $item['related_source_id'] === (int) $activityReport->source_id) {
                    continue;
                }
                ActivityReportRelated::query()->create([
                    'activity_report_id' => $activityReport->id,
                    'related_source_type' => $item['related_source_type'],
                    'related_source_id' => (int) $item['related_source_id'],
                ]);
            }
        });

        return response()->json(['message' => 'Related activities saved.']);
    }

    public function setNotExecuted(Request $request, ActivityReport $activityReport): JsonResponse
    {
        $this->assertCanEdit($activityReport);
        $validated = $request->validate([
            'not_executed' => 'required|boolean',
        ]);
        $activityReport->not_executed = (bool) $validated['not_executed'];
        $activityReport->updated_by = (int) (user_session('staff_id') ?? $activityReport->updated_by);
        $activityReport->save();

        return response()->json(['data' => $activityReport->fresh()]);
    }

    private function assertCanView(ActivityReport $activityReport): void
    {
        $staffId = (int) (user_session('staff_id') ?? 0);
        $activityReport->loadMissing('source');
        $source = $activityReport->source ?? $activityReport;
        if (! $this->access->canView($source, $staffId) && ! $this->isAdminLike()) {
            abort(403);
        }
    }

    private function assertCanEdit(ActivityReport $activityReport): void
    {
        $staffId = (int) (user_session('staff_id') ?? 0);
        if (! $this->access->canEdit($activityReport, $staffId) && ! $this->isAdminLike()) {
            abort(403);
        }
    }

    private function relatedLabel(string $type, int $id): string
    {
        $model = match ($type) {
            Activity::class => Activity::query()->find($id),
            SpecialMemo::class => SpecialMemo::query()->find($id),
            NonTravelMemo::class => NonTravelMemo::query()->find($id),
            default => null,
        };
        if (! $model) {
            return $type.'#'.$id;
        }
        $title = (string) ($model->activity_title ?? '');
        $doc = (string) ($model->document_number ?? '');

        return trim(($doc !== '' ? $doc.' — ' : '').$title) ?: ($type.'#'.$id);
    }

    /**
     * @return Activity|SpecialMemo|NonTravelMemo
     */
    private function resolveSource(Request $request): Activity|SpecialMemo|NonTravelMemo
    {
        $validated = $request->validate([
            'source_type' => 'required|string',
            'source_id' => 'required|integer|min:1',
        ]);

        $map = [
            'activity' => Activity::class,
            'Activity' => Activity::class,
            Activity::class => Activity::class,
            'special_memo' => SpecialMemo::class,
            'SpecialMemo' => SpecialMemo::class,
            SpecialMemo::class => SpecialMemo::class,
            'non_travel' => NonTravelMemo::class,
            'non_travel_memo' => NonTravelMemo::class,
            'NonTravelMemo' => NonTravelMemo::class,
            NonTravelMemo::class => NonTravelMemo::class,
        ];

        $class = $map[$validated['source_type']] ?? null;
        if ($class === null) {
            abort(422, 'Invalid source type.');
        }

        return $class::query()->findOrFail((int) $validated['source_id']);
    }

    private function isAdminLike(): bool
    {
        $role = (int) (user_session('role') ?? user_session('user_role') ?? 0);

        return $role === 10;
    }
}
