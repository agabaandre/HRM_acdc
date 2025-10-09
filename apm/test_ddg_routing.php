<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Matrix;
use App\Services\ApprovalService;
use App\Models\WorkflowDefinition;

echo "=== Testing DDG Routing and Beyond ===\n\n";

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
    echo "Division: {$matrix->division->name} (Category: {$matrix->division->category})\n\n";
    
    // Test what happens at different levels after DDG
    $testLevels = [9, 10, 11, 12];
    
    foreach ($testLevels as $level) {
        echo "--- Testing at approval level {$level} ---\n";
        
        // Create a copy of the matrix with different approval level
        $testMatrix = clone $matrix;
        $testMatrix->approval_level = $level;
        
        $testNextApprover = $approvalService->getNextApprover($testMatrix);
        
        if ($testNextApprover) {
            echo "  Next: {$testNextApprover->role} (Order: {$testNextApprover->approval_order})\n";
            echo "  Fund Type: {$testNextApprover->fund_type}\n";
            echo "  Category: {$testNextApprover->category}\n";
        } else {
            echo "  Next: None found (End of workflow)\n";
        }
        echo "\n";
    }
    
    // Show available workflow definitions for reference
    echo "=== Available Workflow Definitions ===\n";
    $workflowDefinitions = WorkflowDefinition::where('workflow_id', 1)
        ->where('is_enabled', 1)
        ->orderBy('approval_order')
        ->get();
        
    foreach ($workflowDefinitions as $def) {
        echo "Order {$def->approval_order}: {$def->role} - Fund Type: {$def->fund_type}, Category: {$def->category}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
