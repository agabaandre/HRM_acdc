<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class EmailMonthlyBackupArchivesCommand extends Command
{
    protected $signature = 'backup:email-monthly-archives {--force : Send even when today is not the last day of the month}';

    protected $description = 'Email end-of-month database backup files to configured recipients';

    public function handle(BackupService $backupService): int
    {
        $result = $backupService->sendMonthlyArchiveEmails((bool) $this->option('force'));

        if (($result['skipped'] ?? false) === true) {
            $this->warn('Skipped: '.($result['reason'] ?? 'unknown'));

            return self::SUCCESS;
        }

        $this->info('Monthly archive email dispatched.');
        $this->line(json_encode($result, JSON_PRETTY_PRINT));

        return ($result['sent'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
