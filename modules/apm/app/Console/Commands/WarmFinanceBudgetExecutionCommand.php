<?php

namespace App\Console\Commands;

use App\Services\FinanceBudgetExecutionSnapshot;
use Illuminate\Console\Command;

class WarmFinanceBudgetExecutionCommand extends Command
{
    protected $signature = 'finance:warm-budget-execution
                            {--year= : Budget / financial year to warm (default: current)}
                            {--force : Refresh even when a snapshot already exists}';

    protected $description = 'Warm Redis snapshot of Finance budget-execution for the APM dashboard';

    public function handle(FinanceBudgetExecutionSnapshot $snapshot): int
    {
        $yearOpt = $this->option('year');
        $year = ($yearOpt === null || $yearOpt === '') ? null : (int) $yearOpt;

        if (! $this->option('force') && $snapshot->get($year) !== null) {
            $age = $snapshot->syncedAt();
            $this->info('Snapshot already present'.($age ? " (synced {$age})" : '').'. Use --force to refresh.');

            return self::SUCCESS;
        }

        $record = $snapshot->refresh($year);
        $divisions = count($record['data']['divisions'] ?? []);
        $this->info(sprintf(
            'Warmed Finance budget-execution snapshot (%d divisions) at %s',
            $divisions,
            $record['synced_at']
        ));

        return self::SUCCESS;
    }
}
