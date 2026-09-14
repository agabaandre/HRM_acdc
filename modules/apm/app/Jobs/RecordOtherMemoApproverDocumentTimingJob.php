<?php

namespace App\Jobs;

use App\Services\ApproverDocumentTimingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecordOtherMemoApproverDocumentTimingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    public function __construct(public int $otherMemoApprovalTrailId) {}

    public function handle(ApproverDocumentTimingService $service): void
    {
        try {
            $service->recordFromOtherMemoTrailId($this->otherMemoApprovalTrailId);
        } catch (\Throwable $e) {
            Log::error('RecordOtherMemoApproverDocumentTimingJob failed', [
                'other_memo_approval_trail_id' => $this->otherMemoApprovalTrailId,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
