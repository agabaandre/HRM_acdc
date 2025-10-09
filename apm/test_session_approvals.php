<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\PendingApprovalsService;

echo "=== Testing Session-Based Approvals ===\n\n";

try {
    // Get current user from session
    $staffId = user_session('staff_id');
    if (!$staffId) {
        echo "❌ No user session found. Please log in first.\n";
        echo "To test this, visit: http://localhost/staff/apm/test_session_approvals.php\n";
        exit;
    }
    
    echo "✅ User session found!\n";
    echo "Staff ID: {$staffId}\n";
    echo "Division ID: " . user_session('division_id') . "\n";
    echo "Name: " . user_session('name') . "\n\n";
    
    // Test PendingApprovalsService with session data
    $pendingService = new PendingApprovalsService();
    
    echo "=== PendingApprovalsService Results ===\n";
    $summaryStats = $pendingService->getSummaryStats();
    echo "Total pending: {$summaryStats['total_pending']}\n";
    echo "By category:\n";
    foreach ($summaryStats['by_category'] as $category => $count) {
        echo "  - {$category}: {$count}\n";
    }
    echo "\n";
    
    // Test home page functions (these should work without parameters when session exists)
    echo "=== Home Page Function Results (No Parameters) ===\n";
    echo "Matrix count: " . get_pending_matrices_count() . "\n";
    echo "Special Memo count: " . get_pending_special_memo_count() . "\n";
    echo "Non-Travel Memo count: " . get_pending_non_travel_memo_count() . "\n";
    echo "Single Memo count: " . get_pending_single_memo_count() . "\n";
    echo "Service Request count: " . get_pending_service_requests_count() . "\n";
    echo "ARF Request count: " . get_pending_request_arf_count() . "\n";
    echo "Change Request count: " . get_pending_change_request_count() . "\n";
    echo "Total count: " . get_staff_total_pending_count() . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
