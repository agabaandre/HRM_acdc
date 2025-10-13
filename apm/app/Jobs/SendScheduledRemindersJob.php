<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as BusQueueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;

class SendScheduledRemindersJob implements ShouldQueue
{
    use BusQueueable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300; // 5 minutes for bulk operations

    protected $reminderType;
    protected $scheduledTime;

    /**
     * Create a new job instance.
     */
    public function __construct(string $reminderType = 'daily', string $scheduledTime = null)
    {
        $this->reminderType = $reminderType;
        $this->scheduledTime = $scheduledTime ?? now()->format('H:i');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting scheduled {$this->reminderType} reminders job", [
                'scheduled_time' => $this->scheduledTime,
                'job_id' => $this->job->getJobId()
            ]);

            $notificationService = new NotificationService();
            
            switch ($this->reminderType) {
                case 'daily':
                    $this->sendDailyReminders($notificationService);
                    break;
                case 'morning':
                    $this->sendMorningReminders($notificationService);
                    break;
                case 'evening':
                    $this->sendEveningReminders($notificationService);
                    break;
                case 'urgent':
                    $this->sendUrgentReminders($notificationService);
                    break;
                default:
                    $this->sendDailyReminders($notificationService);
            }

            Log::info("Scheduled {$this->reminderType} reminders job completed successfully", [
                'scheduled_time' => $this->scheduledTime,
                'job_id' => $this->job->getJobId()
            ]);

        } catch (\Exception $e) {
            Log::error("Scheduled {$this->reminderType} reminders job failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'scheduled_time' => $this->scheduledTime,
                'job_id' => $this->job->getJobId()
            ]);
            
            throw $e;
        }
    }

    /**
     * Send daily reminders (9:00 AM)
     */
    private function sendDailyReminders(NotificationService $notificationService): void
    {
        Log::info('Sending daily scheduled reminders...');
        
        $notifications = $notificationService->createDailyPendingApprovalsNotifications();
        
        Log::info("Daily scheduled reminders completed", [
            'notifications_created' => count($notifications),
            'message' => 'All daily reminders have been queued for processing'
        ]);
    }

    /**
     * Send morning reminders (8:00 AM)
     */
    private function sendMorningReminders(NotificationService $notificationService): void
    {
        Log::info('Sending morning scheduled reminders...');
        
        $notifications = $notificationService->createDailyPendingApprovalsNotifications();
        
        // Add morning-specific message
        foreach ($notifications as $notification) {
            $notification->update([
                'message' => "Good morning! You have pending approval(s) requiring your attention today."
            ]);
        }
        
        Log::info("Morning scheduled reminders completed", [
            'notifications_created' => count($notifications),
            'message' => 'All morning reminders have been queued for processing'
        ]);
    }

    /**
     * Send evening reminders (5:00 PM)
     */
    private function sendEveningReminders(NotificationService $notificationService): void
    {
        Log::info('Sending evening scheduled reminders...');
        
        $notifications = $notificationService->createDailyPendingApprovalsNotifications();
        
        // Add evening-specific message
        foreach ($notifications as $notification) {
            $notification->update([
                'message' => "End of day reminder: You have pending approval(s) that need attention before tomorrow."
            ]);
        }
        
        Log::info("Evening scheduled reminders completed", [
            'notifications_created' => count($notifications),
            'message' => 'All evening reminders have been queued for processing'
        ]);
    }

    /**
     * Send urgent reminders (for items pending > 3 days)
     */
    private function sendUrgentReminders(NotificationService $notificationService): void
    {
        Log::info('Sending urgent scheduled reminders...');
        
        // Get only urgent pending items (pending > 3 days)
        $urgentNotifications = $notificationService->createUrgentPendingApprovalsNotifications();
        
        Log::info("Urgent scheduled reminders completed", [
            'notifications_created' => count($urgentNotifications),
            'message' => 'All urgent reminders have been queued for processing'
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Scheduled {$this->reminderType} reminders job failed permanently", [
            'error' => $exception->getMessage(),
            'scheduled_time' => $this->scheduledTime,
            'job_id' => $this->job->getJobId()
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'scheduled-reminders',
            'reminder-type:' . $this->reminderType,
            'scheduled-time:' . $this->scheduledTime
        ];
    }
}