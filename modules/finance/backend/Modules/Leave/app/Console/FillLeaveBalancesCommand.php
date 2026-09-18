<?php

namespace Modules\Leave\Console;

use Illuminate\Console\Command;
use Modules\Leave\Services\LeaveBalanceService;

class FillLeaveBalancesCommand extends Command
{
    protected $signature = 'leave:fill-balances
                            {--year= : Calendar year (default: current)}
                            {--overwrite : Replace existing opening_days from leave type defaults}';

    protected $description = 'Fill staff_leave_opening_balances for all active staff from leave type defaults';

    public function handle(LeaveBalanceService $balances): int
    {
        $year = $this->option('year') !== null ? (int) $this->option('year') : null;
        $overwrite = (bool) $this->option('overwrite');

        $result = $balances->bulkFillOpeningBalances(
            year: $year,
            overwrite: $overwrite,
            userId: null,
        );

        $this->info(sprintf(
            'Year %d — staff %d, created %d, updated %d, skipped %d.',
            $result['year'],
            $result['staff_processed'],
            $result['rows_created'],
            $result['rows_updated'],
            $result['rows_skipped'],
        ));

        return self::SUCCESS;
    }
}
