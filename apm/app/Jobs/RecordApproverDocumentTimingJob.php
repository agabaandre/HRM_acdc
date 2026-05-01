<?php

namespace App\Jobs;

use App\Services\ApproverDocumentTimingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecordApproverDocumentTimingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    public function __construct(public int $approvalTrailId) {}

    public function handle(ApproverDocumentTimingService $service): void
    {
        try {
            $service->recordFromApprovalTrailId($this->approvalTrailId);
        } catch (\Throwable $e) {
            Log::error('RecordApproverDocumentTimingJob failed', [
                'approval_trail_id' => $this->approvalTrailId,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
