<?php

namespace App\Jobs;

use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EmailMonthlyBackupArchivesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 1800;

    public function handle(BackupService $backupService): void
    {
        Log::info('EmailMonthlyBackupArchivesJob started');
        $result = $backupService->sendMonthlyArchiveEmails();
        Log::info('EmailMonthlyBackupArchivesJob finished', $result);
    }
}
