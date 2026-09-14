<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FundCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeactivatePastYearFundCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fund-codes:deactivate-past-year
                            {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set is_active=0 for fund codes from previous years (run on first day of new year)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $currentYear = (int) date('Y');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN – no changes will be made.');
        }

        $query = FundCode::where('year', '<', $currentYear)->where('is_active', true);
        $count = $query->count();

        if ($count === 0) {
            $this->info("No active fund codes with year < {$currentYear} found. Nothing to do.");
            return self::SUCCESS;
        }

        $this->info("Found {$count} active fund code(s) with year < {$currentYear}.");

        if ($dryRun) {
            $years = FundCode::where('year', '<', $currentYear)->where('is_active', true)
                ->selectRaw('year, count(*) as cnt')
                ->groupBy('year')
                ->orderBy('year')
                ->get();
            foreach ($years as $r) {
                $this->line("  Year {$r->year}: {$r->cnt} would be deactivated.");
            }
            return self::SUCCESS;
        }

        try {
            $updated = DB::table('fund_codes')
                ->where('year', '<', $currentYear)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $this->info("Deactivated {$updated} fund code(s).");
            Log::info('fund-codes:deactivate-past-year', [
                'year' => $currentYear,
                'deactivated_count' => $updated,
            ]);
        } catch (\Throwable $e) {
            $this->error('Failed to deactivate fund codes: ' . $e->getMessage());
            Log::error('fund-codes:deactivate-past-year failed', [
                'year' => $currentYear,
                'error' => $e->getMessage(),
            ]);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
