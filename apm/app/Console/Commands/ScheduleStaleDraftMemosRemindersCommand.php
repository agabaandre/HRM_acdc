<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class ScheduleStaleDraftMemosRemindersCommand extends Command
{
    protected $signature = 'reminders:stale-draft-memos
                            {--dry-run : List who would be notified without sending}';

    protected $description = 'Remind creators to delete or submit stale draft memos that hold budget (see App settings → Budget)';

    public function handle(NotificationService $notifications): int
    {
        if ($this->option('dry-run')) {
            return $this->runDryRun();
        }

        $this->info('Creating stale draft memo reminders…');
        $created = $notifications->createStaleDraftMemosReminders();
        $this->info('Done. Notifications created: ' . count($created));

        return self::SUCCESS;
    }

    private function runDryRun(): int
    {
        $service = new \App\Services\StaleDraftMemosService();
        $staffIds = $service->staffIdsWithStaleDrafts();

        if ($staffIds === []) {
            $this->info('No staff with stale draft memos holding budget.');

            return self::SUCCESS;
        }

        $this->info('Dry run — staff who would be notified:');
        foreach ($staffIds as $staffId) {
            $items = $service->getStaleDraftsForStaff($staffId);
            $this->line("  • Staff #{$staffId}: " . count($items) . ' stale draft(s)');
            foreach ($items as $item) {
                $this->line("      - {$item['type_label']}: {$item['title']} (\${$item['budget_total']})");
            }
        }

        return self::SUCCESS;
    }
}
