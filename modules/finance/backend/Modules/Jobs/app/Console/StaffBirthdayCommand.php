<?php

namespace Modules\Jobs\Console;

use Illuminate\Console\Command;
use Modules\Jobs\Services\SendQueuedMailService;
use Modules\Jobs\Services\StaffBirthdayService;

class StaffBirthdayCommand extends Command
{
    protected $signature = 'jobs:staff-birthday {--send : Also run instant mail send after queue}';

    protected $description = 'Queue birthday greeting emails for active staff';

    public function handle(StaffBirthdayService $birthdays, SendQueuedMailService $sender): int
    {
        $stats = $birthdays->queueTodaysBirthdays();
        $this->info(sprintf('queued=%d skipped=%d', $stats['queued'], $stats['skipped']));
        if ($this->option('send') || $stats['queued'] > 0) {
            $send = $sender->sendInstant(false);
            $this->line(sprintf('send: sent=%d failed=%d', $send['sent'], $send['failed']));
        }

        return self::SUCCESS;
    }
}
