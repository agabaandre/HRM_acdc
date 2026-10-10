<?php

namespace App\Console\Commands;

use App\Services\Pra\PraOrgUnitMappingService;
use App\Services\Pra\PraSettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncPraOrgUnitsCommand extends Command
{
    protected $signature = 'pra:sync-org-units
                            {--fiscal-year= : Override fiscal year}
                            {--notify : Email support_email when unmatched mappings remain}
                            {--force-notify : Email even when there are no mismatches (dry check)}';

    protected $description = 'Fetch PRA divisions/directorates, preserve existing matches, add new codes, optionally alert support_email';

    public function handle(PraSettingsService $settings, PraOrgUnitMappingService $mappings): int
    {
        if (! $settings->isConfigured()) {
            $this->warn('PRA API is not configured. Skipping.');
            Log::warning('pra.sync_org_units.skipped', ['reason' => 'not_configured']);

            return self::SUCCESS;
        }

        $fiscal = $this->option('fiscal-year');
        $fiscalYear = ($fiscal === null || $fiscal === '') ? null : (int) $fiscal;

        $this->info('Syncing PRA org units…');

        try {
            $result = $mappings->syncFromPra($fiscalYear);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            Log::error('pra.sync_org_units.failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        $summary = $result['summary'];
        $this->info(sprintf(
            'FY %s — total=%d matched=%d unmatched=%d added=%d preserved=%d',
            $result['fiscal_year'],
            $summary['total'],
            $summary['matched'],
            $summary['unmatched'],
            $summary['added'],
            $summary['preserved']
        ));

        $shouldNotify = $this->option('notify') || $this->option('force-notify');
        if ($shouldNotify) {
            if ($summary['unmatched'] > 0 || $this->option('force-notify')) {
                try {
                    $sent = $mappings->notifyAdminOfMismatches($result);
                    $this->info($sent ? 'Support email sent for mismatches.' : 'No support email sent (check support_email / mail).');
                } catch (Throwable $e) {
                    // Sync already persisted — do not fail the job on mail errors.
                    $this->warn('Mismatch email failed: '.$e->getMessage());
                    Log::error('pra.sync_org_units.notify_failed', ['error' => $e->getMessage()]);
                }
            } else {
                $this->info('No mismatches — support email not sent.');
            }
        }

        return self::SUCCESS;
    }
}
