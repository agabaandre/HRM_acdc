<?php

namespace App\Console\Commands;

use App\Services\ActivityReport\ActivityReportReminderService;
use App\Support\ActivityReportingModule;
use Illuminate\Console\Command;

class SendActivityReportRemindersCommand extends Command
{
    protected $signature = 'activity-reports:remind {--test : Dry run — count only, do not queue emails}';

    protected $description = 'Send overdue Activity Report reminders (end date + 7 days, current quarter)';

    public function handle(ActivityReportReminderService $reminders): int
    {
        if (! ActivityReportingModule::notificationsEnabled()) {
            $this->info('Skipped — activity reporting notifications are disabled (App settings → Module control).');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('test');
        if ($dry) {
            $this->info('Dry run — counting eligible reminders only.');
        }

        $count = $reminders->notifyEligible($dry);
        $this->info(($dry ? 'Would notify' : 'Queued')." {$count} reminder(s).");

        return self::SUCCESS;
    }
}
