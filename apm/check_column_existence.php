<?php

/**
 * Script to check if columns exist before running migrations
 * Run this on the server: php check_column_existence.php
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Checking Column Existence ===\n\n";

try {
    // Check workflow_definition table columns
    $columns = DB::select("SHOW COLUMNS FROM workflow_definition");
    
    echo "Columns in workflow_definition table:\n";
    foreach ($columns as $column) {
        echo "  - {$column->Field} ({$column->Type})";
        if ($column->Null === 'NO') {
            echo " NOT NULL";
        }
        if ($column->Default !== null) {
            echo " DEFAULT '{$column->Default}'";
        }
        echo "\n";
    }
    
    echo "\n";
    
    // Check specific columns that might cause migration issues
    $problematicColumns = [
        'memo_print_section',
        'print_order'
    ];
    
    foreach ($problematicColumns as $columnName) {
        $exists = DB::select("SHOW COLUMNS FROM workflow_definition LIKE '{$columnName}'");
        if (!empty($exists)) {
            $column = $exists[0];
            echo "✓ Column '{$columnName}' exists: {$column->Type}";
            if ($column->Default !== null) {
                echo " DEFAULT '{$column->Default}'";
            }
            echo "\n";
        } else {
            echo "✗ Column '{$columnName}' does not exist\n";
        }
    }
    
    echo "\n=== Check completed successfully ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
