<?php

namespace Modules\Jobs\Console;

use Illuminate\Console\Command;
use Modules\Jobs\Services\ContractReminderService;

class AuditExtendedContractsCommand extends Command
{
    protected $signature = 'jobs:audit-extended-contracts';

    protected $description = 'Clear stale due/expired contract notification rows for healthy contracts';

    public function handle(ContractReminderService $service): int
    {
        $stats = $service->auditExtendedContracts();
        $this->info(sprintf(
            'cleared_notifications=%d cleared_flags=%d',
            $stats['cleared_notifications'],
            $stats['cleared_flags'],
        ));

        return self::SUCCESS;
    }
}
