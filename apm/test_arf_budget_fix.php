<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\RequestARF;

echo "=== Testing ARF Budget Fix ===\n\n";

try {
    $arf = RequestARF::find(14);
    
    if (!$arf) {
        echo "❌ ARF request #14 not found!\n";
        exit;
    }
    
    echo "ARF #14: {$arf->activity_title}\n";
    echo "ARF Number: {$arf->arf_number}\n\n";
    
    // Test the budget calculation logic from the view
    $totalBudget = 0;
    
    if ($arf->model_type === 'App\\Models\\Activity') {
        $sourceData = \App\Models\Activity::find($arf->source_id);
        if ($sourceData) {
            $budgetItems = $sourceData->budget_breakdown ?? [];
            // Decode JSON string if needed
            if (is_string($budgetItems)) {
                $budgetItems = json_decode($budgetItems, true) ?? [];
            }
            if (!empty($budgetItems)) {
                if (is_array($budgetItems)) {
                    // Check if it has grand_total first
                    if (isset($budgetItems['grand_total'])) {
                        $totalBudget = floatval($budgetItems['grand_total']);
                    }
                }
            }
        }
    }
    
    echo "Calculated total budget: {$totalBudget}\n";
    echo "ARF total_amount: {$arf->total_amount}\n";
    echo "Display amount (totalBudget ?? total_amount): " . ($totalBudget ?? $arf->total_amount) . "\n";
    echo "Formatted display: $" . number_format($totalBudget ?? $arf->total_amount, 2) . "\n";
    
    if ($totalBudget > 0) {
        echo "✅ Budget fix is working! Total budget should now display as $" . number_format($totalBudget, 2) . "\n";
    } else {
        echo "❌ Budget fix is not working. Total budget is still 0.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
