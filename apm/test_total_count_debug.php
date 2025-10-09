<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Total Count Debug for Staff 558 ===\n\n";

try {
    $staffId = 558;
    $modules = ['matrices', 'non-travel', 'special-memo', 'service-requests', 'request-arf', 'single-memo', 'change-request'];
    
    echo "Testing each module individually:\n";
    $total = 0;
    
    foreach ($modules as $module) {
        $count = get_staff_pending_action_count($module, $staffId);
        echo "  - {$module}: {$count}\n";
        $total += $count;
    }
    
    echo "\nManual total: {$total}\n";
    
    $functionTotal = get_staff_total_pending_count($staffId);
    echo "Function total: {$functionTotal}\n";
    
    // Test without staff ID parameter
    echo "\nTesting without staff ID parameter:\n";
    $functionTotalNoId = get_staff_total_pending_count();
    echo "Function total (no ID): {$functionTotalNoId}\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
