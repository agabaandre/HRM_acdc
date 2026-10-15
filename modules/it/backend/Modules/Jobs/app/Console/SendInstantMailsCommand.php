<?php

namespace Modules\Jobs\Console;

use Illuminate\Console\Command;
use Modules\Jobs\Services\SendQueuedMailService;

class SendInstantMailsCommand extends Command
{
    protected $signature = 'jobs:send-instant-mails {--sleep : Throttle between sends}';

    protected $description = 'Send queued instant emails (performance, birthday, contracts)';

    public function handle(SendQueuedMailService $sender): int
    {
        $stats = $sender->sendInstant((bool) $this->option('sleep'));
        $this->info(sprintf('sent=%d failed=%d skipped=%d', $stats['sent'], $stats['failed'], $stats['skipped']));

        return self::SUCCESS;
    }
}
