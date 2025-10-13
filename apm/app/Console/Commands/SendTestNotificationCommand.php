<?php

namespace App\Console\Commands;

use App\Mail\DailyPendingApprovalsMail;
use App\Models\Staff;
use App\Services\PendingApprovalsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-test {staff_id : The staff ID to send the test notification to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send test pending approvals notification to specific staff member';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $staffId = $this->argument('staff_id');
        
        $this->info("📧 Sending test notification to staff ID: {$staffId}");
        
        $staff = Staff::find($staffId);
        if (!$staff) {
            $this->error("Staff member with ID {$staffId} not found");
            return 1;
        }
        
        $this->info("Staff: {$staff->fname} {$staff->lname} ({$staff->work_email})");
        
        // Create session data
        $sessionData = [
            'staff_id' => $staffId,
            'division_id' => $staff->division_id ?? 1,
            'permissions' => [],
            'name' => $staff->fname . ' ' . $staff->lname,
            'email' => $staff->work_email,
            'base_url' => config('app.url')
        ];
        
        // Get pending approvals
        $pendingApprovalsService = new PendingApprovalsService($sessionData);
        $pendingApprovals = $pendingApprovalsService->getPendingApprovals();
        $summaryStats = $pendingApprovalsService->getSummaryStats();
        
        $this->info("Total pending items: {$summaryStats['total_pending']}");
        
        // Show breakdown by category
        foreach ($summaryStats['by_category'] as $category => $count) {
            $this->line("  - {$category}: {$count}");
        }
        
        // Create notification data
        $notificationData = [
            'approverName' => $staff->fname . ' ' . $staff->lname,
            'approverTitle' => 'Mr.',
            'summaryStats' => $summaryStats,
            'pendingApprovals' => $pendingApprovals
        ];
        
    // Send email using Exchange service (same as matrix notifications)
    try {
        $result = $this->sendWithExchange($staff, $notificationData);
        
        if ($result) {
            $this->info("✅ Email sent successfully to {$staff->work_email}");
            return 0;
        } else {
            $this->error("❌ Failed to send email via Exchange");
            return 1;
        }
    } catch (\Exception $e) {
        $this->error("❌ Error sending email: " . $e->getMessage());
        return 1;
    }
    }

    /**
     * Send email using Exchange service (same as matrix notifications)
     */
    private function sendWithExchange($staff, $notificationData): bool
    {
        try {
            $config = config('exchange-email');
            
            // Use the working implementation from local ExchangeEmailService
            require_once app_path('ExchangeEmailService/ExchangeOAuth.php');
            
            $oauth = new \AgabaandreOffice365\ExchangeEmailService\ExchangeOAuth(
                $config['tenant_id'],
                $config['client_id'],
                $config['client_secret'],
                $config['redirect_uri'] ?? 'http://localhost:8000/oauth/callback',
                'https://graph.microsoft.com/.default', // Correct scope for client credentials
                'client_credentials' // Force client credentials
            );
            
            if (!$oauth->isConfigured()) {
                $this->error('Exchange service not configured - Exchange is required for all emails');
                return false;
            }

            // Generate subject
            $prefix = env('MAIL_SUBJECT_PREFIX', 'APM') . ": ";
            $currentHour = (int) date('H');
            $timeOfDay = $currentHour < 12 ? 'Morning' : 'Evening';
            $totalPending = $notificationData['summaryStats']['total_pending'] ?? 0;
            $subject = $prefix . "{$timeOfDay} Pending Approvals Reminder - {$totalPending} items";

            // Generate HTML content
            $htmlContent = view('emails.daily-pending-approvals-notification', $notificationData)->render();

            // Send via Exchange
            return $oauth->sendEmail(
                $staff->work_email,
                $subject,
                $htmlContent,
                true, // isHtml
                null, // fromEmail
                null, // fromName
                [],   // cc recipients
                ['system@africacdc.org'], // bcc recipients
                []    // attachments
            );

        } catch (\Exception $e) {
            $this->error('Exchange email sending failed: ' . $e->getMessage());
            return false;
        }
    }
}
