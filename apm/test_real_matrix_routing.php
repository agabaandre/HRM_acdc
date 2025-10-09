<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Matrix;
use App\Services\ApprovalService;

echo "=== Testing Real Matrix Category Routing ===\n\n";

try {
    $approvalService = new ApprovalService();
    
    // Test with the actual matrix ID 1
    $matrix = Matrix::with(['division', 'forwardWorkflow'])->find(1);
    
    if (!$matrix) {
        echo "❌ Matrix not found!\n";
        exit;
    }
    
    echo "Matrix ID: {$matrix->id}\n";
    echo "Current Approval Level: {$matrix->approval_level}\n";
    echo "Division: {$matrix->division->name} (Category: {$matrix->division->category})\n";
    echo "Overall Status: {$matrix->overall_status}\n\n";
    
    // Test the current routing
    $nextApprover = $approvalService->getNextApprover($matrix);
    
    if ($nextApprover) {
        echo "✅ Current Next Approver:\n";
        echo "  - Role: {$nextApprover->role}\n";
        echo "  - Approval Order: {$nextApprover->approval_order}\n";
        echo "  - Category: {$nextApprover->category}\n\n";
    } else {
        echo "❌ No next approver found!\n\n";
    }
    
    // Test what would happen if we were at different levels
    echo "=== Testing Different Approval Levels ===\n";
    
    $testLevels = [6, 7, 8];
    
    foreach ($testLevels as $level) {
        echo "--- Testing at approval level {$level} ---\n";
        
        // Create a copy of the matrix with different approval level
        $testMatrix = clone $matrix;
        $testMatrix->approval_level = $level;
        
        $testNextApprover = $approvalService->getNextApprover($testMatrix);
        
        if ($testNextApprover) {
            echo "  Next: {$testNextApprover->role} (Order: {$testNextApprover->approval_order})\n";
        } else {
            echo "  Next: None found\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
