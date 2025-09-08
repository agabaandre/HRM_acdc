<?php

namespace App\Console\Commands;

use App\Models\Matrix;
use App\Models\Division;
use Illuminate\Console\Command;

class TestDocumentCreation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:document-creation {--count=1 : Number of test documents to create}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test document creation and automatic document number assignment';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = (int) $this->option('count');
        
        $this->info("Creating {$count} test document(s)...");
        $this->newLine();
        
        // Get a division for testing
        $division = Division::whereNotNull('division_short_name')->first();
        
        if (!$division) {
            $this->error('No division with short name found. Please ensure divisions have short names.');
            return 1;
        }
        
        $this->info("Using Division: {$division->division_name} ({$division->division_short_name})");
        $this->newLine();
        
        $created = [];
        
        for ($i = 1; $i <= $count; $i++) {
            $this->line("Creating test document {$i}...");
            
            try {
                // Create a test matrix with unique quarter
                $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
                $quarter = $quarters[($i - 1) % 4];
                
                $matrix = Matrix::create([
                    'division_id' => $division->id,
                    'year' => date('Y'),
                    'quarter' => $quarter,
                    'key_result_area' => ['Test KRA 1', 'Test KRA 2'],
                    'staff_id' => 1, // Assuming staff ID 1 exists
                    'focal_person_id' => 1,
                    'forward_workflow_id' => 1,
                    'approval_level' => 1,
                    'overall_status' => 'draft',
                ]);
                
                $created[] = $matrix;
                
                $this->line("  ✅ Created Matrix ID: {$matrix->id}");
                $this->line("  📄 Document Number: " . ($matrix->document_number ?: 'Pending...'));
                
                // Wait a moment for job processing
                sleep(1);
                
                // Refresh to get updated document number
                $matrix->refresh();
                
                if ($matrix->document_number) {
                    $this->line("  ✅ Document Number Assigned: {$matrix->document_number}");
                } else {
                    $this->warn("  ⏳ Document Number Pending (job may still be processing)");
                }
                
            } catch (\Exception $e) {
                $this->error("  ❌ Failed to create document: " . $e->getMessage());
            }
            
            $this->newLine();
        }
        
        $this->info("Test completed!");
        $this->newLine();
        
        // Show summary
        $this->info("Summary:");
        $this->line("  Documents Created: " . count($created));
        $this->line("  With Document Numbers: " . collect($created)->filter(fn($m) => $m->document_number)->count());
        $this->line("  Pending: " . collect($created)->filter(fn($m) => !$m->document_number)->count());
        
        if (count($created) > 0) {
            $this->newLine();
            $this->info("Created Documents:");
            foreach ($created as $matrix) {
                $this->line("  Matrix #{$matrix->id}: " . ($matrix->document_number ?: 'Pending...'));
            }
        }
        
        $this->newLine();
        $this->info("💡 Tip: Run 'php artisan monitor:document-jobs' to watch job processing");
        
        return 0;
    }
}