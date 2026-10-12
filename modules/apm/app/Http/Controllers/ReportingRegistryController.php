<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityReport;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use App\Services\ActivityReport\ActivityReportRegistryService;
use App\Services\ActivityReport\ActivityReportService;
use App\Support\ActivityReportPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportingRegistryController extends Controller
{
    public function __construct(
        private ActivityReportRegistryService $registry,
        private ActivityReportService $reports,
    ) {}

    public function index(): View
    {
        $bounds = $this->registry->currentQuarterBounds();

        $pageConfig = [
            'canProcess' => ActivityReportPermission::userCanProcess(),
            'dataUrl' => route('reporting-registry.data'),
            'startUrl' => url('reporting-registry/start'),
            'processUrl' => url('reporting-registry/process'),
            'previewUrl' => url('reporting-registry/preview'),
            'openReportUrl' => url('activity-reports/open'),
            'csrfToken' => csrf_token(),
            'year' => $bounds['year'],
            'quarter' => $bounds['quarter'],
        ];

        return view('reporting-registry.index', compact('pageConfig'));
    }

    public function data(Request $request): JsonResponse
    {
        $divisionId = $request->integer('division_id') ?: null;
        $rows = $this->registry->listCurrentQuarter($divisionId);

        return response()->json(['data' => $rows]);
    }

    public function start(Request $request): JsonResponse
    {
        $this->assertCanProcess();
        $validated = $this->validateSource($request);
        $staffId = (int) (user_session('staff_id') ?? 0);
        $row = $this->registry->markStarted($validated['source_type'], $validated['source_id'], $staffId);

        return response()->json(['data' => $row]);
    }

    public function process(Request $request): JsonResponse
    {
        $this->assertCanProcess();
        $validated = $this->validateSource($request);
        $staffId = (int) (user_session('staff_id') ?? 0);
        $row = $this->registry->markProcessed($validated['source_type'], $validated['source_id'], $staffId);

        return response()->json(['data' => $row]);
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $this->validateSource($request);
        $report = ActivityReport::query()
            ->where('source_id', $validated['source_id'])
            ->where('source_type', $validated['source_type'])
            ->first();

        if (! $report) {
            return response()->json(['message' => 'No activity report yet.'], 404);
        }

        return response()->json(['data' => $this->reports->previewPayload($report)]);
    }

    private function assertCanProcess(): void
    {
        if (! ActivityReportPermission::userCanProcess()) {
            abort(403, 'Missing Reporting & Finance Registry permission.');
        }
    }

    /**
     * @return array{source_type: class-string, source_id: int}
     */
    private function validateSource(Request $request): array
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

        $type = $map[$validated['source_type']] ?? null;
        if ($type === null) {
            abort(422, 'Invalid source type.');
        }

        return [
            'source_type' => $type,
            'source_id' => (int) $validated['source_id'],
        ];
    }
}
