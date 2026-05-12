<?php

namespace App\Services;

use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use Carbon\Carbon;

class WeeklyBriefingWindowService
{
    public function canEditReport(WeeklyBriefingReport $report): bool
    {
        $settings = WeeklyBriefingSetting::current();

        if ($report->status === WeeklyBriefingReport::STATUS_LOCKED) {
            return false;
        }

        $deadline = $report->submissionDeadline($settings);

        return Carbon::now()->lessThanOrEqualTo($deadline);
    }

    public function canSubmitReport(WeeklyBriefingReport $report): bool
    {
        $settings = WeeklyBriefingSetting::current();

        if ($report->status !== WeeklyBriefingReport::STATUS_DRAFT) {
            return false;
        }

        return Carbon::now()->lessThanOrEqualTo($report->submissionDeadline($settings));
    }
}
