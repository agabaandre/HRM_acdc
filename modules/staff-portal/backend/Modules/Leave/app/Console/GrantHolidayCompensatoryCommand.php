<?php

namespace Modules\Leave\Console;

use Illuminate\Console\Command;
use Modules\Leave\Services\HolidayCompensatoryGrantService;

class GrantHolidayCompensatoryCommand extends Command
{
    protected $signature = 'leave:grant-holiday-compensatory
                            {--year= : Calendar year (default: current)}
                            {--through= : Grant holidays on or before this date (Y-m-d, default: today)}';

    protected $description = 'Grant holiday compensatory credits for weekend public holidays (cap 15, expire 31 Dec)';

    public function handle(HolidayCompensatoryGrantService $grants): int
    {
        $year = $this->option('year') !== null ? (int) $this->option('year') : null;
        $through = $this->option('through') ?: null;

        $result = $grants->grantAll($year, $through);

        $this->info(sprintf(
            'Year %d — staff %d, credits granted %d, staff with no new credits %d.',
            $year ?? (int) now()->year,
            $result['staff'],
            $result['granted'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
