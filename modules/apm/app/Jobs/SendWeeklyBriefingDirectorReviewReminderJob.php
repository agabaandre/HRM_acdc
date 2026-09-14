<?php

namespace App\Jobs;

use App\Models\WeeklyBriefingReport;
use App\Services\WeeklyBriefingDirectorSubmitNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued so HTTP submit stays fast; uses same mail path as other weekly briefing notifications.
 */
class SendWeeklyBriefingDirectorReviewReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $weeklyBriefingReportId) {}

    public function handle(): void
    {
        $report = WeeklyBriefingReport::query()->find($this->weeklyBriefingReportId);
        if (! $report || $report->status !== WeeklyBriefingReport::STATUS_SUBMITTED) {
            return;
        }

        WeeklyBriefingDirectorSubmitNotifier::notifyDirectorsAfterContributorSubmit($report);
    }
}
