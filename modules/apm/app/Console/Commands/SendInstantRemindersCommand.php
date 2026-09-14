<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use App\Services\PendingApprovalsService;
use App\Models\Staff;
use Illuminate\Support\Facades\Log;

class SendInstantRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send-instant 
                            {--staff-id= : Send reminder to specific staff member by ID}
                            {--email= : Send reminder to specific staff member by email}
                            {--all : Send reminders to all approvers with pending items}
                            {--test : Run in test mode (dry run)}
                            {--force : Force send even if no pending items}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send instant pending approval reminders to staff members';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting instant reminders dispatch...');
        
        try {
            if ($this->option('all')) {
                $this->sendToAllApprovers();
            } elseif ($staffId = $this->option('staff-id')) {
                $this->sendToSpecificStaff($staffId);
            } elseif ($email = $this->option('email')) {
                $this->sendToSpecificEmail($email);
            } else {
                $this->error('❌ Please specify --staff-id, --email, or --all');
                return 1;
            }
            
            $this->info('✅ Instant reminders dispatched successfully!');
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Error sending reminders: ' . $e->getMessage());
            Log::error('Instant reminders command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Send reminders to all approvers with pending items
     */
    private function sendToAllApprovers(): void
    {
        $this->info('📧 Sending reminders to all approvers...');
        
        $notificationService = new NotificationService();
        $notifications = $notificationService->createDailyPendingApprovalsNotifications();
        
        if (empty($notifications)) {
            if ($this->option('force')) {
                $this->warn('⚠️  No pending approvals found, but --force specified. Sending anyway...');
            } else {
                $this->info('ℹ️  No pending approvals found. Use --force to send anyway.');
                return;
            }
        }
        
        $this->info("✅ Created {$count} notification(s) for approvers with pending items");
        
        if ($this->option('test')) {
            $this->info('🧪 Test mode: Notifications created but not sent');
            return;
        }
        
        $this->info('📤 Notifications queued for processing...');
    }

    /**
     * Send reminder to specific staff member by ID
     */
    private function sendToSpecificStaff(string $staffId): void
    {
        $staff = Staff::find($staffId);
        
        if (!$staff) {
            $this->error("❌ Staff member with ID {$staffId} not found");
            return;
        }
        
        $this->info("📧 Sending reminder to: {$staff->fname} {$staff->lname} ({$staff->work_email})");
        
        $this->sendReminderToStaff($staff);
    }

    /**
     * Send reminder to specific staff member by email
     */
    private function sendToSpecificEmail(string $email): void
    {
        $staff = Staff::where('work_email', $email)->first();
        
        if (!$staff) {
            $this->error("❌ Staff member with email {$email} not found");
            return;
        }
        
        $this->info("📧 Sending reminder to: {$staff->fname} {$staff->lname} ({$staff->work_email})");
        
        $this->sendReminderToStaff($staff);
    }

    /**
     * Send reminder to a specific staff member
     */
    private function sendReminderToStaff(Staff $staff): void
    {
        try {
            // Create session data for the staff member
            $sessionData = [
                'staff_id' => $staff->staff_id,
                'division_id' => $staff->division_id,
                'permissions' => [],
                'name' => $staff->fname . ' ' . $staff->lname,
                'email' => $staff->work_email,
                'base_url' => config('app.url')
            ];
            
            $pendingService = new PendingApprovalsService($sessionData);
            $summaryStats = $pendingService->getSummaryStats();
            
            if ($summaryStats['total_pending'] === 0) {
                if ($this->option('force')) {
                    $this->warn('⚠️  No pending items for this staff member, but --force specified');
                } else {
                    $this->info('ℹ️  No pending items for this staff member. Use --force to send anyway.');
                    return;
                }
            }
            
            $this->info("📊 Pending items: {$summaryStats['total_pending']}");
            
            // Show breakdown by category
            foreach ($summaryStats['by_category'] as $category => $count) {
                $this->line("   - {$category}: {$count}");
            }
            
            if ($this->option('test')) {
                $this->info('🧪 Test mode: Would send reminder but not actually sending');
                return;
            }
            
            // Create and send notification
            $notificationService = new NotificationService();
            $notification = $notificationService->createNotification([
                'staff_id' => $staff->staff_id,
                'model_id' => null,
                'model_type' => null,
                'message' => "You have {$summaryStats['total_pending']} pending approval(s) requiring your attention.",
                'type' => 'instant_reminder',
                'send_email' => true,
                'pending_approvals' => $pendingService->getPendingApprovals(),
                'summary_stats' => $summaryStats
            ]);
            
            $this->info("✅ Reminder sent successfully to {$staff->fname} {$staff->lname}");
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to send reminder to {$staff->fname} {$staff->lname}: " . $e->getMessage());
            throw $e;
        }
    }
}