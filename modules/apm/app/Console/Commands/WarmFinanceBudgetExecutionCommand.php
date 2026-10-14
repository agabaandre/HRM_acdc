<?php

namespace App\Console\Commands;

use App\Services\FinanceBudgetExecutionSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class WarmFinanceBudgetExecutionCommand extends Command
{
    protected $signature = 'finance:warm-budget-execution
                            {--year= : Budget / financial year to warm (default: current)}
                            {--force : Refresh even when a snapshot already exists}';

    protected $description = 'Warm Redis snapshot of Finance budget-execution for the APM dashboard';

    public function handle(FinanceBudgetExecutionSnapshot $snapshot): int
    {
        $yearOpt = $this->option('year');
        $year = ($yearOpt === null || $yearOpt === '') ? (int) date('Y') : (int) $yearOpt;
        $quiet = $this->output->isQuiet();

        if (! $this->option('force') && $snapshot->get($year) !== null) {
            $age = $snapshot->syncedAt();
            $this->logQuiet('debug', 'finance:warm-budget-execution skipped', [
                'year' => $year,
                'synced_at' => $age,
            ]);
            if (! $quiet) {
                $this->info('Snapshot already present'.($age ? " (synced {$age})" : '').'. Use --force to refresh.');
            }

            return self::SUCCESS;
        }

        try {
            $record = $snapshot->refresh($year);
            $divisions = $snapshot->divisionCount($record['data'] ?? []);

            $this->logQuiet('info', 'finance:warm-budget-execution ok', [
                'year' => $year,
                'divisions' => $divisions,
                'synced_at' => $record['synced_at'],
                'sap_import' => $record['data']['sap_import']['id'] ?? null,
            ]);

            if (! $quiet) {
                $this->info(sprintf(
                    'Warmed Finance budget-execution snapshot (%d divisions) at %s',
                    $divisions,
                    $record['synced_at']
                ));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            // Scheduler runs with -q: log and exit 0 so a Finance blip does not
            // flood cron; the next 10-minute tick retries.
            $this->logQuiet('warning', 'finance:warm-budget-execution failed', [
                'year' => $year,
                'error' => $e->getMessage(),
            ]);

            if (! $quiet) {
                $this->error('Warm failed: '.$e->getMessage());

                return self::FAILURE;
            }

            return self::SUCCESS;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logQuiet(string $level, string $message, array $context = []): void
    {
        try {
            Log::log($level, $message, $context);
        } catch (Throwable) {
            // Logging must never fail a background warm.
        }
    }
}
