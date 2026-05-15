<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\WeeklyBriefingContributor;
use App\Models\WeeklyBriefingReport;
use App\Support\WeeklyBriefingMailTemplate;
use Illuminate\Support\Facades\Log;

/**
 * Emails the **directorate director** ({@see Directorate::director_id}) when a contributor submits a weekly brief that requires director review.
 * Uses {@see sendEmail} and {@see WeeklyBriefingMailTemplate} like other weekly briefing notifications.
 */
final class WeeklyBriefingDirectorSubmitNotifier
{
    public static function notifyDirectorsAfterContributorSubmit(WeeklyBriefingReport $report): void
    {
        $report->loadMissing(['submittedBy']);

        if ($report->status !== WeeklyBriefingReport::STATUS_SUBMITTED) {
            return;
        }

        if (! $report->requiresDirectorReview()) {
            return;
        }

        $dir = $report->directorateForDirectorReview();
        if (! $dir) {
            return;
        }

        $directorStaffId = (int) ($dir->director_id ?? 0);
        if (! $directorStaffId || $directorStaffId <= 0) {
            return;
        }

        if ((int) $directorStaffId === (int) ($report->submitted_by_staff_id ?? 0)) {
            return;
        }

        $director = Staff::query()->find($directorStaffId);
        if (! $director || ! $director->work_email) {
            Log::info('weekly-briefing:director-submit-reminder — no director work email', [
                'report_id' => $report->id,
                'director_staff_id' => $directorStaffId,
            ]);

            return;
        }

        $y = (int) $report->report_iso_week_year;
        $w = (int) $report->report_iso_week;
        $label = htmlspecialchars(
            WeeklyBriefingContributor::presentationLabelForContributionKey((string) $report->contribution_key),
            ENT_QUOTES,
            'UTF-8'
        );
        $weekHuman = htmlspecialchars(WeeklyBriefingReport::humanIsoWeekRange($y, $w), ENT_QUOTES, 'UTF-8');
        $editUrl = htmlspecialchars(route('weekly-briefing.edit', ['report' => $report->id], true), ENT_QUOTES, 'UTF-8');
        $indexUrl = htmlspecialchars(route('weekly-briefing.index', [], true), ENT_QUOTES, 'UTF-8');

        $submitter = $report->submittedBy;
        $submitterName = 'A contributor';
        if ($submitter) {
            $submitterName = htmlspecialchars(
                trim((string) ($submitter->name ?? '')),
                ENT_QUOTES,
                'UTF-8'
            );
            if ($submitterName === '') {
                $submitterName = htmlspecialchars(
                    trim(($submitter->fname ?? '').' '.($submitter->lname ?? '')),
                    ENT_QUOTES,
                    'UTF-8'
                );
            }
            if ($submitterName === '') {
                $submitterName = 'A contributor';
            }
        }

        $inner = <<<HTML
<p><strong>{$submitterName}</strong> has submitted the <strong>Weekly brief</strong> for reporting unit <strong>{$label}</strong>.</p>
<p><strong>Reporting week:</strong> {$weekHuman}</p>
<p>Please review it in APM before the brief is included in the organisation-wide compilation.</p>
<p style="text-align:center;"><a class="btn" href="{$editUrl}">Open briefing to review</a></p>
<p>You can also open the <strong>Weekly brief</strong> module from the <a href="{$indexUrl}">APM home navigation</a>.</p>
<p style="font-size:12px;color:#64748b;">This message was sent automatically because director review is required for this reporting unit (directorate director on the directorates table).</p>
HTML;

        $subject = 'Weekly brief submitted — please review — '.$label.' — W'.$w.'/'.$y.WeeklyBriefingMailTemplate::subjectSuffix();
        if (! WeeklyBriefingNotificationMailer::sendToStaff($director, $subject, 'Weekly brief — director review requested', $inner, 'weekly_briefing_director_submit', $report)) {
            Log::warning('weekly-briefing:director-submit-reminder mail failed', [
                'to' => $director->work_email,
                'report_id' => $report->id,
            ]);
        }
    }
}
