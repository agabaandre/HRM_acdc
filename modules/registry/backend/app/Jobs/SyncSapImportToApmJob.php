<?php

namespace App\Jobs;

use App\Services\SapImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncSapImportToApmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $importId)
    {
        $this->onQueue('finance');
    }

    public function handle(SapImportService $imports): void
    {
        $result = $imports->performApmSync($this->importId);
        if (($result['sync_status'] ?? '') === 'sync_failed') {
            throw new \RuntimeException(
                'SAP→APM sync failed for import '.$this->importId
            );
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('SyncSapImportToApmJob failed', [
            'import_id' => $this->importId,
            'error' => $e?->getMessage(),
        ]);
    }
}
