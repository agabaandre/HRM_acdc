<?php

namespace App\Console\Commands;

use App\Services\DocumentNumberService;
use App\Jobs\AssignDocumentNumberJob;
use App\Models\Matrix;
use App\Models\Activity;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use App\Models\ServiceRequest;
use App\Models\RequestARF;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AssignMissingDocumentNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assign:missing-document-numbers 
                            {--dry-run : Show what would be assigned without making changes}
                            {--table= : Assign numbers for specific table only}
                            {--force : Force assignment even if document numbers exist}
                            {--user=558 : User ID to use for session and audit logging}
                            {--queue : Dispatch jobs to queue instead of immediate assignment}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign document numbers to records that are missing them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $specificTable = $this->option('table');
        $force = $this->option('force');
        $userId = $this->option('user');
        $useQueue = $this->option('queue');

        $this->info('🔍 Scanning for records without document numbers...');
        $this->info("Using user ID: {$userId} for reference (background jobs don't use sessions)");

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $tables = [
            'matrices' => [
                'model' => Matrix::class,
                'name' => 'Matrices',
                'document_type' => null // Matrices don't get document numbers
            ],
            'activities' => [
                'model' => Activity::class,
                'name' => 'Activities',
                'document_type' => 'auto' // Will be determined by is_single_memo field
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

        $totalProcessed = 0;
        $totalAssigned = 0;
        $errors = 0;

        foreach ($tables as $tableName => $config) {
            // Skip if specific table requested and this isn't it
            if ($specificTable && $specificTable !== $tableName) {
                continue;
            }

            // Skip matrices as they don't get document numbers
            if ($tableName === 'matrices') {
                $this->line("Skipping {$config['name']} (no document numbers assigned)");
                continue;
            }

            $this->info("Processing {$config['name']}...");

            try {
                $query = $config['model']::query();
                
                if (!$force) {
                    $query->whereNull('document_number');
                }

                $records = $query->get();
                $totalProcessed += $records->count();

                if ($records->isEmpty()) {
                    $this->line("  ✅ All {$config['name']} already have document numbers");
                    continue;
                }

                $this->line("  Found {$records->count()} records without document numbers");

                $assigned = 0;
                foreach ($records as $record) {
                    try {
                        if ($isDryRun) {
                            $this->line("    Would assign document number to {$config['name']} ID: {$record->id}");
                            $assigned++;
                            continue;
                        }

                        if ($useQueue) {
                            // Dispatch job to queue for proper processing
                            $documentType = $this->getDocumentType($record, $config['document_type']);
                            if ($documentType) {
                                AssignDocumentNumberJob::dispatch($record, $documentType);
                                $this->line("    📤 Dispatched job for {$config['name']} ID: {$record->id} (Type: {$documentType})");
                                $assigned++;
                            } else {
                                $this->error("    ❌ Could not determine document type for {$config['name']} ID: {$record->id}");
                                $errors++;
                            }
                        } else {
                            // Direct assignment using the service
                            $documentNumber = $this->generateDocumentNumber($record, $config['document_type']);
                            
                            if ($documentNumber) {
                                $record->update(['document_number' => $documentNumber]);
                                $this->line("    ✅ Assigned: {$documentNumber} to {$config['name']} ID: {$record->id}");
                                $assigned++;
                            } else {
                                $this->error("    ❌ Failed to generate document number for {$config['name']} ID: {$record->id}");
                                $errors++;
                            }
                        }

                    } catch (\Exception $e) {
                        $this->error("    ❌ Error processing {$config['name']} ID: {$record->id}: " . $e->getMessage());
                        $errors++;
                        try {
                            Log::error("Document number assignment failed", [
                                'table' => $tableName,
                                'record_id' => $record->id,
                                'error' => $e->getMessage()
                            ]);
                        } catch (\Exception $logException) {
                            // Ignore logging errors
                        }
                    }
                }

                $totalAssigned += $assigned;
                $this->line("  {$config['name']}: {$assigned} document numbers assigned");

            } catch (\Exception $e) {
                $this->error("Error processing {$config['name']}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->newLine();
        if ($isDryRun) {
            $this->info("Would assign {$totalAssigned} document numbers to {$totalProcessed} records");
        } else {
            if ($useQueue) {
                $this->info("✅ Jobs dispatched to queue!");
                $this->info("Processed: {$totalProcessed} records");
                $this->info("Jobs dispatched: {$totalAssigned}");
                $this->info("Note: Document numbers will be assigned as jobs are processed by the queue worker");
            } else {
                $this->info("✅ Assignment complete!");
                $this->info("Processed: {$totalProcessed} records");
                $this->info("Assigned: {$totalAssigned} document numbers");
            }
            if ($errors > 0) {
                $this->warn("Errors: {$errors}");
            }
        }

        return 0;
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

    /**
     * Generate document number for a record
     */
    private function generateDocumentNumber($record, $documentType)
    {
        try {
            if ($documentType === 'auto') {
                // For activities, determine document type based on is_single_memo
                if (isset($record->is_single_memo) && $record->is_single_memo == 1) {
                    $documentType = 'SM';
                } else {
                    $documentType = 'QM';
                }
            }

            if ($documentType === null) {
                return null;
            }

            // Use direct generation instead of generateForModel to avoid audit logging
            $divisionId = null;
            $divisionShortName = null;
            
            // Try to get division info from the record
            if (isset($record->division_id) && $record->division_id) {
                $divisionId = $record->division_id;
            } elseif (isset($record->matrix_id) && $record->matrix_id) {
                // For activities, get division through matrix
                $matrix = \App\Models\Matrix::find($record->matrix_id);
                if ($matrix && $matrix->division_id) {
                    $divisionId = $matrix->division_id;
                    $divisionShortName = $matrix->division ? $matrix->division->division_short_name : null;
                }
            }
            
            return DocumentNumberService::generateDocumentNumber($documentType, $divisionShortName, $divisionId);

        } catch (\Exception $e) {
            try {
                Log::error("Document number generation failed", [
                    'record_id' => $record->id,
                    'record_type' => get_class($record),
                    'document_type' => $documentType,
                    'error' => $e->getMessage()
                ]);
            } catch (\Exception $logException) {
                // Ignore logging errors
            }
            return null;
        }
    }
}