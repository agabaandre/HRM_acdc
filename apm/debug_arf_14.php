<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\RequestARF;

echo "=== Debugging ARF Request #14 ===\n\n";

try {
    $arf = RequestARF::find(14);
    
    if (!$arf) {
        echo "❌ ARF request #14 not found!\n";
        exit;
    }
    
    echo "ARF Details:\n";
    echo "  - ID: {$arf->id}\n";
    echo "  - ARF Number: {$arf->arf_number}\n";
    echo "  - Title: {$arf->activity_title}\n";
    echo "  - Requested Amount: {$arf->requested_amount}\n";
    echo "  - Total Amount: {$arf->total_amount}\n";
    echo "  - Model Type: {$arf->model_type}\n";
    echo "  - Source ID: {$arf->source_id}\n";
    echo "  - Source Type: {$arf->source_type}\n";
    echo "  - Fund Type ID: {$arf->fund_type_id}\n";
    echo "  - Funder ID: {$arf->funder_id}\n";
    echo "  - Extramural Code: {$arf->extramural_code}\n";
    echo "  - Status: {$arf->overall_status}\n\n";
    
    echo "Budget Breakdown (raw):\n";
    echo json_encode($arf->budget_breakdown, JSON_PRETTY_PRINT) . "\n\n";
    
    // Test the budget calculation logic from the view
    echo "=== Testing Budget Calculation Logic ===\n";
    
    $totalBudget = 0;
    $sourceData = null;
    
    // Get source data if it exists
    if ($arf->model_type && $arf->source_id) {
        $modelClass = $arf->model_type;
        if (class_exists($modelClass)) {
            $sourceData = $modelClass::find($arf->source_id);
            if ($sourceData) {
                echo "Source data found: {$modelClass} #{$arf->source_id}\n";
                echo "Source total budget: " . ($sourceData->total_budget ?? 'N/A') . "\n";
                echo "Source budget breakdown: " . json_encode($sourceData->budget_breakdown ?? null, JSON_PRETTY_PRINT) . "\n\n";
            }
        }
    }
    
    // Test the calculation logic from the view
    if ($arf->model_type === 'App\\Models\\Activity') {
        echo "Processing as Activity...\n";
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
                    echo "Found grand_total: {$totalBudget}\n";
                } else {
                    // Process individual items
                    foreach ($budgetItems as $key => $item) {
                        if ($key === 'grand_total') {
                            $totalBudget = floatval($item);
                        } elseif (is_array($item)) {
                            foreach ($item as $budgetItem) {
                                if (is_object($budgetItem)) {
                                    $totalBudget += $budgetItem->unit_cost * $budgetItem->units * $budgetItem->days;
                                } elseif (is_array($budgetItem)) {
                                    $totalBudget += floatval($budgetItem['unit_cost'] ?? 0) * floatval($budgetItem['units'] ?? 0) * floatval($budgetItem['days'] ?? 0);
                                }
                            }
                        }
                    }
                }
            } elseif (is_object($budgetItems) && method_exists($budgetItems, 'each')) {
                foreach ($budgetItems as $item) {
                    $totalBudget += $item->unit_cost * $item->units * $item->days;
                }
            }
        }
    } else {
        echo "Processing as other model type...\n";
        $budget = $sourceData['budget_breakdown'] ?? [];
        if (is_string($budget)) {
            $budget = json_decode($budget, true) ?? [];
        }
        if (!empty($budget) && is_array($budget)) {
            if (isset($budget[0]) && is_array($budget[0])) {
                foreach ($budget as $item) {
                    $totalBudget += floatval(
                        $item['total'] ?? ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1),
                    );
                }
            } else {
                foreach ($budget as $key => $item) {
                    if ($key === 'grand_total') {
                        $totalBudget = floatval($item);
                    } elseif (is_array($item)) {
                        foreach ($item as $budgetItem) {
                            $totalBudget += floatval(
                                $budgetItem['total'] ??
                                    ($budgetItem['unit_price'] ?? 0) * ($budgetItem['quantity'] ?? 1),
                            );
                        }
                    }
                }
            }
        }
    }
    
    echo "Calculated total budget: {$totalBudget}\n";
    
    // Test the display logic from the view
    $displayAmount = $arf->total_amount ?? ($totalBudget ?? ($sourceData['total_budget'] ?? 0));
    echo "Display amount (arf->total_amount ?? totalBudget ?? sourceData['total_budget']): {$displayAmount}\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
