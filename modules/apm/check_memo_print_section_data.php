<?php

/**
 * Script to check memo_print_section data before running migration
 * Run this on the server: php check_memo_print_section_data.php
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Checking memo_print_section data ===\n\n";

try {
    // Check if table exists
    $tableExists = DB::select("SHOW TABLES LIKE 'workflow_definition'");
    if (empty($tableExists)) {
        echo "ERROR: workflow_definition table does not exist!\n";
        exit(1);
    }
    
    // Check current column definition
    $columnInfo = DB::select("SHOW COLUMNS FROM workflow_definition LIKE 'memo_print_section'");
    
    if (empty($columnInfo)) {
        echo "ERROR: memo_print_section column does not exist!\n";
        exit(1);
    }
    
    $currentType = $columnInfo[0]->Type;
    $currentDefault = $columnInfo[0]->Default;
    $currentNull = $columnInfo[0]->Null;
    
    echo "Current column definition:\n";
    echo "  Type: {$currentType}\n";
    echo "  Default: " . ($currentDefault ?: 'NULL') . "\n";
    echo "  Null: {$currentNull}\n\n";
    
    // Get all unique values in the column
    $uniqueValues = DB::table('workflow_definition')
        ->select('memo_print_section')
        ->distinct()
        ->get()
        ->pluck('memo_print_section')
        ->toArray();
    
    echo "Current values in column:\n";
    foreach ($uniqueValues as $value) {
        $count = DB::table('workflow_definition')
            ->where('memo_print_section', $value)
            ->count();
        echo "  '{$value}': {$count} rows\n";
    }
    echo "\n";
    
    // Check for invalid values
    $validValues = ['from', 'to', 'through', 'others'];
    $invalidValues = array_diff($uniqueValues, $validValues);
    
    if (!empty($invalidValues)) {
        echo "WARNING: Found invalid values that need to be fixed:\n";
        foreach ($invalidValues as $invalidValue) {
            echo "  '{$invalidValue}'\n";
        }
        echo "\n";
        echo "RECOMMENDATION: Run the data fix migration first:\n";
        echo "php artisan migrate --path=database/migrations/2025_09_03_170907_fix_memo_print_section_data_before_enum_update.php\n\n";
    } else {
        echo "✓ All values are valid for the new enum definition\n\n";
    }
    
    // Check if column already has the correct definition
    if (strpos($currentType, "'through'") !== false && $currentDefault === 'through') {
        echo "✓ Column already has the correct definition with 'through' option and default\n";
        echo "The main migration will be skipped automatically.\n";
    } else {
        echo "Column needs to be updated to include 'through' option and set default\n";
        echo "You can safely run the main migration now.\n";
    }
    
    echo "\n=== Check completed successfully ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
