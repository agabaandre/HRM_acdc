<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyPendingApprovalsNotificationJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailyPendingApprovalsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:daily-pending-approvals 
                            {--test : Run in test mode (dry run)}
                            {--force : Force run even if not scheduled time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily pending approvals notifications to all approvers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isTestMode = $this->option('test');
        $isForced = $this->option('force');

        if ($isTestMode) {
            $this->info('🧪 Running in TEST MODE - No emails will be sent');
            $this->runTestMode();
            return;
        }

        // Check if it's the right time to send (9 AM) unless forced
        if (!$isForced && !$this->isCorrectTime()) {
            $this->warn('⏰ Not the scheduled time (9:00 AM). Use --force to override.');
            return;
        }

        $this->info('🚀 Starting daily pending approvals notification job...');

        try {
            // Run the job directly instead of dispatching to queue
            $job = new SendDailyPendingApprovalsNotificationJob();
            $job->handle();
            
            $this->info('✅ Daily pending approvals notification job completed successfully!');
            $this->info('📧 Notifications have been sent to all approvers with pending items.');
            
            Log::info('Daily pending approvals notification job completed via command', [
                'timestamp' => now(),
                'forced' => $isForced
            ]);

        } catch (\Exception $e) {
            $this->error('❌ Failed to dispatch daily pending approvals notification job: ' . $e->getMessage());
            Log::error('Failed to dispatch daily pending approvals notification job', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Check if it's the correct time to send notifications (9 AM)
     */
    private function isCorrectTime(): bool
    {
        $currentHour = now()->hour;
        return $currentHour === 9;
    }

    /**
     * Run in test mode to show what would be sent
     */
    private function runTestMode(): void
    {
        $this->info('📊 Analyzing pending approvals for all approvers...');

        try {
            // Get all approvers (same logic as the job)
            $approvers = $this->getAllApprovers();
            
            $this->info("Found " . count($approvers) . " active approvers");
            
            $totalPending = 0;
            $approversWithPending = 0;

            foreach ($approvers as $approver) {
                $sessionData = [
                    'staff_id' => $approver['staff_id'],
                    'division_id' => $approver['division_id'],
                    'permissions' => [],
                    'name' => $approver['fname'] . ' ' . $approver['lname'],
                    'email' => $approver['work_email'],
                    'base_url' => config('app.url')
                ];

                $pendingApprovalsService = new \App\Services\PendingApprovalsService($sessionData);
                $summaryStats = $pendingApprovalsService->getSummaryStats();
                
                if ($summaryStats['total_pending'] > 0) {
                    $approversWithPending++;
                    $totalPending += $summaryStats['total_pending'];
                    
                    $this->line("  📧 {$approver['fname']} {$approver['lname']} ({$approver['work_email']}) - {$summaryStats['total_pending']} pending items");
                } else {
                    $this->line("  ✅ {$approver['fname']} {$approver['lname']} ({$approver['work_email']}) - No pending items");
                }
            }

            $this->info("\n📈 Summary:");
            $this->info("  • Total approvers: " . count($approvers));
            $this->info("  • Approvers with pending items: {$approversWithPending}");
            $this->info("  • Total pending items: {$totalPending}");
            $this->info("  • Emails that would be sent: {$approversWithPending}");

        } catch (\Exception $e) {
            $this->error('❌ Test mode failed: ' . $e->getMessage());
        }
    }

    /**
     * Get all staff who are approvers (same logic as the job)
     */
    private function getAllApprovers(): array
    {
        // 1. Get division-specific approvers from divisions table
        $divisionApprovers = \DB::table('divisions')
            ->select('division_head as staff_id')
            ->whereNotNull('division_head')
            ->union(
                \DB::table('divisions')
                    ->select('focal_person as staff_id')
                    ->whereNotNull('focal_person')
            )
            ->union(
                \DB::table('divisions')
                    ->select('admin_assistant as staff_id')
                    ->whereNotNull('admin_assistant')
            )
            ->union(
                \DB::table('divisions')
                    ->select('finance_officer as staff_id')
                    ->whereNotNull('finance_officer')
            )
            ->get()
            ->pluck('staff_id')
            ->unique()
            ->filter()
            ->toArray();

        // 2. Get regular approvers from approvers table
        $regularApprovers = \DB::table('approvers')
            ->distinct()
            ->pluck('staff_id')
            ->toArray();

        // 3. Combine and get unique staff IDs
        $allApproverIds = array_unique(array_merge($divisionApprovers, $regularApprovers));

        // 4. Get staff details for all approvers
        $approvers = \App\Models\Staff::whereIn('staff_id', $allApproverIds)
            ->where('active', 1)
            ->whereNotNull('work_email')
            ->get()
            ->toArray();

        return $approvers;
    }
}
