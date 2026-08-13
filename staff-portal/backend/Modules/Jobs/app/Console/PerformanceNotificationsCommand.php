<?php

namespace Modules\Jobs\Console;

use Illuminate\Console\Command;
use Modules\Jobs\Services\PerformanceReminderService;

class PerformanceNotificationsCommand extends Command
{
    protected $signature = 'jobs:performance-notifications';

    protected $description = 'Queue PPA / Midterm / Endterm staff and supervisor reminder emails';

    public function handle(PerformanceReminderService $service): int
    {
        $stats = $service->runDailyNotifications();
        foreach ($stats as $key => $count) {
            $this->line("{$key}: {$count}");
        }

        return self::SUCCESS;
    }
}
