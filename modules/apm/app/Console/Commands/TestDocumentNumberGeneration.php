<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\DocumentCounter;
use App\Services\DocumentNumberService;
use Illuminate\Console\Command;

class TestDocumentNumberGeneration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:document-numbers {--division= : Division ID to test with}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test document number generation system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Document Number Generation System');
        $this->newLine();

        // Get division
        $divisionId = $this->option('division');
        if ($divisionId) {
            $division = Division::find($divisionId);
        } else {
            $division = Division::first();
        }

        if (!$division) {
            $this->error('No division found. Please create a division first or specify --division=ID');
            return 1;
        }

        $this->info("Using Division: {$division->division_name}");
        $this->info("Division Short Name: " . ($division->division_short_name ?: 'NOT SET'));
        
        if (!$division->division_short_name) {
            $this->error('Division does not have a short name set. Please set division_short_name first.');
            $this->info('You can generate short names using: php artisan settings:force_generate_short_names');
            return 1;
        }
        
        $this->newLine();

        // Test document type generation
        $documentTypes = DocumentCounter::getDocumentTypes();
        
        foreach ($documentTypes as $type => $name) {
            $this->info("Testing {$name} ({$type}):");
            
            // Generate preview
            $preview = DocumentNumberService::getNextNumberPreview($type, $division);
            $this->line("  Preview: {$preview}");
            
            // Generate actual number
            $actual = DocumentNumberService::generateDocumentNumber($type, $division->division_short_name, $division->id);
            $this->line("  Generated: {$actual}");
            
            // Validate
            $isValid = DocumentNumberService::validateDocumentNumber($actual);
            $this->line("  Valid: " . ($isValid ? 'Yes' : 'No'));
            
            $this->newLine();
        }

        // Show current counters
        $this->info('Current Division Counters:');
        $stats = DocumentCounter::getDivisionStats($division->division_short_name);
        foreach ($stats as $type => $count) {
            $typeName = $documentTypes[$type] ?? $type;
            $this->line("  {$typeName}: {$count}");
        }

        $this->newLine();
        $this->info('Document number generation test completed successfully!');
        
        return 0;
    }
}