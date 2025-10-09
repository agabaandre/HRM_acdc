<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Staff;
use App\Services\PendingApprovalsService;

echo "=== Testing All Memo Types for Current User ===\n\n";

try {
    // Get current user from session
    $staffId = user_session('staff_id');
    if (!$staffId) {
        echo "❌ No user session found. Please log in first.\n";
        exit;
    }
    
    $staff = Staff::find($staffId);
    if (!$staff) {
        echo "❌ Staff with ID {$staffId} not found.\n";
        exit;
    }
    
    echo "Staff: {$staff->fname} {$staff->lname}\n";
    echo "Staff ID: {$staffId}\n";
    echo "Division ID: {$staff->division_id}\n\n";
    
    // Test PendingApprovalsService
    $pendingService = new PendingApprovalsService([
        'staff_id' => $staffId,
        'division_id' => $staff->division_id,
        'permissions' => user_session('permissions', []),
        'name' => $staff->fname . ' ' . $staff->lname,
        'email' => $staff->work_email,
        'base_url' => config('app.url')
    ]);
    
    echo "=== PendingApprovalsService Results ===\n";
    $summaryStats = $pendingService->getSummaryStats();
    echo "Total pending: {$summaryStats['total_pending']}\n";
    echo "By category:\n";
    foreach ($summaryStats['by_category'] as $category => $count) {
        echo "  - {$category}: {$count}\n";
    }
    echo "\n";
    
    // Test each category individually
    $categories = ['Matrix', 'Special Memo', 'Non-Travel Memo', 'Single Memo', 'Service Request', 'ARF', 'Change Request'];
    
    foreach ($categories as $category) {
        echo "=== {$category} Details ===\n";
        $items = $pendingService->getPendingByCategory($category);
        echo "Count: " . $items->count() . "\n";
        
        if ($items->count() > 0) {
            foreach ($items as $item) {
                echo "  - ID: {$item['item_id']}, Title: " . substr($item['title'], 0, 50) . "...\n";
                echo "    Division: {$item['division']}, Level: {$item['approval_level']}\n";
            }
        }
        echo "\n";
    }
    
    // Test home page functions
    echo "=== Home Page Function Results ===\n";
    echo "Matrix count: " . get_pending_matrices_count($staffId) . "\n";
    echo "Special Memo count: " . get_pending_special_memo_count($staffId) . "\n";
    echo "Non-Travel Memo count: " . get_pending_non_travel_memo_count($staffId) . "\n";
    echo "Single Memo count: " . get_pending_single_memo_count($staffId) . "\n";
    echo "Service Request count: " . get_pending_service_requests_count($staffId) . "\n";
    echo "ARF Request count: " . get_pending_request_arf_count($staffId) . "\n";
    echo "Change Request count: " . get_pending_change_request_count($staffId) . "\n";
    echo "Total count: " . get_staff_total_pending_count($staffId) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
