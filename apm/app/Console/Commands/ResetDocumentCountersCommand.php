<?php

namespace App\Console\Commands;

use App\Jobs\ResetDocumentCountersJob;
use App\Models\DocumentCounter;
use Illuminate\Console\Command;

class ResetDocumentCountersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'document:reset-counters 
                            {--year= : Year to reset counters for (default: current year)}
                            {--division= : Division short name to reset (optional)}
                            {--type= : Document type to reset (optional)}
                            {--sync : Run synchronously instead of dispatching job}';

    /**
     * The console command description.
     */
    protected $description = 'Reset document counters for a specific year, division, or document type';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $year = $this->option('year') ? (int) $this->option('year') : date('Y');
        $division = $this->option('division');
        $type = $this->option('type');
        $sync = $this->option('sync');

        // Validate document type if provided
        if ($type && !array_key_exists($type, DocumentCounter::getDocumentTypes())) {
            $this->error("Invalid document type: {$type}");
            $this->info("Valid types: " . implode(', ', array_keys(DocumentCounter::getDocumentTypes())));
            return 1;
        }

        // Show what will be reset
        $this->info("Resetting document counters...");
        $this->info("Year: {$year}");
        $this->info("Division: " . ($division ?: 'All'));
        $this->info("Document Type: " . ($type ?: 'All'));

        // Show current counters before reset
        $query = DocumentCounter::where('year', $year);
        if ($division) $query->where('division_short_name', $division);
        if ($type) $query->where('document_type', $type);

        $currentCounters = $query->get();
        
        if ($currentCounters->isEmpty()) {
            $this->warn("No counters found for the specified criteria.");
            return 0;
        }

        $this->table(
            ['Division', 'Document Type', 'Year', 'Current Counter'],
            $currentCounters->map(function ($counter) {
                return [
                    $counter->division_short_name,
                    $counter->document_type,
                    $counter->year,
                    $counter->counter
                ];
            })
        );

        if (!$this->confirm('Are you sure you want to reset these counters to 0?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        if ($sync) {
            // Run synchronously
            $this->info('Running reset synchronously...');
            $job = new ResetDocumentCountersJob($year, $division, $type);
            $job->handle();
            $this->info('Counters reset successfully!');
        } else {
            // Dispatch job
            $this->info('Dispatching reset job...');
            ResetDocumentCountersJob::dispatch($year, $division, $type);
            $this->info('Reset job dispatched successfully!');
            $this->info('Check the queue worker logs to monitor progress.');
        }

        return 0;
    }
}
