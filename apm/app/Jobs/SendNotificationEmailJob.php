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

            // Send email based on notification type
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
     * Send daily pending approvals email
     */
    private function sendDailyPendingApprovalsEmail(Staff $staff): void
    {
        try {
            // Use Laravel's mail system
            Mail::to($staff->work_email)
                ->bcc('system@africacdc.org')
                ->send(new \App\Mail\DailyPendingApprovalsMail([
                    'approver' => $staff->toArray(),
                    'pendingApprovals' => $this->data['pending_approvals'] ?? [],
                    'summaryStats' => $this->data['summary_stats'] ?? [],
                    'approverName' => $staff->fname . ' ' . $staff->lname,
                    'approverTitle' => $staff->title ?? 'Mr',
                    'baseUrl' => config('app.url')
                ]));

        } catch (\Exception $e) {
            Log::error('Laravel Mail error in notification email', [
                'notification_id' => $this->notification->id,
                'staff_id' => $this->notification->staff_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send generic notification email
     */
    private function sendGenericNotificationEmail(Staff $staff): void
    {
        try {
            // Use Laravel's mail system
            Mail::to($staff->work_email)
                ->bcc('system@africacdc.org')
                ->send(new \App\Mail\GenericNotificationMail([
                    'staff' => $staff,
                    'notification' => $this->notification,
                    'message' => $this->notification->message,
                    'type' => $this->notification->type
                ]));

        } catch (\Exception $e) {
            Log::error('Laravel Mail error in generic notification email', [
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