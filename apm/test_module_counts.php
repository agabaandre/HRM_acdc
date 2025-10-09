<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Individual Module Counts for Staff 558 ===\n\n";

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
    
    // Test the specific functions directly
    echo "\n=== Direct Function Tests ===\n";
    echo "Non-Travel: " . get_pending_non_travel_memo_count($staffId) . "\n";
    echo "Special Memo: " . get_pending_special_memo_count($staffId) . "\n";
    echo "Single Memo: " . get_pending_single_memo_count($staffId) . "\n";
    echo "Matrix: " . get_pending_matrices_count($staffId) . "\n";
    echo "Service Request: " . get_pending_service_requests_count($staffId) . "\n";
    echo "ARF: " . get_pending_request_arf_count($staffId) . "\n";
    echo "Change Request: " . get_pending_change_request_count($staffId) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
