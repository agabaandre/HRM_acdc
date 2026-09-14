<?php

namespace App\Console\Commands;

use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WeeklyBriefingLockDraftsCommand extends Command
{
    protected $signature = 'weekly-briefing:lock-drafts';

    protected $description = 'Lock draft weekly briefings whose submission deadline has passed.';

    public function handle(): int
    {
        $settings = WeeklyBriefingSetting::current();

        $now = Carbon::now();
        $n = 0;
        foreach (WeeklyBriefingReport::query()->where('status', WeeklyBriefingReport::STATUS_DRAFT)->cursor() as $report) {
            if ($now->greaterThan($report->submissionDeadline($settings))) {
                if ($settings->reportUnlockOverrideAppliesTo($report)) {
                    continue;
                }
                $report->update(['status' => WeeklyBriefingReport::STATUS_LOCKED]);
                $n++;
            }
        }

        Log::info('weekly-briefing:lock-drafts', ['locked' => $n]);
        $this->info("Locked {$n} draft report(s).");

        return self::SUCCESS;
    }
}
