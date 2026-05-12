<?php

namespace App\Console\Commands;

use App\Models\Staff;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use App\Services\WeeklyBriefingCompletionSummary;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WeeklyBriefingCompiledSummaryCommand extends Command
{
    protected $signature = 'weekly-briefing:compiled-summary';

    protected $description = 'Email compiled weekly briefing PDF to configured recipients.';

    public function handle(): int
    {
        $settings = WeeklyBriefingSetting::current();
        if (! $settings->reminders_enabled) {
            return self::SUCCESS;
        }
        if ((int) Carbon::now()->dayOfWeek !== (int) $settings->submission_weekday) {
            return self::SUCCESS;
        }
        if (! $settings->matchesTimeNow('summary_send_time')) {
            return self::SUCCESS;
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

        try {
            Mail::send([], [], function ($message) use ($recipients, $ccHods, $compiledFilename, $compiledBinary, $divisionAttachments, $summaryBinary, $summaryFilename, $y, $w) {
                $message->to($recipients)
                    ->subject("Weekly briefing compiled — W{$w}/{$y}")
                    ->html('<p>Attached: compiled weekly briefing (grouped by directorate / reporting unit), one PDF per submission, and a one-page completion summary for configured reporting units.</p>')
                    ->attachData($compiledBinary, $compiledFilename, ['mime' => 'application/pdf'])
                    ->attachData($summaryBinary, $summaryFilename, ['mime' => 'application/pdf']);
                foreach ($divisionAttachments as $att) {
                    $message->attachData($att['binary'], $att['filename'], ['mime' => 'application/pdf']);
                }
                if ($ccHods !== []) {
                    $message->cc($ccHods);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('weekly-briefing:compiled-summary mail failed', ['e' => $e->getMessage()]);
        }

        $this->info('weekly-briefing:compiled-summary dispatched.');

        return self::SUCCESS;
    }
}
