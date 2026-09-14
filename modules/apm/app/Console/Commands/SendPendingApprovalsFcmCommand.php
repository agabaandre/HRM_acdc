<?php

namespace App\Console\Commands;

use App\Jobs\SendPendingApprovalsFcmJob;
use App\Models\ApmApiUser;
use App\Services\FirebaseMessagingService;
use App\Services\PendingApprovalsService;
use Illuminate\Console\Command;

class SendPendingApprovalsFcmCommand extends Command
{
    protected $signature = 'notifications:send-pending-approvals-fcm
                            {--sync : Run sends synchronously instead of queuing jobs}
                            {--user= : Only send to this API user_id}';

    protected $description = 'Send FCM push notifications to API users who have pending approvals and a registered Firebase token';

    public function handle(FirebaseMessagingService $fcm): int
    {
        if (!$fcm->isConfigured()) {
            $this->warn('Firebase FCM is not configured (FIREBASE_PROJECT_ID and credentials file). Skipping.');
            return 0;
        }

        $query = ApmApiUser::whereNotNull('firebase_token')
            ->where('firebase_token', '!=', '')
            ->where('status', true);

        if ($userId = $this->option('user')) {
            $query->where('user_id', $userId);
        }

        $users = $query->get();
        if ($users->isEmpty()) {
            $this->info('No API users with Firebase token found.');
            return 0;
        }

        $sent = 0;
        foreach ($users as $user) {
            $sessionData = $user->toSessionArray();
            $pendingService = new PendingApprovalsService($sessionData);
            $summary = $pendingService->getSummaryStats();
            $total = (int) ($summary['total_pending'] ?? 0);
            if ($total <= 0) {
                continue;
            }

            if ($this->option('sync')) {
                (new SendPendingApprovalsFcmJob($user))->handle($fcm);
                $sent++;
            } else {
                SendPendingApprovalsFcmJob::dispatch($user);
                $sent++;
            }
        }

        $this->info("Dispatched/sent {$sent} pending-approvals FCM notification(s).");
        return 0;
    }
}
