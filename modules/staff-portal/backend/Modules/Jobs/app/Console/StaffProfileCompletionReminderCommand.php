<?php

namespace Modules\Jobs\Console;

use Illuminate\Console\Command;
use Modules\Jobs\Services\StaffProfileReminderService;

class StaffProfileCompletionReminderCommand extends Command
{
    protected $signature = 'jobs:staff-profile-completion-reminder';

    protected $description = 'Queue profile completion reminder emails (max every 2 days per staff)';

    public function handle(StaffProfileReminderService $service): int
    {
        $stats = $service->queueIncompleteProfileReminders();
        $this->info(sprintf('queued=%d skipped=%d', $stats['queued'], $stats['skipped']));

        return self::SUCCESS;
    }
}
