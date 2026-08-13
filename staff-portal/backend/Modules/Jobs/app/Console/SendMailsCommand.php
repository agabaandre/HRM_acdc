<?php

namespace Modules\Jobs\Console;

use Illuminate\Console\Command;
use Modules\Jobs\Services\SendQueuedMailService;

class SendMailsCommand extends Command
{
    protected $signature = 'jobs:send-mails {--sleep : Throttle between sends}';

    protected $description = 'Send scheduled email_notifications queue (non-birthday)';

    public function handle(SendQueuedMailService $sender): int
    {
        $stats = $sender->sendScheduled((bool) $this->option('sleep'));
        $this->info(sprintf('sent=%d failed=%d skipped=%d', $stats['sent'], $stats['failed'], $stats['skipped']));

        return self::SUCCESS;
    }
}
