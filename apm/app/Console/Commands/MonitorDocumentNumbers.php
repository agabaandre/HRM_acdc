<?php

namespace App\Console\Commands;

use App\Jobs\AssignDocumentNumberJob;
use App\Models\Activity;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use App\Models\ServiceRequest;
use App\Models\RequestARF;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorDocumentNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:document-numbers 
                            {--user=558 : User ID to use for session and audit logging}
                            {--auto-assign : Automatically assign document numbers to records that need them}
                            {--check-interval=300 : Check interval in seconds (default: 5 minutes)}
                            {--daemon : Run as daemon to continuously monitor}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor and automatically assign document numbers to records that need them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user');
        $autoAssign = $this->option('auto-assign');
        $checkInterval = (int) $this->option('check-interval');
        $daemon = $this->option('daemon');

        $this->info("🔍 Document Number Monitor Started");
        $this->info("Using user ID: {$userId} for reference (background jobs don't use sessions)");
        $this->info("Auto-assign: " . ($autoAssign ? 'Enabled' : 'Disabled'));
        
        if ($daemon) {
            $this->info("Running as daemon with {$checkInterval}s check interval");
        }

        do {
            $this->checkAndAssignDocumentNumbers($autoAssign);
            
            if ($daemon) {
                $this->line("⏰ Waiting {$checkInterval} seconds before next check...");
                sleep($checkInterval);
            }
        } while ($daemon);

        return 0;
    }

    /**
     * Check for records without document numbers and optionally assign them
     */
    private function checkAndAssignDocumentNumbers($autoAssign = false)
    {
        $this->line("🔍 Checking for records without document numbers...");

        $tables = [
            'activities' => [
                'model' => Activity::class,
                'name' => 'Activities',
                'document_type' => 'auto'
            ],
            'non_travel_memos' => [
                'model' => NonTravelMemo::class,
                'name' => 'Non-Travel Memos',
                'document_type' => 'NT'
            ],
            'special_memos' => [
                'model' => SpecialMemo::class,
                'name' => 'Special Memos',
                'document_type' => 'SPM'
            ],
            'service_requests' => [
                'model' => ServiceRequest::class,
                'name' => 'Service Requests',
                'document_type' => 'SR'
            ],
            'request_arfs' => [
                'model' => RequestARF::class,
                'name' => 'Request ARFs',
                'document_type' => 'ARF'
            ]
        ];

        $totalFound = 0;
        $totalAssigned = 0;
        $errors = 0;

        foreach ($tables as $tableName => $config) {
            try {
                $records = $config['model']::whereNull('document_number')->get();
                $count = $records->count();
                $totalFound += $count;

                if ($count > 0) {
                    $this->warn("  ⚠️  Found {$count} {$config['name']} without document numbers");
                    
                    if ($autoAssign) {
                        $assigned = 0;
                        foreach ($records as $record) {
                            try {
                                $documentType = $this->getDocumentType($record, $config['document_type']);
                                if ($documentType) {
                                    AssignDocumentNumberJob::dispatch($record, $documentType);
                                    $assigned++;
                                }
                            } catch (\Exception $e) {
                                $this->error("    ❌ Error dispatching job for {$config['name']} ID: {$record->id}: " . $e->getMessage());
                                $errors++;
                            }
                        }
                        
                        $totalAssigned += $assigned;
                        $this->info("    📤 Dispatched {$assigned} jobs for {$config['name']}");
                    } else {
                        // Show which records need document numbers
                        foreach ($records->take(5) as $record) {
                            $this->line("    - ID: {$record->id}, Title: " . substr($record->activity_title ?? $record->title ?? 'N/A', 0, 50) . "...");
                        }
                        if ($count > 5) {
                            $this->line("    ... and " . ($count - 5) . " more");
                        }
                    }
                } else {
                    $this->line("  ✅ All {$config['name']} have document numbers");
                }

            } catch (\Exception $e) {
                $this->error("Error checking {$config['name']}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->newLine();
        if ($totalFound > 0) {
            if ($autoAssign) {
                $this->info("📊 Summary: Found {$totalFound} records, dispatched {$totalAssigned} jobs");
                if ($errors > 0) {
                    $this->warn("⚠️  {$errors} errors occurred during job dispatch");
                }
            } else {
                $this->warn("📊 Summary: Found {$totalFound} records without document numbers");
                $this->info("💡 Run with --auto-assign to automatically assign document numbers");
            }
        } else {
            $this->info("✅ All records have document numbers assigned");
        }

        return $totalFound;
    }

    /**
     * Get document type for a record
     */
    private function getDocumentType($record, $documentType)
    {
        if ($documentType === 'auto') {
            // For activities, determine document type based on is_single_memo
            if (isset($record->is_single_memo) && $record->is_single_memo == 1) {
                return 'SM';
            } else {
                return 'QM';
            }
        }

        return $documentType;
    }
}