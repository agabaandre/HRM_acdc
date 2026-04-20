<?php

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable as BusQueueable;
use Illuminate\Support\Facades\Log;

class SendStalePendingApprovalsReminderJob implements ShouldQueue
{
    use BusQueueable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function handle(): void
    {
        try {
            Log::info('Starting stale pending approvals reminder job');
            $notificationService = new NotificationService();
            $notifications = $notificationService->createStalePendingApprovalsReminders();

            Log::info('Stale pending approvals reminder job completed', [
                'notifications_created' => count($notifications),
            ]);
        } catch (\Exception $e) {
            Log::error('Stale pending approvals reminder job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Stale pending approvals reminder job failed permanently', [
            'error' => $exception->getMessage(),
        ]);
    }
}
