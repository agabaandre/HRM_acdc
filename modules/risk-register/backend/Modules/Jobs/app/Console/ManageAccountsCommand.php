<?php

namespace Modules\Jobs\Console;

use Illuminate\Console\Command;
use Modules\Jobs\Services\ManageAccountsJobService;

class ManageAccountsCommand extends Command
{
    protected $signature = 'jobs:manage-accounts';

    protected $description = 'Sync portal user accounts from latest contract eligibility';

    public function handle(ManageAccountsJobService $service): int
    {
        $stats = $service->syncAll();
        $this->info(sprintf(
            'created=%d enabled=%d disabled=%d renamed=%d',
            $stats['created'],
            $stats['enabled'],
            $stats['disabled'],
            $stats['renamed'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
