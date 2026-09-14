<?php

namespace App\Console\Commands;

use App\Services\DocumentNumberService;
use App\Models\DocumentCounter;
use App\Models\Division;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixDocumentNumberConflicts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:document-conflicts 
                            {--dry-run : Show what would be fixed without making changes}
                            {--division= : Fix conflicts for specific division only}
                            {--reset-counters : Reset counters to next available numbers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix document number conflicts and reset counters after deletions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $specificDivision = $this->option('division');
        $resetCounters = $this->option('reset-counters');

        $this->info('🔍 Scanning for document number conflicts...');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Get divisions to check
        $divisions = $specificDivision 
            ? Division::where('id', $specificDivision)->get()
            : Division::all();

        if ($divisions->isEmpty()) {
            $this->error('No divisions found');
            return 1;
        }

        $conflictsFound = 0;
        $conflictsFixed = 0;

        foreach ($divisions as $division) {
            $this->info("Checking division: {$division->division_name} ({$division->division_short_name})");

            // Check for conflicts in each document type
            $documentTypes = [
                DocumentCounter::TYPE_QUARTERLY_MATRIX,
                DocumentCounter::TYPE_NON_TRAVEL_MEMO,
                DocumentCounter::TYPE_SPECIAL_MEMO,
                DocumentCounter::TYPE_SINGLE_MEMO,
                DocumentCounter::TYPE_SERVICE_REQUEST,
                DocumentCounter::TYPE_ARF,
            ];

            foreach ($documentTypes as $documentType) {
                $conflicts = $this->findConflicts($division->division_short_name, $documentType);
                
                if (!empty($conflicts)) {
                    $conflictsFound += count($conflicts);
                    $this->warn("Found " . count($conflicts) . " conflicts for {$documentType}");
                    
                    if (!$isDryRun) {
                        $fixed = $this->fixConflicts($conflicts, $documentType);
                        $conflictsFixed += $fixed;
                        $this->info("Fixed {$fixed} conflicts for {$documentType}");
                    } else {
                        $this->line("Would fix " . count($conflicts) . " conflicts for {$documentType}");
                    }
                }
            }

            // Reset counters if requested
            if ($resetCounters && !$isDryRun) {
                $this->info("Resetting counters for division: {$division->division_short_name}");
                foreach ($documentTypes as $documentType) {
                    DocumentNumberService::resetCounterAfterDeletion(
                        $documentType,
                        $division->division_short_name
                    );
                }
                $this->info("Counters reset for {$division->division_short_name}");
            }
        }

        $this->newLine();
        if ($conflictsFound > 0) {
            if ($isDryRun) {
                $this->info("Found {$conflictsFound} conflicts that would be fixed");
            } else {
                $this->info("Fixed {$conflictsFixed} out of {$conflictsFound} conflicts");
            }
        } else {
            $this->info("✅ No conflicts found!");
        }

        return 0;
    }

    /**
     * Find document number conflicts
     */
    private function findConflicts(string $divisionShortName, string $documentType): array
    {
        $tables = [
            'matrices',
            'activities', 
            'non_travel_memos',
            'special_memos',
            'service_requests',
            'request_arfs'
        ];

        $conflicts = [];

        foreach ($tables as $table) {
            $duplicates = DB::table($table)
                ->where('document_number', 'LIKE', "AU/CDC/{$divisionShortName}/IM/{$documentType}/%")
                ->select('document_number', DB::raw('COUNT(*) as count'))
                ->groupBy('document_number')
                ->having('count', '>', 1)
                ->get();

            foreach ($duplicates as $duplicate) {
                $conflicts[] = [
                    'table' => $table,
                    'document_number' => $duplicate->document_number,
                    'count' => $duplicate->count
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Fix document number conflicts
     */
    private function fixConflicts(array $conflicts, string $documentType): int
    {
        $fixed = 0;

        foreach ($conflicts as $conflict) {
            $table = $conflict['table'];
            $documentNumber = $conflict['document_number'];
            
            // Get all records with this document number
            $records = DB::table($table)
                ->where('document_number', $documentNumber)
                ->orderBy('id')
                ->get();

            // Keep the first record, fix the rest
            $keepFirst = true;
            foreach ($records as $record) {
                if ($keepFirst) {
                    $keepFirst = false;
                    continue;
                }

                // Generate new document number for this record
                $newDocumentNumber = DocumentNumberService::findNextAvailableNumber(
                    $documentType,
                    explode('/', $documentNumber)[2] // Extract division short name
                );

                // Update the record
                DB::table($table)
                    ->where('id', $record->id)
                    ->update(['document_number' => $newDocumentNumber]);

                $this->line("  Fixed: {$table} ID {$record->id} -> {$newDocumentNumber}");
                $fixed++;
            }
        }

        return $fixed;
    }
}