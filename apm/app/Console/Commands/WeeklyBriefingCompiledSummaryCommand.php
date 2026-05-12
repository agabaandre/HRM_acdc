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
use App\Support\WeeklyBriefingMailTemplate;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WeeklyBriefingCompiledSummaryCommand extends Command
{
    protected $signature = 'weekly-briefing:compiled-summary {--force : Run immediately, ignoring reminders_enabled, weekday, and summary_send_time}';

    protected $description = 'Email organisation-wide compiled Weekly brief only to central recipients; optionally email HoDs per division and directors a separate division-only director report plus scoped completion summary.';

    public function handle(): int
    {
        $settings = WeeklyBriefingSetting::current();
        $force = (bool) $this->option('force');
        if (! $force) {
            if (! $settings->reminders_enabled) {
                return self::SUCCESS;
            }
            if ((int) Carbon::now()->dayOfWeek !== (int) $settings->submission_weekday) {
                return self::SUCCESS;
            }
            if (! $settings->matchesTimeNow('summary_send_time')) {
                return self::SUCCESS;
            }
        }

        $y = Carbon::now()->isoWeekYear();
        $w = Carbon::now()->isoWeek();

        $reports = WeeklyBriefingReport::query()
            ->where('report_iso_week_year', $y)
            ->where('report_iso_week', $w)
            ->where('status', WeeklyBriefingReport::STATUS_SUBMITTED)
            ->with(['division', 'directorate', 'submittedBy', 'directorReviewedBy'])
            ->get();

        $reports = WeeklyBriefingCompletionSummary::sortReportsForCompiled($reports);

        $recipients = collect(explode(',', (string) $settings->compiled_recipient_emails))
            ->map(fn ($e) => trim((string) $e))
            ->filter(fn ($e) => $e !== '' && Str::contains($e, '@'))
            ->values()
            ->all();

        $sendCompiled = $recipients !== [];
        $sendDivisionLeaderPdfs = (bool) $settings->cc_division_hod_on_compiled;

        if (! $sendCompiled && ! $sendDivisionLeaderPdfs) {
            Log::info('weekly-briefing:compiled-summary — no compiled recipients and division PDF mail disabled');

            return self::SUCCESS;
        }

        if ($reports->isEmpty()) {
            Log::info('weekly-briefing:compiled-summary — no submitted reports');

            return self::SUCCESS;
        }

        $subjectPrefix = env('MAIL_SUBJECT_PREFIX', 'APM').': ';

        if ($sendCompiled) {
            $pdf = mpdf_print('weekly-briefing.pdf-compiled', [
                'reports' => $reports,
                'settings' => $settings,
                'isoYear' => $y,
                'isoWeek' => $w,
            ], ['orientation' => 'L']);

            $compiledBinary = $pdf->Output('', 'S');
            $compiledFilename = 'Weekly_Briefing_Compiled_W'.$w.'_'.$y.'.pdf';

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

            $subject = $subjectPrefix."Weekly brief compiled — W{$w}/{$y}".WeeklyBriefingMailTemplate::subjectSuffix();
            $innerCentral = <<<HTML
<p>We write to inform you that the compiled <strong>Weekly brief</strong> materials for ISO week <strong>W{$w} / {$y}</strong> are ready for your attention.</p>
<p>Attached, for audit and organisational oversight, you will find:</p>
<ul style="color:#444444;font-size:14px;line-height:1.6;">
<li>The <strong>compiled PDF</strong> containing all submitted reporting units for this week; and</li>
<li>The <strong>organisational completion summary</strong>, listing every configured reporting unit in the weekly brief settings.</li>
</ul>
<p>Should you require any clarification, please contact your APM administrator or the relevant focal point.</p>
HTML;
            $body = WeeklyBriefingMailTemplate::wrap(null, 'Weekly brief — compiled package', $innerCentral);
            $graphAttachments = [
                ['name' => $compiledFilename, 'content' => $compiledBinary, 'content_type' => 'application/pdf'],
                ['name' => $summaryFilename, 'content' => $summaryBinary, 'content_type' => 'application/pdf'],
            ];

            try {
                if (! sendEmail($recipients, $subject, $body, null, null, [], [], $graphAttachments)) {
                    Log::warning('weekly-briefing:compiled-summary sendEmail returned false');
                }
            } catch (\Throwable $e) {
                Log::warning('weekly-briefing:compiled-summary mail failed', ['e' => $e->getMessage()]);
            }
        }

        if ($sendDivisionLeaderPdfs) {
            $reportsByKey = $reports->keyBy(fn (WeeklyBriefingReport $r) => (string) $r->contribution_key);
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
                $innerDiv = '<p>Please find attached the submitted <strong>Weekly brief</strong> for <strong>'.$divLabelEsc.'</strong> (ISO week <strong>W'.$w.' / '.$y.'</strong>), for your records and onward distribution as appropriate.</p>';
                $divBody = WeeklyBriefingMailTemplate::wrap($hodStaff instanceof Staff ? $hodStaff : null, 'Weekly brief — division submission', $innerDiv);
                $divSubject = $subjectPrefix.'Weekly brief — '.$divLabel.' — W'.$w.'/'.$y.WeeklyBriefingMailTemplate::subjectSuffix();

                foreach ($leaderAddresses as $addr) {
                    try {
                        if (! sendEmail($addr, $divSubject, $divBody, null, null, [], [], $divisionAttachments)) {
                            Log::warning('weekly-briefing:compiled-summary division sendEmail returned false', ['to' => $addr, 'division_id' => $division->id]);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('weekly-briefing:compiled-summary division mail failed', ['e' => $e->getMessage(), 'to' => $addr, 'division_id' => $division->id]);
                    }
                }
            }

            foreach (WeeklyBriefingDirectorateCombined::directorCombinedMailGroups($reports, $y, $w) as $group) {
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
                $metaHtml = 'ISO week <strong>W'.(int) $w.' / '.(int) $y.'</strong> · <strong>'.(int) $divisionCount.'</strong> submitted division briefing(s) where you are the director (divisions table) · <em>Not the organisation-wide compiled pack sent to central recipients.</em>';

                $compiled = mpdf_print('weekly-briefing.pdf-compiled', [
                    'reports' => $groupReports,
                    'settings' => $settings,
                    'isoYear' => $y,
                    'isoWeek' => $w,
                    'compiledPdfHeading' => 'Weekly brief — director report (your divisions only)',
                    'compiledPdfMetaHtml' => $metaHtml,
                ], ['orientation' => 'L']);
                $combinedFilename = 'Weekly_Briefing_Director_Divisions_'.$safeDir.'_W'.$w.'_'.$y.'.pdf';
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
                    $scopeNote = 'Director view: completion status for division reporting units you direct in this directorate (from settings). Directorate-only (dr-) rows are not included here.';
                    $scopeSummaryPdf = mpdf_print('weekly-briefing.pdf-completion-summary', [
                        'rows' => $scopeRows,
                        'settings' => $settings,
                        'isoYear' => $y,
                        'isoWeek' => $w,
                        'pdfScopeNote' => $scopeNote,
                    ], ['orientation' => 'L']);
                    $scopeSummaryFilename = 'Weekly_Briefing_Completion_Summary_Director_Divisions_'.$safeDir.'_W'.$w.'_'.$y.'.pdf';
                    $attachments[] = [
                        'name' => $scopeSummaryFilename,
                        'content' => $scopeSummaryPdf->Output('', 'S'),
                        'content_type' => 'application/pdf',
                    ];
                }

                $dirLabelEsc = htmlspecialchars($dirLabel, ENT_QUOTES, 'UTF-8');
                $combinedInner = '<p>Please find attached (1) the <strong>Director report</strong>, comprising submitted <strong>Weekly brief</strong> returns for divisions for which you are recorded as director in the system (ISO week <strong>W'.$w.' / '.$y.'</strong>). This package is <strong>not</strong> the organisation-wide compiled document sent to central recipients. (2) A completion summary covering those division reporting units only.</p>';
                $combinedBody = WeeklyBriefingMailTemplate::wrap($directorStaff, 'Weekly brief — director report', $combinedInner);
                $combinedSubject = $subjectPrefix.'Weekly brief — director report — '.$dirLabel.' — W'.$w.'/'.$y.WeeklyBriefingMailTemplate::subjectSuffix();

                try {
                    if (! sendEmail($raw, $combinedSubject, $combinedBody, null, null, [], [], $attachments)) {
                        Log::warning('weekly-briefing:compiled-summary director combined sendEmail returned false', ['to' => $raw, 'director_id' => $directorId, 'directorate_id' => $dirTorateId]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('weekly-briefing:compiled-summary director combined mail failed', ['e' => $e->getMessage(), 'to' => $raw, 'director_id' => $directorId]);
                }
            }
        }

        $this->info('weekly-briefing:compiled-summary dispatched.');

        return self::SUCCESS;
    }
}
