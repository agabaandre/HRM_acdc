<?php

namespace App\Jobs;

use App\Models\DocumentCounter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResetDocumentCountersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $year;
    public $divisionShortName;
    public $documentType;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $year = null, ?string $divisionShortName = null, ?string $documentType = null)
    {
        $this->year = $year ?? date('Y');
        $this->divisionShortName = $divisionShortName;
        $this->documentType = $documentType;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $query = DocumentCounter::query();

            // Filter by year
            $query->where('year', $this->year);

            // Filter by division if specified
            if ($this->divisionShortName) {
                $query->where('division_short_name', $this->divisionShortName);
            }

            // Filter by document type if specified
            if ($this->documentType) {
                $query->where('document_type', $this->documentType);
            }

            $counters = $query->get();
            $resetCount = 0;

            foreach ($counters as $counter) {
                $counter->update(['counter' => 0]);
                $resetCount++;
                
                Log::info("Reset counter for {$counter->division_short_name} - {$counter->document_type} - {$counter->year} to 0");
            }

            Log::info("Document counters reset completed", [
                'year' => $this->year,
                'division' => $this->divisionShortName,
                'document_type' => $this->documentType,
                'reset_count' => $resetCount
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to reset document counters", [
                'error' => $e->getMessage(),
                'year' => $this->year,
                'division' => $this->divisionShortName,
                'document_type' => $this->documentType
            ]);
            
            throw $e;
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'reset-counters',
            'year:' . $this->year,
            'division:' . ($this->divisionShortName ?? 'all'),
            'type:' . ($this->documentType ?? 'all')
        ];
    }
}
