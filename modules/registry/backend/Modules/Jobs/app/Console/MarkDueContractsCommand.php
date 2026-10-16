<?php

namespace Modules\Jobs\Console;

use Illuminate\Console\Command;
use Modules\Jobs\Services\ContractReminderService;

class MarkDueContractsCommand extends Command
{
    protected $signature = 'jobs:mark-due-contracts';

    protected $description = 'Mark contracts due/expired, queue reminder emails, sync portal accounts';

    public function handle(ContractReminderService $service): int
    {
        $stats = $service->markDueContracts();
        $this->info(sprintf('due=%d expired=%d restored=%d', $stats['due'], $stats['expired'], $stats['restored']));

        return self::SUCCESS;
    }
}
