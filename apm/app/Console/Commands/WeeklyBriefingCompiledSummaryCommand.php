<?php

namespace App\Console\Commands;

use App\Models\Directorate;
use App\Models\Division;
use App\Models\Staff;
use App\Models\WeeklyBriefingContributor;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use App\Services\WeeklyBriefingCompletionSummary;
use App\Services\WeeklyBriefingDirectorateCombined;
use App\Services\WeeklyBriefingNotificationMailer;
use App\Services\WeeklyBriefingScheduleGate;
use App\Support\WeeklyBriefingMailTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WeeklyBriefingCompiledSummaryCommand extends Command
{
    protected $signature = 'weekly-briefing:compiled-summary {--force : Run immediately, ignoring reminders_enabled, deadline calendar-day gate, and summary_send_time}';

    protected $description = 'Email organisation-wide compiled Weekly brief only to central recipients; optionally email HoDs per division and directors a separate division-only director report plus scoped completion summary.';

    public function handle(): int
    {
        $settings = WeeklyBriefingSetting::current();
        $force = (bool) $this->option('force');
        $gate = WeeklyBriefingScheduleGate::for($settings);
        $filing = $settings->filingIsoWeekPair();
        $y = $filing['iso_year'];
        $w = $filing['iso_week'];
        $filingDeadline = $settings->filingSubmissionDeadline();

        if (! $gate->passesCompiledSummarySchedule($force)) {
            return self::SUCCESS;
        }

        $compiledSlotKey = $gate->compiledSummarySlotKey();
        if (! $force && ! $gate->tryClaimDispatch('compiled', $compiledSlotKey)) {
            return self::SUCCESS;
        }

        $compiledDispatched = false;
        $weekHuman = htmlspecialchars(WeeklyBriefingReport::humanIsoWeekRange($y, $w), ENT_QUOTES, 'UTF-8');

        $reportsAllSubmitted = WeeklyBriefingReport::query()
            ->where('report_iso_week_year', $y)
            ->where('report_iso_week', $w)
            ->where('status', WeeklyBriefingReport::STATUS_SUBMITTED)
            ->with(['division', 'directorate', 'submittedBy', 'directorReviewedBy'])
            ->get();

        $reportsAllSubmitted = WeeklyBriefingCompletionSummary::sortReportsForCompiled($reportsAllSubmitted);
        $reportsForCentral = WeeklyBriefingReport::filterForOrganisationCompiledExport($reportsAllSubmitted, $settings);
        $centralExcludedCount = $reportsAllSubmitted->count() - $reportsForCentral->count();

        $recipients = collect(explode(',', (string) $settings->compiled_recipient_emails))
            ->map(fn ($e) => trim((string) $e))
            ->filter(fn ($e) => $e !== '' && Str::contains($e, '@'))
            ->values()
            ->all();

        $sendCompiled = $recipients !== [];
        $sendDivisionLeaderPdfs = (bool) $settings->cc_division_hod_on_compiled;

        if (! $sendCompiled && ! $sendDivisionLeaderPdfs) {
            Log::info('weekly-briefing:compiled-summary — no compiled recipients and division PDF mail disabled');
            if (! $force) {
                $gate->releaseDispatch('compiled', $compiledSlotKey);
            }

            return self::SUCCESS;
        }

        if ($reportsAllSubmitted->isEmpty()) {
            Log::info('weekly-briefing:compiled-summary — no submitted reports');
            if (! $force) {
                $gate->releaseDispatch('compiled', $compiledSlotKey);
            }

            return self::SUCCESS;
        }

        if ($sendCompiled) {
            $graphAttachments = [];
            if ($reportsForCentral->isNotEmpty()) {
                $pdf = mpdf_print('weekly-briefing.pdf-compiled', [
                    'reports' => $reportsForCentral,
                    'settings' => $settings,
                    'isoYear' => $y,
                    'isoWeek' => $w,
                ], ['orientation' => 'L']);
                $compiledBinary = $pdf->Output('', 'S');
                $compiledFilename = 'Weekly_Briefing_Compiled_W'.$w.'_'.$y.'.pdf';
                $graphAttachments[] = ['name' => $compiledFilename, 'content' => $compiledBinary, 'content_type' => 'application/pdf'];
            }

            $completionRows = WeeklyBriefingCompletionSummary::rows($settings, $y, $w);
            $summaryPdf = mpdf_print('weekly-briefing.pdf-completion-summary', [
                'rows' => $completionRows,
                'settings' => $settings,
                'isoYear' => $y,
                'isoWeek' => $w,
                'pdfScopeNote' => 'Full audit: all reporting units from weekly briefing settings.',
            ], ['orientation' => 'L']);
            $summaryBinary = $summaryPdf->Output('', 'S');
            $summaryFilename = 'Weekly_Briefing_Completion_Summary_FULL_AUDIT_W'.$w.'_'.$y.'.pdf';
            $graphAttachments[] = ['name' => $summaryFilename, 'content' => $summaryBinary, 'content_type' => 'application/pdf'];

            $subject = "Weekly brief compiled — W{$w}/{$y}".WeeklyBriefingMailTemplate::subjectSuffix();
            $omitPdfNote = ($centralExcludedCount > 0 && $reportsForCentral->isNotEmpty())
                ? '<p style="color:#92400e;"><strong>Note:</strong> '.$centralExcludedCount.' submitted division brief(s) that require director review were omitted from the compiled PDF because review was not recorded before the deadline (per weekly briefing settings).</p>'
                : '';
            $emptyPdfNote = $reportsForCentral->isEmpty()
                ? '<p style="color:#92400e;"><strong>Note:</strong> No division briefs were included in the compiled PDF this week (submitted briefs that require director review are still pending review, per settings).</p>'
                : '';
            $innerCentral = <<<HTML
<p>We write to inform you that the compiled <strong>Weekly brief</strong> materials for the reporting week below are ready for your attention.</p>
<p><strong>{$weekHuman}</strong></p>
{$omitPdfNote}{$emptyPdfNote}
<p>Attached, for audit and organisational oversight, you will find:</p>
<ul style="color:#444444;font-size:14px;line-height:1.6;">
<li>The <strong>compiled PDF</strong> (when present) containing submitted reporting units included in the organisation-wide pack; and</li>
<li>The <strong>organisational completion summary</strong>, listing every configured reporting unit in the weekly brief settings.</li>
</ul>
<p>Should you require any clarification, please contact your APM administrator or the relevant focal point.</p>
HTML;
            foreach ($recipients as $addr) {
                if (WeeklyBriefingNotificationMailer::sendToAddress($addr, $subject, 'Weekly brief — compiled package', $innerCentral, 'weekly_briefing_compiled', $graphAttachments)) {
                    $compiledDispatched = true;
                }
            }
        }

        if ($sendDivisionLeaderPdfs) {
            $reportsByKey = $reportsAllSubmitted->keyBy(fn (WeeklyBriefingReport $r) => (string) $r->contribution_key);
            $divisions = Division::query()->orderBy('id')->get();

            $staffIds = [];
            foreach ($divisions as $division) {
                $key = WeeklyBriefingContributor::contributionKeyForDivision((int) $division->id);
                if (! $reportsByKey->has($key)) {
                    continue;
                }
                if ($division->division_head) {
                    $staffIds[] = (int) $division->division_head;
                }
            }
            $staffIds = array_values(array_unique($staffIds));
            $hodStaffById = $staffIds === []
                ? collect()
                : Staff::query()
                    ->whereIn('staff_id', $staffIds)
                    ->get()
                    ->keyBy('staff_id');
            $workEmailByStaffId = $hodStaffById->pluck('work_email', 'staff_id')->all();

            foreach ($divisions as $division) {
                $key = WeeklyBriefingContributor::contributionKeyForDivision((int) $division->id);
                $report = $reportsByKey->get($key);
                if (! $report) {
                    continue;
                }

                $leaderAddresses = [];
                if ($division->division_head) {
                    $raw = $workEmailByStaffId[(int) $division->division_head] ?? null;
                    $raw = is_string($raw) ? trim($raw) : '';
                    if ($raw !== '' && Str::contains($raw, '@')) {
                        $leaderAddresses[strtolower($raw)] = $raw;
                    }
                }

                if ($leaderAddresses === []) {
                    continue;
                }

                $one = mpdf_print('weekly-briefing.pdf-division', [
                    'report' => $report,
                    'settings' => $settings,
                ], ['orientation' => 'L']);
                $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $report->contributionEntityLabel());
                $divisionFilename = 'Weekly_Briefing_'.$safe.'_W'.$w.'_'.$y.'.pdf';
                $divisionBinary = $one->Output('', 'S');
                $divisionAttachments = [
                    ['name' => $divisionFilename, 'content' => $divisionBinary, 'content_type' => 'application/pdf'],
                ];

                $divLabel = trim((string) ($division->division_name ?? ''));
                if ($divLabel === '') {
                    $divLabel = 'Division '.$division->id;
                }
                $divLabelEsc = htmlspecialchars($divLabel, ENT_QUOTES, 'UTF-8');
                $hodStaff = $hodStaffById->get((int) $division->division_head);
                $innerDiv = '<p>Please find attached the submitted <strong>Weekly brief</strong> for <strong>'.$divLabelEsc.'</strong>. Reporting week: <strong>'.$weekHuman.'</strong></p>';
                $divSubject = 'Weekly brief — '.$divLabel.' — W'.$w.'/'.$y.WeeklyBriefingMailTemplate::subjectSuffix();

                foreach ($leaderAddresses as $addr) {
                    $sent = $hodStaff instanceof Staff
                            ? WeeklyBriefingNotificationMailer::sendToStaff($hodStaff, $divSubject, 'Weekly brief — division submission', $innerDiv, 'weekly_briefing_division_pdf', null, $divisionAttachments)
                            : WeeklyBriefingNotificationMailer::sendToAddress($addr, $divSubject, 'Weekly brief — division submission', $innerDiv, 'weekly_briefing_division_pdf', $divisionAttachments);
                        if ($sent) {
                            $compiledDispatched = true;
                        }
                }
            }

            foreach (WeeklyBriefingDirectorateCombined::directorCombinedMailGroups($reportsAllSubmitted, $y, $w) as $group) {
                $directorId = $group['director_id'];
                $dirTorateId = $group['directorate_id'];
                $groupReports = $group['reports'];
                $directorStaff = Staff::query()->find($directorId);
                $raw = $directorStaff?->work_email;
                $raw = is_string($raw) ? trim($raw) : '';
                if ($raw === '' || ! Str::contains($raw, '@')) {
                    continue;
                }

                $dirLabel = $dirTorateId > 0
                    ? (trim((string) (Directorate::query()->find($dirTorateId)?->name ?? '')) ?: 'Directorate #'.$dirTorateId)
                    : 'Directed divisions (no directorate)';
                $safeDir = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $dirLabel);
                $divisionCount = $groupReports->count();
                $metaHtml = htmlspecialchars(WeeklyBriefingReport::humanIsoWeekRange($y, $w), ENT_QUOTES, 'UTF-8')
                    .' · <strong>'.(int) $divisionCount.'</strong> submitted briefing(s) in this directorate scope.';

                $compiled = mpdf_print('weekly-briefing.pdf-compiled', [
                    'reports' => $groupReports,
                    'settings' => $settings,
                    'isoYear' => $y,
                    'isoWeek' => $w,
                    'compiledPdfHeading' => 'Weekly brief — director report (your directorate scope)',
                    'compiledPdfMetaHtml' => $metaHtml,
                ], ['orientation' => 'L']);
                $combinedFilename = 'Weekly_Briefing_Director_Directorate_'.$safeDir.'_W'.$w.'_'.$y.'.pdf';
                $combinedBinary = $compiled->Output('', 'S');
                $attachments = [
                    ['name' => $combinedFilename, 'content' => $combinedBinary, 'content_type' => 'application/pdf'],
                ];

                $scopeKeys = WeeklyBriefingDirectorateCombined::contributionKeysForDirectorDirectorateScope(
                    $directorId,
                    $dirTorateId,
                    $settings
                );
                $scopeRows = WeeklyBriefingCompletionSummary::rowsForContributionKeys($settings, $y, $w, $scopeKeys);
                if ($scopeRows !== []) {
                    $scopeNote = 'Director view: completion status for reporting units in this directorate scope (from settings), including directorate-level (dr-) and division (d-) keys under the same directorate.';
                    $scopeSummaryPdf = mpdf_print('weekly-briefing.pdf-completion-summary', [
                        'rows' => $scopeRows,
                        'settings' => $settings,
                        'isoYear' => $y,
                        'isoWeek' => $w,
                        'pdfScopeNote' => $scopeNote,
                    ], ['orientation' => 'L']);
                    $scopeSummaryFilename = 'Weekly_Briefing_Completion_Summary_Director_Directorate_'.$safeDir.'_W'.$w.'_'.$y.'.pdf';
                    $attachments[] = [
                        'name' => $scopeSummaryFilename,
                        'content' => $scopeSummaryPdf->Output('', 'S'),
                        'content_type' => 'application/pdf',
                    ];
                }

                $dirLabelEsc = htmlspecialchars($dirLabel, ENT_QUOTES, 'UTF-8');
                $combinedInner = '<p>Please find attached (1) the <strong>Director report</strong>, comprising submitted <strong>Weekly brief</strong> returns for your directorate (as the director assigned on the <strong>directorates</strong> table). Reporting week: <strong>'.$weekHuman.'</strong> This package is <strong>not</strong> the organisation-wide compiled document sent to central recipients. (2) A completion summary for the same directorate scope.</p>';
                $combinedSubject = 'Weekly brief — director report — '.$dirLabel.' — W'.$w.'/'.$y.WeeklyBriefingMailTemplate::subjectSuffix();
                if ($directorStaff && WeeklyBriefingNotificationMailer::sendToStaff($directorStaff, $combinedSubject, 'Weekly brief — director report', $combinedInner, 'weekly_briefing_director_compiled', null, $attachments)) {
                    $compiledDispatched = true;
                }
            }
        }

        if (! $compiledDispatched && ! $force) {
            $gate->releaseDispatch('compiled', $compiledSlotKey);
        }

        $this->info('weekly-briefing:compiled-summary dispatched.');

        return self::SUCCESS;
    }
}
