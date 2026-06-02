<?php

namespace App\Http\Controllers;

use App\Jobs\SendWeeklyBriefingDirectorReviewReminderJob;
use App\Models\Directorate;
use App\Models\Division;
use App\Models\Staff;
use App\Models\WeeklyBriefingContributor;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use App\Services\DivisionWeeklyBriefGate;
use App\Services\WeeklyBriefingContributionKeyResolver;
use App\Services\WeeklyBriefingCompletionSummary;
use App\Services\WeeklyBriefingDirectorateCombined;
use App\Services\WeeklyBriefingWindowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class WeeklyBriefingController extends Controller
{
    public function index(Request $request): View
    {
        $this->assertDivisionWeeklyBriefModuleAccess();

        $contributorRows = DivisionWeeklyBriefGate::contributorsForReportListing();
        $effectiveKeys = WeeklyBriefingContributionKeyResolver::effectiveKeysForContributors($contributorRows);
        $storedKeys = $contributorRows
            ->pluck('contribution_key')
            ->map(fn ($k) => trim((string) $k))
            ->filter(fn ($k) => $k !== '')
            ->values()
            ->all();
        $listingKeys = array_values(array_unique(array_merge($effectiveKeys, $storedKeys)));
        $directorateDisplayByContributionKey = $this->weeklyBriefDirectorateDisplayByContributionKeys($listingKeys);
        $filingKeys = DivisionWeeklyBriefGate::contributionKeysForFiling();

        $settings = WeeklyBriefingSetting::current();
        $filing = $settings->filingIsoWeekPair();
        $filingIsoYear = $filing['iso_year'];
        $filingIsoWeek = $filing['iso_week'];

        WeeklyBriefingContributionKeyResolver::migrateLegacyReportsByDivisionId();
        $filingWeekHumanRange = WeeklyBriefingReport::humanIsoWeekRange($filingIsoYear, $filingIsoWeek);
        $filingSubmissionDeadline = $settings->filingSubmissionDeadline();

        $tab = (string) $request->query('tab', 'this_week');
        if ($tab !== 'all') {
            $tab = 'this_week';
        }

        $contributorDivisionIds = $contributorRows
            ->map(fn (WeeklyBriefingContributor $c) => WeeklyBriefingContributionKeyResolver::divisionIdForContributor($c))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $currentQuery = WeeklyBriefingReport::query()
            ->where('report_iso_week_year', $filingIsoYear)
            ->where('report_iso_week', $filingIsoWeek)
            ->orderBy('contribution_key')
            ->with(['division', 'directorate', 'submittedBy', 'directorReviewedBy']);

        $hubCanViewAllReports = DivisionWeeklyBriefGate::mayViewAllConfiguredReportsOnHub();
        if (! $hubCanViewAllReports) {
            if ($listingKeys === [] && $contributorDivisionIds === []) {
                $currentQuery->whereRaw('0 = 1');
            } else {
                $currentQuery->where(function ($q) use ($listingKeys, $contributorDivisionIds) {
                    if ($listingKeys !== []) {
                        $q->whereIn('contribution_key', $listingKeys);
                    }
                    if ($contributorDivisionIds !== []) {
                        $method = $listingKeys !== [] ? 'orWhereIn' : 'whereIn';
                        $q->{$method}('division_id', $contributorDivisionIds);
                    }
                });
            }
        }

        $currentWeekReports = WeeklyBriefingContributionKeyResolver::reportsIndexedWithDivisionAliases(
            $currentQuery->get()
        );

        $sortedUniqueKeys = $this->sortContributionKeysForWeeklyBriefIndex($listingKeys);
        $keyRank = array_flip($sortedUniqueKeys);

        $weekRowsBase = $contributorRows->map(function (WeeklyBriefingContributor $c) use (
            $currentWeekReports,
            $directorateDisplayByContributionKey,
            $filingIsoYear,
            $filingIsoWeek,
        ) {
            $k = $c->effectiveContributionKey();
            $report = WeeklyBriefingContributionKeyResolver::resolveReportForContributor(
                $c,
                $currentWeekReports,
                $filingIsoYear,
                $filingIsoWeek,
            );

            return [
                'contributor' => $c,
                'key' => $k,
                'report' => $report,
                'label' => $c->hubLabel(),
                'directorate_display' => $directorateDisplayByContributionKey[$k] ?? [
                    'directorate_name' => '',
                    'director_name' => '',
                ],
            ];
        })->sortBy(function (array $row) use ($keyRank) {
            $k = $row['key'];
            $rank = $keyRank[$k] ?? 99999;
            $id = $row['contributor'] instanceof WeeklyBriefingContributor ? (int) $row['contributor']->id : 0;

            return [$rank, $id];
        })->values();

        $twStatus = (string) $request->query('tw_status', '');
        $allowedTwStatuses = ['', 'draft', 'submitted', 'locked', 'not_started'];
        if (! in_array($twStatus, $allowedTwStatuses, true)) {
            $twStatus = '';
        }
        $twSearch = trim((string) $request->query('tw_search', ''));

        $filteredThisWeek = $weekRowsBase->filter(function (array $row) use ($twStatus, $twSearch) {
            $r = $row['report'];
            if ($twSearch !== '') {
                $needle = mb_strtolower($twSearch);
                $labelHay = mb_strtolower((string) ($row['label'] ?? ''));
                $c = $row['contributor'] ?? null;
                $staffHay = '';
                if ($c instanceof WeeklyBriefingContributor) {
                    $st = $c->staff;
                    if ($st) {
                        $staffHay = mb_strtolower(trim((string) $st->name));
                    }
                }
                $dd = $row['directorate_display'] ?? null;
                $dirHay = '';
                if (is_array($dd)) {
                    $dirHay = mb_strtolower(trim(
                        trim((string) ($dd['directorate_name'] ?? '')).' '.trim((string) ($dd['director_name'] ?? ''))
                    ));
                }
                if (! str_contains($labelHay, $needle)
                    && ($staffHay === '' || ! str_contains($staffHay, $needle))
                    && ($dirHay === '' || ! str_contains($dirHay, $needle))) {
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

        $twPerPage = 20;
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

        $previousIso = now()->subWeek();
        $previousIsoYear = (int) $previousIso->isoWeekYear();
        $previousIsoWeek = (int) $previousIso->isoWeek();

        $filterYear = (int) $request->query('year', $filingIsoYear);
        $filterWeekRaw = $request->query('week');
        $filterWeek = ($filterWeekRaw === null || $filterWeekRaw === '') ? null : (int) $filterWeekRaw;
        if ($tab === 'all' && $filterWeek === null) {
            // Default extraction week for "All reports": previous ISO week.
            $filterWeek = $previousIsoWeek;
        }
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
                ->with(['division.directorate.director', 'directorate.director']);
            if (! $hubCanViewAllReports) {
                if ($listingKeys === [] && $contributorDivisionIds === []) {
                    $reportsQuery->whereRaw('0 = 1');
                } else {
                    $reportsQuery->where(function ($q) use ($listingKeys, $contributorDivisionIds) {
                        if ($listingKeys !== []) {
                            $q->whereIn('contribution_key', $listingKeys);
                        }
                        if ($contributorDivisionIds !== []) {
                            $method = $listingKeys !== [] ? 'orWhereIn' : 'whereIn';
                            $q->{$method}('division_id', $contributorDivisionIds);
                        }
                    });
                }
            }
            if ($filterWeek !== null) {
                $reportsQuery->where('report_iso_week', $filterWeek);
            }
            if ($filterStatus !== '') {
                $reportsQuery->where('status', $filterStatus);
            }
            if ($filterSearch !== '' && ($hubCanViewAllReports || $listingKeys !== [])) {
                $needle = mb_strtolower($filterSearch);
                $matchingKeysSet = [];
                foreach ($settings->contributors()->with('staff')->get() as $contrib) {
                    $k = $contrib->effectiveContributionKey();
                    if ($k === '' || (! $hubCanViewAllReports && ! in_array($k, $listingKeys, true))) {
                        continue;
                    }
                    $st = $contrib->staff;
                    $staffHay = $st ? mb_strtolower(trim((string) $st->name)) : '';
                    $dd = $directorateDisplayByContributionKey[$k] ?? [
                        'directorate_name' => '',
                        'director_name' => '',
                    ];
                    $dirHay = mb_strtolower(trim(
                        trim((string) ($dd['directorate_name'] ?? '')).' '.trim((string) ($dd['director_name'] ?? ''))
                    ));
                    if (str_contains(mb_strtolower($contrib->hubLabel()), $needle)
                        || str_contains(mb_strtolower(WeeklyBriefingContributor::presentationLabelForContributionKey($k)), $needle)
                        || ($staffHay !== '' && str_contains($staffHay, $needle))
                        || ($dirHay !== '' && str_contains($dirHay, $needle))) {
                        $matchingKeysSet[$k] = true;
                    }
                }
                $matchingKeys = array_keys($matchingKeysSet);
                if ($matchingKeys === []) {
                    $reportsQuery->whereRaw('0 = 1');
                } else {
                    $reportsQuery->whereIn('contribution_key', $matchingKeys);
                }
            }
            $reports = $reportsQuery->paginate(20)->withQueryString();
        }

        $filingKeySet = array_fill_keys($filingKeys, true);
        $startUrls = [];
        $filingWeekReports = collect();
        foreach ($filingKeys as $k) {
            $matched = $currentWeekReports->get($k);
            if ($matched instanceof WeeklyBriefingReport) {
                $filingWeekReports->put((int) $matched->id, $matched);

                continue;
            }
            $startUrls[$k] = route('weekly-briefing.create', [
                'contribution_key' => $k,
                'iso_year' => $filingIsoYear,
                'iso_week' => $filingIsoWeek,
            ]);
        }
        $filingWeekReports = $filingWeekReports->values();

        $divisionId = $this->userPrimaryDivisionId();

        $wbNowY = $tab === 'all' ? $filterYear : $filingIsoYear;
        $wbNowW = $tab === 'all' ? ($filterWeek ?? $previousIsoWeek) : $filingIsoWeek;
        $wbDirectorCombinedOptions = WeeklyBriefingDirectorateCombined::directorCombinedDownloadOptionsForStaff(
            (int) user_session('staff_id'),
            $wbNowY,
            $wbNowW
        );

        $directorReviewKeySet = array_fill_keys(DivisionWeeklyBriefGate::directorManagedContributionKeysForListing(), true);
        $hubShowsDirectorateOversight = DivisionWeeklyBriefGate::isDirectorateDirector()
            && ! $hubCanViewAllReports;

        $yearOptions = range($filingIsoYear - 2, $filingIsoYear + 1);
        $configuredUnitCount = $contributorRows->count();

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
            'filingIsoYear',
            'filingIsoWeek',
            'filingWeekHumanRange',
            'filingSubmissionDeadline',
            'wbNowY',
            'wbNowW',
            'wbDirectorCombinedOptions',
            'directorReviewKeySet',
            'hubShowsDirectorateOversight',
            'hubCanViewAllReports'
        ));
    }

    public function create(Request $request): RedirectResponse
    {
        $this->assertDivisionWeeklyBriefModuleAccess();

        $settings = WeeklyBriefingSetting::current();
        $contributionKey = (string) $request->query('contribution_key', '');

        if ($contributionKey === '') {
            return redirect()->route('weekly-briefing.index')->with('error', 'Choose a reporting unit to start.');
        }

        if (! DivisionWeeklyBriefGate::mayUseContributionKey($contributionKey)) {
            abort(403);
        }

        $contributorRow = DivisionWeeklyBriefGate::contributorRowForSessionFiling($contributionKey);
        if (! $contributorRow) {
            abort(403);
        }

        $storageKey = $contributorRow->effectiveContributionKey();
        if (! str_starts_with($storageKey, 'd-')) {
            abort(403, 'This reporting unit must use a division-scoped brief.');
        }

        $defaults = $settings->filingIsoWeekPair();
        $isoYear = (int) $request->get('iso_year', $defaults['iso_year']);
        $isoWeek = (int) $request->get('iso_week', $defaults['iso_week']);
        $periodStart = WeeklyBriefingReport::periodMonday($isoYear, $isoWeek);

        $divId = (int) substr($storageKey, 2);
        $division = Division::query()->findOrFail($divId);

        $existing = WeeklyBriefingReport::query()
            ->where('report_iso_week_year', $isoYear)
            ->where('report_iso_week', $isoWeek)
            ->where('division_id', $division->id)
            ->first();

        if ($existing) {
            if ((string) $existing->contribution_key !== $storageKey) {
                $existing->update([
                    'contribution_key' => $storageKey,
                    'directorate_id' => $division->directorate_id,
                ]);
            }

            return redirect()->route('weekly-briefing.edit', $existing);
        }

        $report = WeeklyBriefingReport::query()->firstOrCreate(
            [
                'contribution_key' => $storageKey,
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
        $filingAsAdminAssistant = DivisionWeeklyBriefGate::mayEditReportAsAdminAssistant($report);
        $filedOnBehalfBy = null;
        $filedOnBehalfStaffId = $report->submissionFiledOnBehalfByStaffId();
        if ($filedOnBehalfStaffId) {
            $filedOnBehalfBy = Staff::query()->find($filedOnBehalfStaffId);
        }

        return view('weekly-briefing.edit', compact(
            'report',
            'settings',
            'window',
            'canContributorEdit',
            'canDirectorEdit',
            'canContributorSubmit',
            'canMarkDirectorReview',
            'formEditable',
            'unlockOverrideActive',
            'filingAsAdminAssistant',
            'filedOnBehalfBy'
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
            'section1.*.major_happening' => 'nullable|string|max:15000',
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
            $mPlain = trim(strip_tags($m));
            $dPlain = trim(strip_tags($d));
            $sPlain = trim(strip_tags($s));
            if ($mPlain === '' && $dPlain === '' && $sPlain === '') {
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
            if (trim(strip_tags($issue)) === '' && trim(strip_tags($impact)) === '' && trim(strip_tags($action)) === '') {
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

        $contributorSubmitted = $request->boolean('submit_final')
            && $window->canSubmitReport($report)
            && DivisionWeeklyBriefGate::mayEditReport($report);

        if ($contributorSubmitted) {
            $report->status = WeeklyBriefingReport::STATUS_SUBMITTED;
            $report->submitted_at = now();
            $attributionId = DivisionWeeklyBriefGate::submissionAttributionStaffId($report);
            $filerId = DivisionWeeklyBriefGate::sessionStaffId();
            $report->submitted_by_staff_id = $attributionId > 0 ? $attributionId : $filerId;
            if ($filerId > 0 && $attributionId > 0 && $filerId !== $attributionId
                && DivisionWeeklyBriefGate::mayEditReportAsAdminAssistant($report)) {
                $report->appendSubmissionFiledOnBehalfTrail($filerId, $attributionId);
            }
        }

        $report->save();

        if ($contributorSubmitted) {
            SendWeeklyBriefingDirectorReviewReminderJob::dispatch($report->id);
        }

        $msg = $contributorSubmitted
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
        $reports = WeeklyBriefingReport::filterForOrganisationCompiledExport($reports, $settings);

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
        $metaHtml = htmlspecialchars(
            WeeklyBriefingReport::humanIsoWeekRange($year, $week),
            ENT_QUOTES,
            'UTF-8'
        ).' · <strong>'.(int) $divisionCount.'</strong> submitted division briefing(s).';

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

    /**
     * @param  list<string>  $keys
     * @return array<string, array{directorate_name: string, director_name: string}>
     */
    private function weeklyBriefDirectorateDisplayByContributionKeys(array $keys): array
    {
        $divIds = [];
        $drIds = [];
        foreach ($keys as $raw) {
            $k = trim((string) $raw);
            if (str_starts_with($k, 'd-')) {
                $id = (int) substr($k, 2);
                if ($id > 0) {
                    $divIds[] = $id;
                }
            } elseif (str_starts_with($k, 'dr-')) {
                $id = (int) substr($k, 3);
                if ($id > 0) {
                    $drIds[] = $id;
                }
            }
        }
        $divIds = array_values(array_unique($divIds));
        $drIds = array_values(array_unique($drIds));

        $out = [];

        $divisions = $divIds === []
            ? collect()
            : Division::query()->whereIn('id', $divIds)->with(['directorate.director'])->get()->keyBy('id');
        foreach ($divIds as $id) {
            $div = $divisions->get($id);
            $out['d-'.$id] = $this->weeklyBriefDirectorateDisplayTuple($div?->directorate);
        }

        $directorates = $drIds === []
            ? collect()
            : Directorate::query()->whereIn('id', $drIds)->with(['director'])->get()->keyBy('id');
        foreach ($drIds as $id) {
            $dir = $directorates->get($id);
            $out['dr-'.$id] = $this->weeklyBriefDirectorateDisplayTuple($dir);
        }

        return $out;
    }

    /**
     * @return array{directorate_name: string, director_name: string}
     */
    private function weeklyBriefDirectorateDisplayTuple(?Directorate $directorate): array
    {
        if (! $directorate) {
            return ['directorate_name' => '', 'director_name' => ''];
        }
        $directorate->loadMissing('director');

        return [
            'directorate_name' => trim((string) ($directorate->name ?? '')),
            'director_name' => $directorate->director ? trim((string) $directorate->director->name) : '',
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function sortContributionKeysForWeeklyBriefIndex(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $divIds = [];
        foreach ($keys as $k) {
            if (str_starts_with((string) $k, 'd-')) {
                $divIds[] = (int) substr((string) $k, 2);
            }
        }
        $divIds = array_values(array_unique(array_filter($divIds)));
        $nameById = $divIds === []
            ? []
            : Division::query()->whereIn('id', $divIds)->pluck('division_name', 'id')->all();

        usort($keys, function (string $a, string $b) use ($nameById): int {
            $tuple = function (string $k) use ($nameById): string {
                if (str_starts_with($k, 'd-')) {
                    $id = (int) substr($k, 2);
                    $name = mb_strtolower((string) ($nameById[$id] ?? ''));

                    return 'd:'.$name."\0".str_pad((string) $id, 10, '0', STR_PAD_LEFT);
                }
                if (str_starts_with($k, 'dr-')) {
                    return 'dr:'.mb_strtolower(WeeklyBriefingContributor::presentationLabelForContributionKey($k));
                }

                return 'z:'.$k;
            };

            return strcmp($tuple($a), $tuple($b));
        });

        return $keys;
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
