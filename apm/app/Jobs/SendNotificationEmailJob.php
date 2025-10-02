<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\Staff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as BusQueueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;

class SendNotificationEmailJob implements ShouldQueue
{
    use BusQueueable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    protected $notification;
    protected $data;

    /**
     * Create a new job instance.
     */
    public function __construct(Notification $notification, array $data = [])
    {
        $this->notification = $notification;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $staff = Staff::where('staff_id', $this->notification->staff_id)->first();
            
            if (!$staff || !$staff->work_email) {
                Log::warning('Staff member not found or no email address', [
                    'notification_id' => $this->notification->id,
                    'staff_id' => $this->notification->staff_id
                ]);
                return;
            }

            // Send email based on notification type using centralized system
            switch ($this->notification->type) {
                case 'daily_pending_approvals':
                    $this->sendDailyPendingApprovalsEmail($staff);
                    break;
                default:
                    $this->sendGenericNotificationEmail($staff);
                    break;
            }

            Log::info('Notification email sent successfully', [
                'notification_id' => $this->notification->id,
                'staff_id' => $this->notification->staff_id,
                'email' => $staff->work_email
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send notification email', [
                'notification_id' => $this->notification->id,
                'staff_id' => $this->notification->staff_id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Send daily pending approvals email using centralized system
     */
    private function sendDailyPendingApprovalsEmail(Staff $staff): void
    {
        try {
            // Use centralized email system
            $pendingApprovals = $this->data['pending_approvals'] ?? [];
            $summaryStats = $this->data['summary_stats'] ?? [];
            
            $result = sendDailyPendingApprovalsEmail($staff, $pendingApprovals, $summaryStats);
            
            if (!$result) {
                throw new \Exception('Failed to send daily pending approvals email');
            }

        } catch (\Exception $e) {
            Log::error('Error in daily pending approvals notification email', [
                'notification_id' => $this->notification->id,
                'staff_id' => $this->notification->staff_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send generic notification email using centralized system
     */
    private function sendGenericNotificationEmail(Staff $staff): void
    {
        try {
            // Use centralized email system
            $title = $this->data['title'] ?? 'APM Notification';
            $message = $this->data['message'] ?? $this->notification->message;
            $data = $this->data['data'] ?? [];
            
            $result = sendGenericNotificationEmail($staff, $title, $message, $data);
            
            if (!$result) {
                throw new \Exception('Failed to send generic notification email');
            }

        } catch (\Exception $e) {
            Log::error('Error in generic notification email', [
                'notification_id' => $this->notification->id,
                'staff_id' => $this->notification->staff_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Notification email job failed permanently', [
            'notification_id' => $this->notification->id,
            'staff_id' => $this->notification->staff_id,
            'error' => $exception->getMessage()
        ]);
    }
}