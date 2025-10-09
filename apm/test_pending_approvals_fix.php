<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\PendingApprovalsService;
use App\Models\Staff;

echo "=== Testing Pending Approvals Fix ===\n\n";

try {
    // Test with a specific user (staff ID 484 - finance officer)
    $testStaffId = 484;
    
    echo "Testing with Staff ID: {$testStaffId}\n";
    
    $staff = Staff::find($testStaffId);
    if (!$staff) {
        echo "❌ Staff not found\n";
        exit;
    }
    
    echo "Staff: {$staff->fname} {$staff->lname}\n";
    echo "Email: {$staff->work_email}\n";
    echo "Division: " . ($staff->division ? $staff->division->name : 'None') . "\n\n";
    
    // Create PendingApprovalsService instance
    $sessionData = [
        'staff_id' => $testStaffId,
        'division_id' => $staff->division_id ?? null,
        'permissions' => [], // Empty permissions for testing
    ];
    $pendingService = new PendingApprovalsService($sessionData);
    
    // Test each category
    $categories = ['matrices', 'non_travel_memos', 'special_memos', 'single_memos', 'service_requests', 'change_requests'];
    
    foreach ($categories as $category) {
        echo "=== Testing {$category} ===\n";
        
        $pendingItems = $pendingService->getPendingByCategory($category);
        $count = $pendingItems->count();
        
        echo "Pending count: {$count}\n";
        
        if ($count > 0) {
            echo "Sample items:\n";
            foreach ($pendingItems->take(3) as $item) {
                $itemType = class_basename($item);
                $itemId = $item->id;
                $status = $item->overall_status ?? 'unknown';
                $level = $item->approval_level ?? 0;
                
                echo "  - {$itemType} ID {$itemId}: Status={$status}, Level={$level}\n";
            }
        }
        
        echo "\n";
    }
    
    // Calculate total pending count
    $totalPending = 0;
    foreach ($categories as $category) {
        $totalPending += $pendingService->getPendingByCategory($category)->count();
    }
    echo "Total pending count: {$totalPending}\n";
    
    echo "\n✅ Pending approvals test completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
