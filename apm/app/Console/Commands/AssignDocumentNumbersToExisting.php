<?php

namespace App\Console\Commands;

use App\Models\Matrix;
use App\Models\Activity;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use App\Models\ServiceRequest;
use App\Models\RequestARF;
use App\Services\DocumentNumberService;
use Illuminate\Console\Command;

class AssignDocumentNumbersToExisting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assign:document-numbers {--dry-run : Show what would be assigned without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign document numbers to existing records that don\'t have them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $this->info('Assigning document numbers to existing records...');
        $this->newLine();

        $totalAssigned = 0;

        // Process each model type
        $models = [
            'Matrix' => Matrix::class,
            'Activity' => Activity::class,
            'NonTravelMemo' => NonTravelMemo::class,
            'SpecialMemo' => SpecialMemo::class,
            'ServiceRequest' => ServiceRequest::class,
            'RequestARF' => RequestARF::class,
        ];

        foreach ($models as $name => $modelClass) {
            $this->info("Processing {$name}...");
            
            // Get records without document numbers
            $records = $modelClass::whereNull('document_number')->get();
            $count = $records->count();
            
            if ($count === 0) {
                $this->line("  No records need document numbers");
                continue;
            }

            $this->line("  Found {$count} records without document numbers");

            if (!$isDryRun) {
                $bar = $this->output->createProgressBar($count);
                $bar->start();

                foreach ($records as $record) {
                    try {
                        $documentNumber = DocumentNumberService::generateForAnyModel($record);
                        $record->update(['document_number' => $documentNumber]);
                        $totalAssigned++;
                    } catch (\Exception $e) {
                        $this->error("  Failed to assign document number to {$name} ID {$record->id}: " . $e->getMessage());
                    }
                    
                    $bar->advance();
                }

                $bar->finish();
                $this->newLine();
            } else {
                // Show preview for dry run
                $sample = $records->take(3);
                foreach ($sample as $record) {
                    try {
                        $documentNumber = DocumentNumberService::generateForAnyModel($record);
                        $this->line("  Would assign: {$documentNumber}");
                    } catch (\Exception $e) {
                        $this->error("  Would fail: " . $e->getMessage());
                    }
                }
                
                if ($count > 3) {
                    $this->line("  ... and " . ($count - 3) . " more");
                }
            }

            $this->newLine();
        }

        if ($isDryRun) {
            $this->warn("Dry run completed. Run without --dry-run to assign document numbers.");
        } else {
            $this->info("Document number assignment completed!");
            $this->info("Total assigned: {$totalAssigned}");
        }

        return 0;
    }
}