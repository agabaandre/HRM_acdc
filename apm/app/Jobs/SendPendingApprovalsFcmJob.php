<?php

namespace App\Jobs;

use App\Models\ApmApiUser;
use App\Services\FirebaseMessagingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPendingApprovalsFcmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected ApmApiUser $user
    ) {}

    public function handle(FirebaseMessagingService $fcm): void
    {
        $token = $this->user->firebase_token;
        if (empty($token) || !$fcm->isConfigured()) {
            return;
        }

        $sessionData = $this->user->toSessionArray();
        $pendingService = new PendingApprovalsService($sessionData);
        $summary = $pendingService->getSummaryStats();
        $total = (int) ($summary['total_pending'] ?? 0);
        if ($total <= 0) {
            return;
        }

        $baseUrl = config('app.url');
        $deepLink = rtrim($baseUrl, '/') . '/staff/apm/pending-approvals';
        $fcm->sendPendingApprovalsNotification($token, $total, $deepLink);
    }
}
