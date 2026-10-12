<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityReport;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use App\Services\ActivityReport\ActivityReportAccessService;
use App\Services\ActivityReport\ActivityReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $pageConfig = [
            'report' => $activityReport->toArray(),
            'canEdit' => $this->access->canEdit($activityReport, $staffId),
            'missionTypes' => ActivityReportService::MISSION_TYPES,
            'updateUrl' => route('activity-reports.update', $activityReport),
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
