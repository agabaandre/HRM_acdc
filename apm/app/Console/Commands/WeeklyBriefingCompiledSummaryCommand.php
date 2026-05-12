<?php

namespace App\Console\Commands;

use App\Models\Staff;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use App\Services\WeeklyBriefingCompletionSummary;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WeeklyBriefingCompiledSummaryCommand extends Command
{
    protected $signature = 'weekly-briefing:compiled-summary {--force : Run immediately, ignoring reminders_enabled, weekday, and summary_send_time}';

    protected $description = 'Email compiled weekly briefing PDF to configured recipients.';

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
            ->with(['division', 'directorate'])
            ->get();

        $reports = WeeklyBriefingCompletionSummary::sortReportsForCompiled($reports);

        $recipients = collect(explode(',', (string) $settings->compiled_recipient_emails))
            ->map(fn ($e) => trim((string) $e))
            ->filter(fn ($e) => $e !== '' && Str::contains($e, '@'))
            ->values()
            ->all();

        if ($recipients === []) {
            Log::info('weekly-briefing:compiled-summary — no recipients configured');

            return self::SUCCESS;
        }

        if ($reports->isEmpty()) {
            Log::info('weekly-briefing:compiled-summary — no submitted reports');

            return self::SUCCESS;
        }

        $pdf = mpdf_print('weekly-briefing.pdf-compiled', [
            'reports' => $reports,
            'settings' => $settings,
            'isoYear' => $y,
            'isoWeek' => $w,
        ], ['orientation' => 'L']);

        $compiledBinary = $pdf->Output('', 'S');
        $compiledFilename = 'Weekly_Briefing_Compiled_W'.$w.'_'.$y.'.pdf';

        $divisionAttachments = [];
        foreach ($reports as $report) {
            $one = mpdf_print('weekly-briefing.pdf-division', [
                'report' => $report,
                'settings' => $settings,
            ], ['orientation' => 'L']);
            $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $report->contributionEntityLabel());
            $divisionAttachments[] = [
                'binary' => $one->Output('', 'S'),
                'filename' => 'Weekly_Briefing_'.$safe.'_W'.$w.'_'.$y.'.pdf',
            ];
        }

        $completionRows = WeeklyBriefingCompletionSummary::rows($settings, $y, $w);
        $summaryPdf = mpdf_print('weekly-briefing.pdf-completion-summary', [
            'rows' => $completionRows,
            'settings' => $settings,
            'isoYear' => $y,
            'isoWeek' => $w,
        ], ['orientation' => 'L']);
        $summaryBinary = $summaryPdf->Output('', 'S');
        $summaryFilename = 'Weekly_Briefing_Completion_Summary_W'.$w.'_'.$y.'.pdf';

        $ccHods = [];
        if ($settings->cc_division_hod_on_compiled) {
            foreach ($reports as $report) {
                $hodId = $report->division?->division_head ?? null;
                if (! $hodId) {
                    continue;
                }
                $email = Staff::query()->where('staff_id', $hodId)->value('work_email');
                if ($email && Str::contains($email, '@')) {
                    $ccHods[] = strtolower(trim($email));
                }
            }
            $ccHods = array_values(array_unique(array_diff($ccHods, array_map('strtolower', $recipients))));
        }

        $subjectPrefix = env('MAIL_SUBJECT_PREFIX', 'APM').': ';
        $subject = $subjectPrefix."Weekly briefing compiled — W{$w}/{$y}";
        $body = '<p>Attached: compiled weekly briefing (grouped by directorate / reporting unit), one PDF per submission, and a one-page completion summary for configured reporting units.</p>';
        $graphAttachments = [
            ['name' => $compiledFilename, 'content' => $compiledBinary, 'content_type' => 'application/pdf'],
            ['name' => $summaryFilename, 'content' => $summaryBinary, 'content_type' => 'application/pdf'],
        ];
        foreach ($divisionAttachments as $att) {
            $graphAttachments[] = [
                'name' => $att['filename'],
                'content' => $att['binary'],
                'content_type' => 'application/pdf',
            ];
        }

        try {
            if (! sendEmail($recipients, $subject, $body, null, null, $ccHods, [], $graphAttachments)) {
                Log::warning('weekly-briefing:compiled-summary sendEmail returned false');
            }
        } catch (\Throwable $e) {
            Log::warning('weekly-briefing:compiled-summary mail failed', ['e' => $e->getMessage()]);
        }

        $this->info('weekly-briefing:compiled-summary dispatched.');

        return self::SUCCESS;
    }
}
