<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\WeeklyBriefingContributor;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use App\Services\DivisionWeeklyBriefGate;
use App\Services\WeeklyBriefingCompletionSummary;
use App\Services\WeeklyBriefingWindowService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WeeklyBriefingController extends Controller
{
    public function index(Request $request): View
    {
        $this->assertDivisionWeeklyBriefModuleAccess();

        $keys = DivisionWeeklyBriefGate::contributionKeysForCurrentUser();
        if ($keys === [] && ! DivisionWeeklyBriefGate::isSystemAdmin()) {
            abort(403, 'No Division Weekly Brief access for your account.');
        }

        $year = (int) $request->get('year', Carbon::now()->isoWeekYear());
        $week = (int) $request->get('week', Carbon::now()->isoWeek());

        $reportsQuery = WeeklyBriefingReport::query()
            ->where('report_iso_week_year', $year)
            ->orderByDesc('report_iso_week');
        if ($keys !== []) {
            $reportsQuery->whereIn('contribution_key', $keys);
        } else {
            $reportsQuery->whereRaw('0 = 1');
        }
        $reports = $reportsQuery->paginate(12);

        $cy = Carbon::now()->isoWeekYear();
        $cw = Carbon::now()->isoWeek();

        $currentQuery = WeeklyBriefingReport::query()
            ->where('report_iso_week_year', $cy)
            ->where('report_iso_week', $cw)
            ->orderBy('contribution_key');
        if ($keys !== []) {
            $currentQuery->whereIn('contribution_key', $keys);
        } else {
            $currentQuery->whereRaw('0 = 1');
        }
        $currentWeekReports = $currentQuery->get();

        $startUrls = [];
        foreach ($keys as $k) {
            if ($currentWeekReports->contains('contribution_key', $k)) {
                continue;
            }
            $startUrls[$k] = route('weekly-briefing.create', ['contribution_key' => $k]);
        }

        $divisionId = $this->userPrimaryDivisionId();

        return view('weekly-briefing.index', compact(
            'reports',
            'currentWeekReports',
            'startUrls',
            'divisionId',
            'year',
            'week'
        ));
    }

    public function create(Request $request): RedirectResponse
    {
        $this->assertDivisionWeeklyBriefModuleAccess();

        $settings = WeeklyBriefingSetting::current();
        $staffId = (int) user_session('staff_id');
        $divisionId = $this->userPrimaryDivisionId();

        $contributionKey = (string) $request->query('contribution_key', '');

        if ($contributionKey === '') {
            return redirect()->route('weekly-briefing.index')->with('error', 'Choose a reporting unit to start.');
        }

        if (! DivisionWeeklyBriefGate::mayUseContributionKey($contributionKey)) {
            abort(403);
        }

        $contributorRow = $settings->contributors()
            ->where('contribution_key', $contributionKey)
            ->where('staff_id', $staffId)
            ->first()
            ?? $settings->contributors()->where('contribution_key', $contributionKey)->first();

        $apmDivisionId = $contributorRow
            ? (int) $contributorRow->apm_division_id
            : (int) $divisionId;

        $isoYear = (int) $request->get('iso_year', Carbon::now()->isoWeekYear());
        $isoWeek = (int) $request->get('iso_week', Carbon::now()->isoWeek());
        $periodStart = WeeklyBriefingReport::periodMonday($isoYear, $isoWeek);

        if (str_starts_with($contributionKey, 'dr-')) {
            $dirId = (int) substr($contributionKey, 3);
            $report = WeeklyBriefingReport::query()->firstOrCreate(
                [
                    'contribution_key' => $contributionKey,
                    'report_iso_week_year' => $isoYear,
                    'report_iso_week' => $isoWeek,
                ],
                [
                    'division_id' => $apmDivisionId,
                    'directorate_id' => $dirId,
                    'period_start' => $periodStart->toDateString(),
                    'status' => WeeklyBriefingReport::STATUS_DRAFT,
                    'section1_major_happenings' => $this->defaultSection1(),
                    'section2_bottlenecks' => [['issue' => '', 'impact_risk' => '', 'required_action' => '']],
                ]
            );
        } else {
            $divId = (int) substr($contributionKey, 2);
            $division = Division::query()->findOrFail($divId);
            $report = WeeklyBriefingReport::query()->firstOrCreate(
                [
                    'contribution_key' => $contributionKey,
                    'report_iso_week_year' => $isoYear,
                    'report_iso_week' => $isoWeek,
                ],
                [
                    'division_id' => $division->id,
                    'directorate_id' => $division->directorate_id,
                    'period_start' => $periodStart->toDateString(),
                    'status' => WeeklyBriefingReport::STATUS_DRAFT,
                    'section1_major_happenings' => $this->defaultSection1(),
                    'section2_bottlenecks' => [['issue' => '', 'impact_risk' => '', 'required_action' => '']],
                ]
            );
        }

        return redirect()->route('weekly-briefing.edit', $report);
    }

    public function edit(WeeklyBriefingReport $report): View
    {
        $this->assertDivisionWeeklyBriefModuleAccess();
        $this->assertCanAccessReport($report);

        $report->load(['division', 'directorate']);
        $settings = WeeklyBriefingSetting::current();
        $window = new WeeklyBriefingWindowService;

        return view('weekly-briefing.edit', compact('report', 'settings', 'window'));
    }

    public function update(Request $request, WeeklyBriefingReport $report): RedirectResponse
    {
        $this->assertDivisionWeeklyBriefModuleAccess();
        $this->assertCanAccessReport($report);

        $window = new WeeklyBriefingWindowService;
        if (! $window->canEditReport($report)) {
            return back()->with('error', 'This weekly briefing is no longer editable (deadline passed or locked).');
        }

        $validated = $request->validate([
            'section1' => 'required|array|max:3',
            'section1.*.description_key_actions' => 'nullable|string',
            'section1.*.strategic_relevance' => 'nullable|string',
            'section2' => 'nullable|array',
            'section2.*.issue' => 'nullable|string|max:20000',
            'section2.*.impact_risk' => 'nullable|string|max:20000',
            'section2.*.required_action' => 'nullable|string|max:20000',
        ]);

        $section1 = [];
        foreach (array_slice($validated['section1'], 0, 3) as $row) {
            $d = trim((string) ($row['description_key_actions'] ?? ''));
            $s = trim((string) ($row['strategic_relevance'] ?? ''));
            if ($d === '' && $s === '') {
                continue;
            }
            $section1[] = [
                'description_key_actions' => $d,
                'strategic_relevance' => $s,
            ];
        }

        if (count($section1) === 0) {
            return back()->withInput()->with('error', 'Add at least one major happening (description & key actions, and strategic relevance).');
        }

        $section2 = [];
        foreach ($validated['section2'] ?? [] as $row) {
            $issue = trim((string) ($row['issue'] ?? ''));
            $impact = trim((string) ($row['impact_risk'] ?? ''));
            $action = trim((string) ($row['required_action'] ?? ''));
            if ($issue === '' && $impact === '' && $action === '') {
                continue;
            }
            $section2[] = [
                'issue' => $issue,
                'impact_risk' => $impact,
                'required_action' => $action,
            ];
        }

        $report->section1_major_happenings = $section1;
        $report->section2_bottlenecks = $section2;

        if ($request->boolean('submit_final') && $window->canSubmitReport($report)) {
            $report->status = WeeklyBriefingReport::STATUS_SUBMITTED;
            $report->submitted_at = now();
            $report->submitted_by_staff_id = (int) user_session('staff_id');
        }

        $report->save();

        return redirect()->route('weekly-briefing.edit', $report)
            ->with('status', $request->boolean('submit_final') ? 'Weekly briefing submitted.' : 'Draft saved.');
    }

    public function pdf(WeeklyBriefingReport $report)
    {
        $this->assertDivisionWeeklyBriefModuleAccess();
        $this->assertCanAccessReport($report);

        $report->load(['division', 'directorate']);
        $settings = WeeklyBriefingSetting::current();

        $pdf = mpdf_print('weekly-briefing.pdf-division', [
            'report' => $report,
            'settings' => $settings,
        ], []);

        $label = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $report->contributionEntityLabel());
        $fn = 'Weekly_Briefing_'.$label.'_W'.$report->report_iso_week.'_'.$report->report_iso_week_year.'.pdf';

        return response($pdf->Output($fn, 'I'), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function compiledPdf(int $year, int $week)
    {
        $role = (int) (user_session('role') ?? user_session('user_role') ?? 0);
        $perms = user_session('permissions', []) ?? [];
        if ($role !== 10 && ! in_array(87, $perms, true) && ! in_array(88, $perms, true)) {
            abort(403);
        }

        $reports = WeeklyBriefingReport::query()
            ->where('report_iso_week_year', $year)
            ->where('report_iso_week', $week)
            ->where('status', WeeklyBriefingReport::STATUS_SUBMITTED)
            ->with(['division', 'directorate'])
            ->get();

        $reports = WeeklyBriefingCompletionSummary::sortReportsForCompiled($reports);

        $settings = WeeklyBriefingSetting::current();

        $pdf = mpdf_print('weekly-briefing.pdf-compiled', [
            'reports' => $reports,
            'settings' => $settings,
            'isoYear' => $year,
            'isoWeek' => $week,
        ], []);

        $fn = 'Weekly_Briefing_Compiled_W'.$week.'_'.$year.'.pdf';

        return response($pdf->Output($fn, 'I'), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function completionSummaryPdf(int $year, int $week)
    {
        $role = (int) (user_session('role') ?? user_session('user_role') ?? 0);
        $perms = user_session('permissions', []) ?? [];
        if ($role !== 10 && ! in_array(87, $perms, true) && ! in_array(88, $perms, true)) {
            abort(403);
        }

        $settings = WeeklyBriefingSetting::current();
        $rows = WeeklyBriefingCompletionSummary::rows($settings, $year, $week);

        $pdf = mpdf_print('weekly-briefing.pdf-completion-summary', [
            'rows' => $rows,
            'settings' => $settings,
            'isoYear' => $year,
            'isoWeek' => $week,
        ], []);

        $fn = 'Weekly_Briefing_Completion_W'.$week.'_'.$year.'.pdf';

        return response($pdf->Output($fn, 'I'), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function defaultSection1(): array
    {
        return [
            ['description_key_actions' => '', 'strategic_relevance' => ''],
            ['description_key_actions' => '', 'strategic_relevance' => ''],
            ['description_key_actions' => '', 'strategic_relevance' => ''],
        ];
    }

    private function userPrimaryDivisionId(): ?int
    {
        $id = user_session('division_id');

        return $id !== null && $id !== '' ? (int) $id : null;
    }

    private function assertDivisionWeeklyBriefModuleAccess(): void
    {
        if (! DivisionWeeklyBriefGate::canAccessModule()) {
            abort(403, 'You do not have access to Division Weekly Brief.');
        }
    }

    private function assertCanAccessReport(WeeklyBriefingReport $report): void
    {
        if (DivisionWeeklyBriefGate::isSystemAdmin()) {
            return;
        }

        $settings = WeeklyBriefingSetting::current();
        $uid = (int) user_session('staff_id');
        if ($settings->contributors()->where('staff_id', $uid)->where('contribution_key', $report->contribution_key)->exists()) {
            return;
        }

        abort(403);
    }
}
