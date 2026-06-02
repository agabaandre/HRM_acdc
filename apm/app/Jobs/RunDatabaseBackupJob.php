<?php

namespace App\Jobs;

use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunDatabaseBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 3600;

    public function __construct(
        public string $type = 'daily',
        public bool $runCleanup = false,
        public ?int $databaseId = null
    ) {
        if (! in_array($this->type, ['daily', 'monthly', 'annual'], true)) {
            $this->type = 'daily';
        }
    }

    public function handle(BackupService $backupService): void
    {
        Log::info('RunDatabaseBackupJob started', [
            'type' => $this->type,
            'cleanup' => $this->runCleanup,
            'database_id' => $this->databaseId,
        ]);

        $result = $backupService->createBackup($this->type, $this->databaseId);

        if ($result === false) {
            Log::error('RunDatabaseBackupJob failed', ['type' => $this->type]);
            throw new \RuntimeException("Backup failed ({$this->type})");
        }

        if ($this->runCleanup) {
            $backupService->cleanupOldBackups();
        }

        Log::info('RunDatabaseBackupJob completed', [
            'type' => $this->type,
            'success_count' => $result['success_count'] ?? null,
            'total_count' => $result['total_count'] ?? null,
        ]);
    }
}
