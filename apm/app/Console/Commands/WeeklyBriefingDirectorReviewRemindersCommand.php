<?php

namespace App\Console\Commands;

use App\Models\Staff;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use App\Services\WeeklyBriefingNotificationMailer;
use App\Services\WeeklyBriefingScheduleGate;
use App\Support\WeeklyBriefingMailTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WeeklyBriefingDirectorReviewRemindersCommand extends Command
{
    protected $signature = 'weekly-briefing:director-review-reminders {--force : Run immediately, ignoring schedule gates}';

    protected $description = 'Remind directorate directors (directorates.director_id) of submitted weekly briefs still pending director review before the submission deadline (per configured day offsets and clock time).';

    public function handle(): int
    {
        $settings = WeeklyBriefingSetting::current();
        $force = (bool) $this->option('force');
        $gate = WeeklyBriefingScheduleGate::for($settings);

        $filing = $settings->filingIsoWeekPair();
        $y = $filing['iso_year'];
        $w = $filing['iso_week'];
        $deadline = $settings->filingSubmissionDeadline();

        if (! $gate->passesDirectorReviewReminderSchedule($force)) {
            return self::SUCCESS;
        }

        $slotKey = $gate->directorReviewReminderSlotKey();
        if (! $force && ! $gate->tryClaimDispatch('director_review', $slotKey)) {
            return self::SUCCESS;
        }

        $pending = WeeklyBriefingReport::query()
            ->where('report_iso_week_year', $y)
            ->where('report_iso_week', $w)
            ->where('status', WeeklyBriefingReport::STATUS_SUBMITTED)
            ->get()
            ->filter(fn (WeeklyBriefingReport $r) => $r->requiresDirectorReview() && ! $r->isDirectorReviewed());

        if ($pending->isEmpty()) {
            if (! $force) {
                $gate->releaseDispatch('director_review', $slotKey);
            }

            return self::SUCCESS;
        }

        $dispatched = false;
        $deadlineHuman = htmlspecialchars($deadline->format('l, F j, Y \a\t g:i A'), ENT_QUOTES, 'UTF-8');
        $weekHuman = htmlspecialchars(WeeklyBriefingReport::humanIsoWeekRange($y, $w), ENT_QUOTES, 'UTF-8');

        $byDirector = [];
        foreach ($pending as $report) {
            $directorStaffId = $report->assignedDirectorStaffId();
            if ($directorStaffId <= 0) {
                continue;
            }
            if ($directorStaffId === (int) ($report->submitted_by_staff_id ?? 0)) {
                continue;
            }
            $byDirector[$directorStaffId][] = $report;
        }

        foreach ($byDirector as $directorStaffId => $reports) {
            $director = Staff::query()->find($directorStaffId);
            $email = $director?->work_email;
            if (! $email) {
                Log::info('weekly-briefing:director-review-reminders — no director email', ['staff_id' => $directorStaffId]);

                continue;
            }

            $lines = [];
            foreach ($reports as $rep) {
                $label = htmlspecialchars($rep->contributionEntityLabel(), ENT_QUOTES, 'UTF-8');
                $edit = htmlspecialchars(route('weekly-briefing.edit', ['report' => $rep->id], true), ENT_QUOTES, 'UTF-8');
                $lines[] = "<li><strong>{$label}</strong> — <a href=\"{$edit}\">Open to review</a></li>";
            }
            $listHtml = '<ul style="margin:8px 0;padding-left:20px;">'.implode('', $lines).'</ul>';

            $inner = <<<HTML
<p>This is a reminder to review <strong>submitted</strong> weekly brief(s) pending your sign-off before the submission deadline.</p>
<p><strong>Reporting week:</strong> {$weekHuman}</p>
<p><strong>Deadline:</strong> {$deadlineHuman}</p>
{$listHtml}
<p style="font-size:12px;color:#64748b;">If you have already marked these as reviewed, you can ignore this message.</p>
HTML;

            $subject = 'Weekly brief — director review pending'.WeeklyBriefingMailTemplate::subjectSuffix();
            if (WeeklyBriefingNotificationMailer::sendToStaff($director, $subject, 'Weekly brief — director review reminder', $inner, 'weekly_briefing_director_review')) {
                $dispatched = true;
            }
        }

        if (! $dispatched && ! $force) {
            $gate->releaseDispatch('director_review', $slotKey);
        }

        $this->info('weekly-briefing:director-review-reminders completed.');

        return self::SUCCESS;
    }
}
