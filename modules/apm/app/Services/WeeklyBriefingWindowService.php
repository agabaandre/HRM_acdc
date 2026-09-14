<?php

namespace App\Services;

use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use Carbon\Carbon;

class WeeklyBriefingWindowService
{
    /**
     * Contributors may edit draft (or locked draft during admin unlock). Submitted briefs stay read-only for contributors.
     * Without an active admin unlock: past deadline or locked status blocks editing; locked + past week cannot submit (submit requires draft).
     */
    public function canEditReport(WeeklyBriefingReport $report): bool
    {
        $settings = WeeklyBriefingSetting::current();
        $override = $settings->reportUnlockOverrideAppliesTo($report);
        if ($override) {
            if ($report->status === WeeklyBriefingReport::STATUS_SUBMITTED) {
                return false;
            }

            return true;
        }

        if ($report->status === WeeklyBriefingReport::STATUS_LOCKED) {
            return false;
        }

        $deadline = $report->submissionDeadline($settings);

        return Carbon::now()->lessThanOrEqualTo($deadline);
    }

    /**
     * Submit is only for drafts before the deadline, unless an admin unlock allows submitting a locked late draft.
     */
    public function canSubmitReport(WeeklyBriefingReport $report): bool
    {
        $settings = WeeklyBriefingSetting::current();
        $override = $settings->reportUnlockOverrideAppliesTo($report);
        if ($override) {
            return in_array($report->status, [
                WeeklyBriefingReport::STATUS_DRAFT,
                WeeklyBriefingReport::STATUS_LOCKED,
            ], true);
        }

        if ($report->status !== WeeklyBriefingReport::STATUS_DRAFT) {
            return false;
        }

        return Carbon::now()->lessThanOrEqualTo($report->submissionDeadline($settings));
    }

    public function canDirectorEditSubmittedReport(WeeklyBriefingReport $report): bool
    {
        $settings = WeeklyBriefingSetting::current();
        $override = $settings->reportUnlockOverrideAppliesTo($report);
        if ($report->status === WeeklyBriefingReport::STATUS_LOCKED) {
            return $override && DivisionWeeklyBriefGate::mayEditAsDivisionDirector($report);
        }
        if ($report->status !== WeeklyBriefingReport::STATUS_SUBMITTED) {
            return false;
        }
        if (! DivisionWeeklyBriefGate::mayEditAsDivisionDirector($report)) {
            return false;
        }
        if ($override) {
            return true;
        }
        $deadline = $report->submissionDeadline($settings);

        return Carbon::now()->lessThanOrEqualTo($deadline);
    }

    public function canMarkDirectorReview(WeeklyBriefingReport $report): bool
    {
        return DivisionWeeklyBriefGate::mayMarkDirectorReview($report) && $this->canDirectorEditSubmittedReport($report);
    }
}
