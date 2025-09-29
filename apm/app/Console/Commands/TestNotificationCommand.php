<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use App\Models\Staff;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:notification {email=andrewa@africacdc.org}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test notification system by creating a test notification';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("Testing notification system for: {$email}");
        
        try {
            // Find staff member by email
            $staff = Staff::where('work_email', $email)->first();
            
            if (!$staff) {
                $this->error("❌ Staff member not found with email: {$email}");
                return 1;
            }
            
            $this->info("✅ Found staff member: {$staff->fname} {$staff->lname}");
            
            // Create test notification
            $notificationService = new NotificationService();
            $notification = $notificationService->createNotification([
                'staff_id' => $staff->staff_id,
                'model_id' => null,
                'model_type' => null,
                'message' => 'This is a test notification from the APM system.',
                'type' => 'test_notification',
                'send_email' => true
            ]);
            
            $this->info("✅ Test notification created successfully!");
            $this->info("📧 Notification ID: {$notification->id}");
            $this->info("📧 Email job has been queued for sending");
            
            // Test daily pending approvals notifications
            $this->info("\n🔄 Testing daily pending approvals notifications...");
            $dailyNotifications = $notificationService->createDailyPendingApprovalsNotifications();
            $this->info("✅ Created " . count($dailyNotifications) . " daily pending approvals notifications");
            
            Log::info('Test notification created successfully', [
                'staff_id' => $staff->staff_id,
                'email' => $email,
                'notification_id' => $notification->id
            ]);
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('Test notification failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
        
        return 0;
    }
}