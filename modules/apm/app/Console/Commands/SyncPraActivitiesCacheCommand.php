<?php

namespace App\Console\Commands;

use App\Services\Pra\PraActivitiesCacheService;
use App\Services\Pra\PraSettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncPraActivitiesCacheCommand extends Command
{
    protected $signature = 'pra:cache-activities
                            {--fiscal-year= : Fiscal year to cache (default: configured / current)}';

    protected $description = 'Refresh Redis cache of PRA specific activities for matrix creation';

    public function handle(PraSettingsService $settings, PraActivitiesCacheService $cache): int
    {
        if (! $settings->isConfigured()) {
            $this->warn('PRA not configured — skipping activities cache.');

            return self::SUCCESS;
        }

        $fiscal = $this->option('fiscal-year');
        $year = ($fiscal === null || $fiscal === '') ? null : (int) $fiscal;

        try {
            $result = $cache->refresh($year);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            Log::error('pra.cache_activities.failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Cached %d PRA activities for FY %d at %s',
            $result['activities'],
            $result['fiscal_year'],
            $result['cached_at']
        ));

        return self::SUCCESS;
    }
}
