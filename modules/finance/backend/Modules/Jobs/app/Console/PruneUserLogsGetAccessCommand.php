<?php

namespace Modules\Jobs\Console;

use Illuminate\Console\Command;
use Modules\Jobs\Services\PruneUserLogsService;

class PruneUserLogsGetAccessCommand extends Command
{
    protected $signature = 'jobs:prune-user-logs-get-access';

    protected $description = 'Delete GET access rows from user_logs';

    public function handle(PruneUserLogsService $service): int
    {
        $n = $service->pruneGetAccess();
        $this->info("deleted={$n}");

        return self::SUCCESS;
    }
}
