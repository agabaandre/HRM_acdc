<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SendReturnedMemosNotificationJob;
use Illuminate\Support\Facades\Log;

class ScheduleReturnedMemosRemindersCommand extends Command
{
    protected $signature = 'reminders:returned-memos 
                            {--test : Run in test mode (dry run)}
                            {--force : Force run even if not scheduled time}';

    protected $description = 'Schedule returned memos reminders to staff with returned items';

    public function handle()
    {
        $isTestMode = $this->option('test');
        $isForced = $this->option('force');

        if ($isTestMode) {
            $this->info('🧪 Running in TEST MODE - No jobs will be created');
            $this->runTestMode();
            return;
        }

        if (!$isForced && !$this->isCorrectTime()) {
            $this->warn('⏰ Not the scheduled time (8:00 AM, 1:00 PM, or 5:00 PM). Use --force to override.');
            return;
        }

        $this->info('🚀 Scheduling returned memos reminders for all staff...');

        try {
            dispatch(new SendReturnedMemosNotificationJob());
            $this->info('✅ Returned memos reminders job dispatched successfully!');
        } catch (\Exception $e) {
            $this->error('❌ Failed to dispatch returned memos reminders job: ' . $e->getMessage());
        }
    }

    private function isCorrectTime(): bool
    {
        $currentHour = now()->hour;
        return $currentHour === 8 || $currentHour === 13 || $currentHour === 17;
    }

    private function runTestMode(): void
    {
        $this->info('📊 Analyzing what would be sent to staff with returned memos...');

        try {
            // Get all staff who have returned memos
            $staffWithReturnedMemos = $this->getAllStaffWithReturnedMemos();
            
            $this->info("Found " . count($staffWithReturnedMemos) . " staff with returned memos");
            
            $totalReturned = 0;
            $staffWithReturned = 0;

            foreach ($staffWithReturnedMemos as $staff) {
                try {
                    // Ensure we have array access
                    if (is_object($staff)) {
                        $staff = (array) $staff;
                    }
                    
                    $sessionData = [
                        'staff_id' => $staff['staff_id'],
                        'division_id' => $staff['division_id'] ?? null,
                        'permissions' => [],
                        'name' => $staff['fname'] . ' ' . $staff['lname'],
                        'email' => $staff['work_email'],
                        'base_url' => config('app.url')
                    ];

                    $returnedMemosService = new \App\Services\ReturnedMemosService($sessionData);
                    $summaryStats = $returnedMemosService->getSummaryStats();
                    
                    if ($summaryStats['total_returned'] > 0) {
                        $staffWithReturned++;
                        $totalReturned += $summaryStats['total_returned'];
                        
                        $this->line("  📧 {$staff['fname']} {$staff['lname']} ({$staff['work_email']}) - {$summaryStats['total_returned']} returned items");
                    } else {
                        $this->line("  ✅ {$staff['fname']} {$staff['lname']} ({$staff['work_email']}) - No returned items");
                    }
                } catch (\Exception $e) {
                    $this->error("  ❌ Error processing {$staff['fname']} {$staff['lname']}: " . $e->getMessage());
                }
            }

            $this->info("\n📈 Summary:");
            $this->info("  • Total staff checked: " . count($staffWithReturnedMemos));
            $this->info("  • Staff with returned items: {$staffWithReturned}");
            $this->info("  • Total returned items: {$totalReturned}");
            $this->info("  • Jobs that would be created: {$staffWithReturned}");

        } catch (\Exception $e) {
            $this->error('❌ Test mode failed: ' . $e->getMessage());
        }
    }

    /**
     * Get all staff who have returned memos based on the same logic as ReturnedMemosService
     */
    public function getAllStaffWithReturnedMemos(): array
    {
        // Get unique staff IDs who have returned memos based on our filtering logic
        $staffIds = collect();

        // 1. Matrix - staff_id
        $matrixStaffIds = \App\Models\Matrix::where('overall_status', 'returned')
            ->pluck('staff_id')
            ->unique();

        // 2. Special Memo - responsible_person_id
        $specialMemoStaffIds = \App\Models\SpecialMemo::where('overall_status', 'returned')
            ->pluck('responsible_person_id')
            ->unique();

        // 3. Non-Travel Memo - staff_id
        $nonTravelStaffIds = \App\Models\NonTravelMemo::where('overall_status', 'returned')
            ->pluck('staff_id')
            ->unique();

        // 4. Single Memo - staff_id OR responsible_person_id
        $singleMemoStaffIds = \App\Models\Activity::where('is_single_memo', true)
            ->whereIn('overall_status', ['returned', 'draft'])
            ->get()
            ->pluck('staff_id')
            ->merge(\App\Models\Activity::where('is_single_memo', true)
                ->whereIn('overall_status', ['returned', 'draft'])
                ->pluck('responsible_person_id'))
            ->unique();

        // 5. Service Request - staff_id
        $serviceRequestStaffIds = \App\Models\ServiceRequest::where('overall_status', 'returned')
            ->pluck('staff_id')
            ->unique();

        // 6. ARF - staff_id
        $arfStaffIds = \App\Models\RequestARF::where('overall_status', 'returned')
            ->pluck('staff_id')
            ->unique();

        // 7. Change Request - responsible_person_id
        $changeRequestStaffIds = \App\Models\ChangeRequest::where('overall_status', 'returned')
            ->pluck('responsible_person_id')
            ->unique();

        // Combine all staff IDs (only those who actually have returned memos)
        $allStaffIds = $staffIds
            ->merge($matrixStaffIds)
            ->merge($specialMemoStaffIds)
            ->merge($nonTravelStaffIds)
            ->merge($singleMemoStaffIds)
            ->merge($serviceRequestStaffIds)
            ->merge($arfStaffIds)
            ->merge($changeRequestStaffIds)
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        // Get staff details and convert to array format
        $staff = \App\Models\Staff::whereIn('staff_id', $allStaffIds)
            ->where('active', 1)
            ->whereNotNull('work_email')
            ->get()
            ->map(function($staffMember) {
                return $staffMember->toArray();
            })
            ->toArray();

        return $staff;
    }
}
