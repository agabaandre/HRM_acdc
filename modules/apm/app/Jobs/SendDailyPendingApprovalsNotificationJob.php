<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable as BusQueueable;
use Illuminate\Support\Facades\Log;

class SendDailyPendingApprovalsNotificationJob implements ShouldQueue
{
    use BusQueueable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // Number of retry attempts
    public $timeout = 300; // Timeout in seconds (5 minutes for bulk operations)

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        // No parameters needed - this job will process all approvers
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting daily pending approvals notification job');

            // Use the NotificationService to create notifications and dispatch email jobs
            $notificationService = new \App\Services\NotificationService();
            $notifications = $notificationService->createDailyPendingApprovalsNotifications();
            
            Log::info('Daily pending approvals notification job completed', [
                'notifications_created' => count($notifications),
                'message' => 'All notifications have been queued for processing'
            ]);

        } catch (\Exception $e) {
            Log::error('Daily pending approvals notification job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e; // Re-throw to trigger retry
        }
    }


    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Daily pending approvals notification job failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
