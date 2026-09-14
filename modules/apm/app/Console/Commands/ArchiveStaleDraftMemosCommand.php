<?php

namespace App\Console\Commands;

use App\Services\BudgetCommitmentSettings;
use App\Services\StaleMemoArchiveService;
use Illuminate\Console\Command;

class ArchiveStaleDraftMemosCommand extends Command
{
    protected $signature = 'memos:archive-stale-drafts
                            {--dry-run : List stale drafts that would be archived without archiving}
                            {--force : Run even when auto-archive is disabled in App settings}';

    protected $description = 'Archive stale draft memos holding budget (weekly schedule; see System configs → Stale memos)';

    public function handle(StaleMemoArchiveService $archiver, BudgetCommitmentSettings $settings): int
    {
        if (! $this->option('force') && ! $settings->staleDraftAutoArchiveEnabled()) {
            $this->info('Stale draft auto-archive is disabled (App settings → Budget).');

            return self::SUCCESS;
        }

        if ($settings->draftBudgetCutoff() === null) {
            $this->info('Draft max age is disabled (0 months); nothing to archive.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            return $this->runDryRun();
        }

        $this->info('Archiving stale draft memos…');
        $result = $archiver->archiveAllStaleDrafts('scheduled');
        $this->info("Done. Archived: {$result['archived']}, skipped: {$result['skipped']}.");
        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }

    private function runDryRun(): int
    {
        $service = new \App\Services\StaleDraftMemosService();
        $items = $service->getAllStaleDrafts();

        if ($items === []) {
            $this->info('No stale draft memos holding budget.');

            return self::SUCCESS;
        }

        $this->info('Dry run — would archive ' . count($items) . ' memo(s):');
        foreach ($items as $item) {
            $this->line("  • {$item['type_label']}: {$item['title']} (#{$item['id']}, \${$item['budget_total']})");
        }

        return self::SUCCESS;
    }
}
