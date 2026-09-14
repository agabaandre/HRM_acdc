<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NonTravelMemo;
use App\Models\FundCode;

class UpdateNonTravelFundTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'non-travel:update-fund-types {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update fund_type_id for existing non-travel memos based on their budget_id column';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info('Starting fund_type_id update for non-travel memos...');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }
        
        // Get all non-travel memos that don't have fund_type_id set or have it as null
        $memos = NonTravelMemo::whereNull('fund_type_id')
            ->orWhere('fund_type_id', 0)
            ->get();
            
        $this->info("Found {$memos->count()} non-travel memos to process");
        
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        
        foreach ($memos as $memo) {
            try {
                // Parse the budget_id JSON
                $budgetIds = $this->parseBudgetIds($memo->budget_id);
                
                if (empty($budgetIds)) {
                    $this->warn("Memo ID {$memo->id}: No budget IDs found, skipping");
                    $skipped++;
                    continue;
                }
                
                // Get the first budget code to determine fund type
                $firstBudgetId = $budgetIds[0];
                $fundCode = FundCode::find($firstBudgetId);
                
                if (!$fundCode) {
                    $this->warn("Memo ID {$memo->id}: Budget code {$firstBudgetId} not found, skipping");
                    $skipped++;
                    continue;
                }
                
                if (!$fundCode->fund_type_id) {
                    $this->warn("Memo ID {$memo->id}: Budget code {$firstBudgetId} has no fund_type_id, skipping");
                    $skipped++;
                    continue;
                }
                
                $fundTypeName = $fundCode->fundType ? $fundCode->fundType->name : 'Unknown';
                $fundTypeId = $fundCode->fund_type_id;
                
                if ($isDryRun) {
                    $this->line("Memo ID {$memo->id}: Would update fund_type_id to {$fundTypeId} ({$fundTypeName})");
                } else {
                    $memo->update(['fund_type_id' => $fundTypeId]);
                    $this->line("Memo ID {$memo->id}: Updated fund_type_id to {$fundTypeId} ({$fundTypeName})");
                }
                
                $updated++;
                
            } catch (\Exception $e) {
                $this->error("Memo ID {$memo->id}: Error - " . $e->getMessage());
                $errors++;
            }
        }
        
        $this->newLine();
        $this->info("Update completed!");
        $this->info("Updated: {$updated}");
        $this->info("Skipped: {$skipped}");
        $this->info("Errors: {$errors}");
        
        if ($isDryRun) {
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        }
        
        return 0;
    }
    
    /**
     * Parse budget_id JSON string to array
     */
    private function parseBudgetIds($budgetIdJson)
    {
        if (is_array($budgetIdJson)) {
            return $budgetIdJson;
        }
        
        if (is_string($budgetIdJson)) {
            $decoded = json_decode($budgetIdJson, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        
        return [];
    }
}