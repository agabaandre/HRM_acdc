<?php

namespace App\Http\Controllers;

use App\Models\Directorate;
use App\Models\Division;
use App\Models\WeeklyBriefingContributor;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use App\Services\DivisionWeeklyBriefGate;
use App\Services\WeeklyBriefingCompletionSummary;
use App\Services\WeeklyBriefingDirectorateCombined;
use App\Services\WeeklyBriefingWindowService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class WeeklyBriefingController extends Controller
{
    public function index(Request $request): View
    {
        $this->assertDivisionWeeklyBriefModuleAccess();

        $listingKeys = DivisionWeeklyBriefGate::contributionKeysForReportListing();
        $filingKeys = DivisionWeeklyBriefGate::contributionKeysForFiling();

        $cy = Carbon::now()->isoWeekYear();
        $cw = Carbon::now()->isoWeek();

        $tab = (string) $request->query('tab', 'this_week');
        if ($tab !== 'all') {
            $tab = 'this_week';
        }

        $currentQuery = WeeklyBriefingReport::query()
            ->where('report_iso_week_year', $cy)
            ->where('report_iso_week', $cw)
            ->orderBy('contribution_key')
            ->with(['division', 'directorate', 'submittedBy', 'directorReviewedBy']);
        if ($listingKeys !== []) {
            $currentQuery->whereIn('contribution_key', $listingKeys);
        } else {
            $currentQuery->whereRaw('0 = 1');
        }
        $currentWeekReports = $currentQuery->get()->keyBy('contribution_key');

        $weekRowsBase = collect($listingKeys)->map(function (string $k) use ($currentWeekReports) {
            return [
                'key' => $k,
                'report' => $currentWeekReports->get($k),
            ];
        })->values();

        $twStatus = (string) $request->query('tw_status', '');
        $allowedTwStatuses = ['', 'draft', 'submitted', 'locked', 'not_started'];
        if (! in_array($twStatus, $allowedTwStatuses, true)) {
            $twStatus = '';
        }
        $twSearch = trim((string) $request->query('tw_search', ''));

        $filteredThisWeek = $weekRowsBase->filter(function (array $row) use ($twStatus, $twSearch) {
            $k = $row['key'];
            $r = $row['report'];
            if ($twSearch !== '') {
                $label = strtolower(WeeklyBriefingContributor::presentationLabelForContributionKey($k));
                if (! str_contains($label, strtolower($twSearch))) {
                    return false;
                }
            }
            if ($twStatus === '') {
                return true;
            }
            if ($twStatus === 'not_started') {
                return $r === null;
            }

            return $r && (string) $r->status === $twStatus;
        })->values();

        $twPerPage = 15;
        $twPage = max(1, (int) $request->query('tw_page', 1));
        $twTotal = $filteredThisWeek->count();
        $twItems = $filteredThisWeek->slice(($twPage - 1) * $twPerPage, $twPerPage)->values()->all();

        $thisWeekPaginator = new LengthAwarePaginator(
            $twItems,
            $twTotal,
            $twPerPage,
            $twPage,
            [
                'path' => $request->url(),
                'pageName' => 'tw_page',
            ]
        );
        $thisWeekPaginator->withQueryString();

        $filterYear = (int) $request->query('year', $cy);
        $filterWeekRaw = $request->query('week');
        $filterWeek = ($filterWeekRaw === null || $filterWeekRaw === '') ? null : (int) $filterWeekRaw;
        if ($filterWeek !== null && ($filterWeek < 1 || $filterWeek > 53)) {
            $filterWeek = null;
        }
        $filterStatus = (string) $request->query('status', '');
        $allowedStatuses = ['', WeeklyBriefingReport::STATUS_DRAFT, WeeklyBriefingReport::STATUS_SUBMITTED, WeeklyBriefingReport::STATUS_LOCKED];
        if (! in_array($filterStatus, $allowedStatuses, true)) {
            $filterStatus = '';
        }
        $filterSearch = trim((string) $request->query('search', ''));

        $reports = null;
        if ($tab === 'all') {
            $reportsQuery = WeeklyBriefingReport::query()
                ->where('report_iso_week_year', $filterYear)
                ->orderByDesc('report_iso_week')
                ->orderBy('contribution_key')
                ->with(['division', 'directorate']);
            if ($listingKeys !== []) {
                $reportsQuery->whereIn('contribution_key', $listingKeys);
            } else {
                $reportsQuery->whereRaw('0 = 1');
            }
            if ($filterWeek !== null) {
                $reportsQuery->where('report_iso_week', $filterWeek);
            }
            if ($filterStatus !== '') {
                $reportsQuery->where('status', $filterStatus);
            }
            if ($filterSearch !== '' && $listingKeys !== []) {
                $needle = strtolower($filterSearch);
                $matchingKeys = array_values(array_filter($listingKeys, function (string $k) use ($needle) {
                    return str_contains(strtolower(WeeklyBriefingContributor::presentationLabelForContributionKey($k)), $needle);
                }));
                if ($matchingKeys === []) {
                    $reportsQuery->whereRaw('0 = 1');
                } else {
                    $reportsQuery->whereIn('contribution_key', $matchingKeys);
                }
            }
            $reports = $reportsQuery->paginate(15)->withQueryString();
        }

        $filingKeySet = array_fill_keys($filingKeys, true);
        $startUrls = [];
        foreach ($filingKeys as $k) {
            if ($currentWeekReports->has($k)) {
                continue;
            }
            $startUrls[$k] = route('weekly-briefing.create', ['contribution_key' => $k]);
        }

        $filingWeekReports = $currentWeekReports->only($filingKeys)->values();

        $divisionId = $this->userPrimaryDivisionId();

        $wbNowY = $cy;
        $wbNowW = $cw;
        $wbDirectorCombinedOptions = WeeklyBriefingDirectorateCombined::directorCombinedDownloadOptionsForStaff(
            (int) user_session('staff_id'),
            $wbNowY,
            $wbNowW
        );

        $directorReviewKeySet = array_fill_keys(DivisionWeeklyBriefGate::directorManagedContributionKeysForListing(), true);

        $yearOptions = range($cy - 2, $cy + 1);
        $configuredUnitCount = count($listingKeys);

        return view('weekly-briefing.index', compact(
            'tab',
            'thisWeekPaginator',
            'twStatus',
            'twSearch',
            'reports',
            'filterYear',
            'filterWeek',
            'filterStatus',
            'filterSearch',
            'yearOptions',
            'configuredUnitCount',
            'startUrls',
            'filingKeySet',
            'filingWeekReports',
            'divisionId',
            'cy',
            'cw',
            'wbNowY',
            'wbNowW',
            'wbDirectorCombinedOptions',
            'directorReviewKeySet'
        ));
    }

    public function create(Request $request): RedirectResponse
    {
        $this->assertDivisionWeeklyBriefModuleAccess();

        $settings = WeeklyBriefingSetting::current();
        $staffId = (int) user_session('staff_id');

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
            ->first();
        if (! $contributorRow) {
            abort(403);
        }

        $apmDivisionId = (int) $contributorRow->apm_division_id;

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
        $this->assertCanViewReport($report);

        $report->load(['division', 'directorate', 'submittedBy', 'directorReviewedBy']);
        $settings = WeeklyBriefingSetting::current();
        $window = new WeeklyBriefingWindowService;

        $canContributorEdit = DivisionWeeklyBriefGate::mayEditReport($report) && $window->canEditReport($report);
        $canDirectorEdit = DivisionWeeklyBriefGate::mayEditAsDivisionDirector($report) && $window->canDirectorEditSubmittedReport($report);
        $canContributorSubmit = DivisionWeeklyBriefGate::mayEditReport($report) && $window->canSubmitReport($report);
        $canMarkDirectorReview = $window->canMarkDirectorReview($report);
        $formEditable = $canContributorEdit || $canDirectorEdit;
        $unlockOverrideActive = $settings->reportUnlockOverrideAppliesTo($report);

        return view('weekly-briefing.edit', compact(
            'report',
            'settings',
            'window',
            'canContributorEdit',
            'canDirectorEdit',
            'canContributorSubmit',
            'canMarkDirectorReview',
            'formEditable',
            'unlockOverrideActive'
        ));
    }

    public function update(Request $request, WeeklyBriefingReport $report): RedirectResponse
    {
        $this->assertDivisionWeeklyBriefModuleAccess();
        if (! DivisionWeeklyBriefGate::mayEditReport($report) && ! DivisionWeeklyBriefGate::mayEditAsDivisionDirector($report)) {
            abort(403);
        }

        $window = new WeeklyBriefingWindowService;
        $contribPath = DivisionWeeklyBriefGate::mayEditReport($report) && $window->canEditReport($report);
        $dirPath = DivisionWeeklyBriefGate::mayEditAsDivisionDirector($report) && $window->canDirectorEditSubmittedReport($report);
        if (! $contribPath && ! $dirPath) {
            return back()->with('error', 'This weekly briefing is no longer editable (deadline passed or locked).');
        }

        if (DivisionWeeklyBriefGate::mayEditAsDivisionDirector($report)
            && ! DivisionWeeklyBriefGate::mayEditReport($report)
            && $request->boolean('submit_final')) {
            return back()->with('error', 'Directors cannot submit here — contributors submit the briefing; use “Mark reviewed by director” when you have finished your review.');
        }

        $validated = $request->validate([
            'section1' => 'required|array|max:3',
            'section1.*.major_happening' => 'nullable|string|max:500',
            'section1.*.description_key_actions' => 'nullable|string',
            'section1.*.strategic_relevance' => 'nullable|string',
            'section2' => 'nullable|array',
            'section2.*.issue' => 'nullable|string|max:20000',
            'section2.*.impact_risk' => 'nullable|string|max:20000',
            'section2.*.required_action' => 'nullable|string|max:20000',
        ]);

        $section1 = [];
        foreach (array_slice($validated['section1'], 0, 3) as $row) {
            $m = trim((string) ($row['major_happening'] ?? ''));
            $d = trim((string) ($row['description_key_actions'] ?? ''));
            $s = trim((string) ($row['strategic_relevance'] ?? ''));
            $dPlain = trim(strip_tags($d));
            $sPlain = trim(strip_tags($s));
            if ($m === '' && $dPlain === '' && $sPlain === '') {
                continue;
            }
            $section1[] = [
                'major_happening' => $m,
                'description_key_actions' => $d,
                'strategic_relevance' => $s,
            ];
        }

        $hasCompleteHappening = false;
        foreach ($section1 as $row) {
            if (trim(strip_tags((string) ($row['description_key_actions'] ?? ''))) !== ''
                && trim(strip_tags((string) ($row['strategic_relevance'] ?? ''))) !== '') {
                $hasCompleteHappening = true;
                break;
            }
        }

        if (! $hasCompleteHappening) {
            return back()->withInput()->with('error', 'Add at least one major happening with description & key actions and strategic relevance completed.');
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

        $directorOnly = DivisionWeeklyBriefGate::mayEditAsDivisionDirector($report)
            && ! DivisionWeeklyBriefGate::mayEditReport($report);
        if ($directorOnly) {
            $report->appendDirectorReviewTrail('edited', (int) user_session('staff_id'));
        }

        if ($request->boolean('submit_final') && $window->canSubmitReport($report) && DivisionWeeklyBriefGate::mayEditReport($report)) {
            $report->status = WeeklyBriefingReport::STATUS_SUBMITTED;
            $report->submitted_at = now();
            $report->submitted_by_staff_id = (int) user_session('staff_id');
        }

        $report->save();

        $msg = $request->boolean('submit_final') && DivisionWeeklyBriefGate::mayEditReport($report)
            ? 'Weekly brief submitted.'
            : ($directorOnly ? 'Changes saved (director edit recorded on trail).' : 'Draft saved.');

        return redirect()->route('weekly-briefing.edit', $report)->with('status', $msg);
    }

    public function directorReview(WeeklyBriefingReport $report): RedirectResponse
    {
        $this->assertDivisionWeeklyBriefModuleAccess();
        $this->assertCanViewReport($report);
        if (! DivisionWeeklyBriefGate::mayMarkDirectorReview($report)) {
            abort(403);
        }
        $window = new WeeklyBriefingWindowService;
        if (! $window->canMarkDirectorReview($report)) {
            return back()->with('error', 'Director review cannot be recorded (deadline passed or briefing locked — use an administrative unlock in settings if appropriate).');
        }

        $report->director_reviewed_at = now();
        $report->director_reviewed_by_staff_id = (int) user_session('staff_id');
        $report->appendDirectorReviewTrail('reviewed', (int) user_session('staff_id'));
        $report->save();

        return redirect()->route('weekly-briefing.edit', $report)
            ->with('status', 'Recorded as reviewed by director.');
    }

    public function pdf(WeeklyBriefingReport $report)
    {
        $this->assertDivisionWeeklyBriefModuleAccess();
        $this->assertCanViewReport($report);

        $report->load(['division', 'directorate', 'submittedBy', 'directorReviewedBy']);
        $settings = WeeklyBriefingSetting::current();

        $pdf = mpdf_print('weekly-briefing.pdf-division', [
            'report' => $report,
            'settings' => $settings,
        ], ['orientation' => 'L']);

        $label = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $report->contributionEntityLabel());
        $fn = 'Weekly_Briefing_'.$label.'_W'.$report->report_iso_week.'_'.$report->report_iso_week_year.'.pdf';

        return response($pdf->Output($fn, 'I'), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function compiledPdf(int $year, int $week)
    {
        if (! DivisionWeeklyBriefGate::mayAccessCompiledBriefingExports()) {
            abort(403);
        }

        $reports = WeeklyBriefingReport::query()
            ->where('report_iso_week_year', $year)
            ->where('report_iso_week', $week)
            ->where('status', WeeklyBriefingReport::STATUS_SUBMITTED)
            ->with(['division', 'directorate', 'submittedBy', 'directorReviewedBy'])
            ->get();

        $reports = WeeklyBriefingCompletionSummary::sortReportsForCompiled($reports);

        $settings = WeeklyBriefingSetting::current();

        $pdf = mpdf_print('weekly-briefing.pdf-compiled', [
            'reports' => $reports,
            'settings' => $settings,
            'isoYear' => $year,
            'isoWeek' => $week,
        ], ['orientation' => 'L']);

        $fn = 'Weekly_Briefing_Compiled_W'.$week.'_'.$year.'.pdf';

        return response($pdf->Output($fn, 'I'), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function directorateCombinedPdf(int $year, int $week, int $directorate_id)
    {
        $this->assertDivisionWeeklyBriefModuleAccess();

        if (! DivisionWeeklyBriefGate::mayDownloadDirectorateCombinedPdf($year, $week, $directorate_id)) {
            abort(403);
        }

        $staffId = (int) user_session('staff_id');
        $reports = WeeklyBriefingDirectorateCombined::submittedReportsForDirectorDirectorate(
            $staffId,
            $directorate_id,
            $year,
            $week,
            null
        );

        if ($reports->isEmpty()) {
            abort(404);
        }

        $settings = WeeklyBriefingSetting::current();

        $dirLabel = $directorate_id > 0
            ? (trim((string) (Directorate::query()->find($directorate_id)?->name ?? '')) ?: 'Directorate #'.$directorate_id)
            : 'Directed divisions (no directorate)';
        $divisionCount = $reports->count();
        $metaHtml = 'ISO week <strong>W'.(int) $week.' / '.(int) $year.'</strong> · <strong>'.(int) $divisionCount.'</strong> submitted division briefing(s) where you are the director (divisions table) · <em>Not the organisation-wide compiled pack for central recipients.</em>';

        $pdf = mpdf_print('weekly-briefing.pdf-compiled', [
            'reports' => $reports,
            'settings' => $settings,
            'isoYear' => $year,
            'isoWeek' => $week,
            'compiledPdfHeading' => 'Weekly brief — director report (your divisions only)',
            'compiledPdfMetaHtml' => $metaHtml,
        ], ['orientation' => 'L']);

        $safeDir = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $dirLabel);
        $fn = 'Weekly_Briefing_Director_Divisions_'.$safeDir.'_W'.$week.'_'.$year.'.pdf';

        return response($pdf->Output($fn, 'I'), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function completionSummaryPdf(int $year, int $week)
    {
        if (! DivisionWeeklyBriefGate::mayAccessCompiledBriefingExports()) {
            abort(403);
        }

        $settings = WeeklyBriefingSetting::current();
        $rows = WeeklyBriefingCompletionSummary::rows($settings, $year, $week);

        $pdf = mpdf_print('weekly-briefing.pdf-completion-summary', [
            'rows' => $rows,
            'settings' => $settings,
            'isoYear' => $year,
            'isoWeek' => $week,
            'pdfScopeNote' => 'Full audit: all reporting units from weekly briefing settings.',
        ], ['orientation' => 'L']);

        $fn = 'Weekly_Briefing_Completion_Summary_FULL_AUDIT_W'.$week.'_'.$year.'.pdf';

        return response($pdf->Output($fn, 'I'), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function defaultSection1(): array
    {
        return [
            ['major_happening' => '', 'description_key_actions' => '', 'strategic_relevance' => ''],
            ['major_happening' => '', 'description_key_actions' => '', 'strategic_relevance' => ''],
            ['major_happening' => '', 'description_key_actions' => '', 'strategic_relevance' => ''],
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
            abort(403, 'You do not have access to Weekly brief.');
        }
    }

    private function assertCanViewReport(WeeklyBriefingReport $report): void
    {
        if (! DivisionWeeklyBriefGate::mayViewReport($report)) {
            abort(403);
        }
    }
}
