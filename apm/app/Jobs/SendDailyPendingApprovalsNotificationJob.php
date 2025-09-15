<?php

namespace App\Jobs;

use App\Services\PendingApprovalsService;
use App\Models\Staff;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable as BusQueueable;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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

            // Get all staff who are approvers (either division-specific or regular approvers)
            $approvers = $this->getAllApprovers();
            
            $totalNotifications = 0;
            $successCount = 0;
            $failureCount = 0;

            foreach ($approvers as $approver) {
                try {
                    $notificationSent = $this->processApproverNotification($approver);
                    if ($notificationSent) {
                        $successCount++;
                        $totalNotifications++;
                    }
                } catch (\Exception $e) {
                    $failureCount++;
                    Log::error('Failed to send notification to approver', [
                        'staff_id' => $approver->staff_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Daily pending approvals notification job completed', [
                'total_approvers' => count($approvers),
                'successful_notifications' => $successCount,
                'failed_notifications' => $failureCount,
                'total_notifications' => $totalNotifications
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
     * Get all staff who are approvers
     */
    private function getAllApprovers(): array
    {
        $approvers = [];

        // 1. Get division-specific approvers from divisions table
        $divisionApprovers = DB::table('divisions')
            ->select('division_head as staff_id')
            ->whereNotNull('division_head')
            ->union(
                DB::table('divisions')
                    ->select('focal_person as staff_id')
                    ->whereNotNull('focal_person')
            )
            ->union(
                DB::table('divisions')
                    ->select('admin_assistant as staff_id')
                    ->whereNotNull('admin_assistant')
            )
            ->union(
                DB::table('divisions')
                    ->select('finance_officer as staff_id')
                    ->whereNotNull('finance_officer')
            )
            ->get()
            ->pluck('staff_id')
            ->unique()
            ->filter()
            ->toArray();

        // 2. Get regular approvers from approvers table
        $regularApprovers = DB::table('approvers')
            ->distinct()
            ->pluck('staff_id')
            ->toArray();

        // 3. Combine and get unique staff IDs
        $allApproverIds = array_unique(array_merge($divisionApprovers, $regularApprovers));

        // 4. Get staff details for all approvers
        $approvers = Staff::whereIn('staff_id', $allApproverIds)
            ->where('active', 1)
            ->whereNotNull('work_email')
            ->get()
            ->toArray();

        return $approvers;
    }

    /**
     * Process notification for a single approver
     * @return bool True if notification was sent, false if no pending items
     */
    private function processApproverNotification($approver): bool
    {
        // Create session data for the approver
        $sessionData = [
            'staff_id' => $approver['staff_id'],
            'division_id' => $approver['division_id'],
            'permissions' => [],
            'name' => $approver['fname'] . ' ' . $approver['lname'],
            'email' => $approver['work_email'],
            'base_url' => config('app.url')
        ];

        // Get pending approvals for this approver using the service
        $pendingApprovalsService = new PendingApprovalsService($sessionData);
        $pendingApprovals = $pendingApprovalsService->getPendingApprovals();
        $summaryStats = $pendingApprovalsService->getSummaryStats();

        // Only send notification if there are pending items
        if ($summaryStats['total_pending'] === 0) {
            return false; // No pending items, no notification sent
        }

        // Create notification record
        Notification::create([
            'staff_id' => $approver['staff_id'],
            'model_id' => null, // No specific model for daily summary
            'message' => "You have {$summaryStats['total_pending']} pending approval(s) requiring your attention.",
            'type' => 'daily_pending_approvals',
            'is_read' => false
        ]);

        // Send email notification
        $this->sendDailyPendingApprovalsEmail($approver, $pendingApprovals, $summaryStats);
        
        return true; // Notification was sent
    }

    /**
     * Send daily pending approvals email
     */
    private function sendDailyPendingApprovalsEmail($approver, $pendingApprovals, $summaryStats): void
    {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = env('MAIL_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = env('MAIL_USERNAME');
            $mail->Password = env('MAIL_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = env('MAIL_PORT');

            // Recipients
            $mail->setFrom(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME', 'Africa CDC APM'));
            $mail->addAddress($approver['work_email'], $approver['fname'] . ' ' . $approver['lname']);
            $mail->addBCC('system@africacdc.org');

            // Subject - Dynamic based on time of day
            $prefix = env('MAIL_SUBJECT_PREFIX', 'APM') . ": ";
            $currentHour = (int) date('H');
            $timeOfDay = $currentHour < 12 ? 'Morning' : 'Evening';
            $mail->Subject = $prefix . "{$timeOfDay} Pending Approvals Reminder - {$summaryStats['total_pending']} items";

            // Render email template
            $htmlContent = View::make('emails.daily-pending-approvals-notification', [
                'approver' => $approver,
                'pendingApprovals' => $pendingApprovals,
                'summaryStats' => $summaryStats,
                'approverName' => $approver['fname'] . ' ' . $approver['lname'],
                'approverTitle' => $approver['title'] ?? 'Mr',
                'baseUrl' => config('app.url')
            ])->render();

            // Content
            $mail->isHTML(true);
            $mail->Body = $htmlContent;
            
            // Create plain text version
            $plainText = strip_tags($htmlContent);
            $mail->AltBody = $plainText;

            $result = $mail->send();

            Log::info("Daily pending approvals email sent successfully", [
                'staff_id' => $approver['staff_id'],
                'total_pending' => $summaryStats['total_pending'],
                'result' => $result
            ]);

        } catch (Exception $e) {
            Log::error('PHPMailer error in daily pending approvals notification', [
                'staff_id' => $approver['staff_id'],
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
        Log::error('Daily pending approvals notification job failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
