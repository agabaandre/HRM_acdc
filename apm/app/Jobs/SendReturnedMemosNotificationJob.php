<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendNotificationEmailJob;
use App\Services\ReturnedMemosService;

class SendReturnedMemosNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // Number of retry attempts
    public $timeout = 300; // Timeout in seconds (5 minutes for bulk operations)

    public function handle(): void
    {
        Log::info('SendReturnedMemosNotificationJob started');

        try {
            // Get all staff who have returned memos (initiators, not approvers)
            $staffWithReturnedMemos = $this->getAllStaffWithReturnedMemos();
            
            if (empty($staffWithReturnedMemos)) {
                Log::info('No staff found with returned memos. Skipping notifications.');
                return;
            }

            Log::info("Found " . count($staffWithReturnedMemos) . " staff with returned memos");

            $totalReturned = 0;
            $staffWithReturned = 0;

            Log::info("Starting to process staff members...");

            foreach ($staffWithReturnedMemos as $index => $staffData) {
                Log::info("Processing staff member #{$index}");
                try {
                    // Ensure we have array access
                    if (is_object($staffData)) {
                        $staffData = (array) $staffData;
                    }
                    
                    $staffId = $staffData['staff_id'];
                    $staffEmail = $staffData['work_email'];
                    $staffName = $staffData['fname'] . ' ' . $staffData['lname'];

                    Log::info("Processing staff: {$staffName} ({$staffEmail}) - ID: {$staffId}");

                    $sessionData = [
                        'staff_id' => $staffId,
                        'division_id' => $staffData['division_id'] ?? null,
                        'permissions' => [],
                        'name' => $staffName,
                        'email' => $staffEmail,
                        'base_url' => config('app.url')
                    ];

                    $returnedMemosService = new ReturnedMemosService($sessionData);
                    $summaryStats = $returnedMemosService->getSummaryStats();
                    $returnedItems = $returnedMemosService->getReturnedMemos();

                    Log::info("Staff {$staffName} returned count: {$summaryStats['total_returned']}");

                    if ($summaryStats['total_returned'] > 0) {
                        $staffWithReturned++;
                        $totalReturned += $summaryStats['total_returned'];
                        
                        // Get the actual Staff model (like the working daily pending approvals)
                        $staffModel = \App\Models\Staff::find($staffId);
                        
                        if (!$staffModel) {
                            Log::warning("Staff model not found for ID: {$staffId}");
                            continue;
                        }
                        
                        // Dispatch notification email job using the same approach as pending approvals
                        SendNotificationEmailJob::dispatch(
                            (object)['id' => 'returned-memos', 'type' => 'returned_memos'],
                            $staffModel,
                            'returned_memos',
                            'You have returned memos that require your attention.',
                            'emails.returned-memos-notification'
                        );
                        
                        Log::info("Returned memos notification job dispatched for {$staffEmail} with {$summaryStats['total_returned']} items.");
                    } else {
                        Log::info("No returned memos for {$staffEmail}. Skipping notification.");
                    }
                } catch (\Exception $e) {
                    Log::error("Error processing staff {$staffData['fname']} {$staffData['lname']}: " . $e->getMessage(), [
                        'trace' => $e->getTraceAsString(),
                        'staff_data' => $staffData
                    ]);
                }
            }

            Log::info('SendReturnedMemosNotificationJob completed successfully.', [
                'total_staff_checked' => count($staffWithReturnedMemos),
                'staff_with_returned' => $staffWithReturned,
                'total_returned_items' => $totalReturned
            ]);

        } catch (\Exception $e) {
            Log::error('SendReturnedMemosNotificationJob failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * Get all staff who have returned memos (initiators, not approvers)
     * Based on the same logic as ReturnedMemosService
     */
    private function getAllStaffWithReturnedMemos(): array
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

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendReturnedMemosNotificationJob failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}

