<?php

namespace Modules\Jobs\Console;

use Illuminate\Console\Command;
use Modules\Jobs\Services\PerformanceReminderService;

class PerformanceApprovalReminderCommand extends Command
{
    protected $signature = 'jobs:performance-approval-reminder';

    protected $description = 'Queue combined pending performance approval reminders for supervisors';

    public function handle(PerformanceReminderService $service): int
    {
        $n = $service->notifySupervisorsPendingPerformanceApproval();
        $this->info("queued={$n}");

        return self::SUCCESS;
    }
}
