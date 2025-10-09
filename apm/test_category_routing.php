<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Matrix;
use App\Models\Division;
use App\Services\ApprovalService;
use App\Models\WorkflowDefinition;

echo "=== Testing Category-Based Routing from Approval Level 6 ===\n\n";

try {
    $approvalService = new ApprovalService();
    
    // Test each division category
    $testCategories = ['Operations', 'Programs', 'Other'];
    
    foreach ($testCategories as $category) {
        echo "=== Testing Division Category: {$category} ===\n";
        
        // Create a mock matrix for this category
        $matrix = new Matrix();
        $matrix->id = 999; // Mock ID
        $matrix->approval_level = 6; // Start from Director Finance level
        $matrix->forward_workflow_id = 1;
        $matrix->overall_status = 'pending';
        
        // Create a mock division
        $division = new Division();
        $division->category = $category;
        $division->name = "Test {$category} Division";
        $matrix->setRelation('division', $division);
        
        // Set funding types (mixed for testing)
        $matrix->setAttribute('has_intramural', true);
        $matrix->setAttribute('has_extramural', true);
        $matrix->setAttribute('has_external_source', false);
        
        echo "Starting from approval level: {$matrix->approval_level}\n";
        echo "Division category: {$division->category}\n";
        echo "Has intramural: " . ($matrix->has_intramural ? 'Yes' : 'No') . "\n";
        echo "Has extramural: " . ($matrix->has_extramural ? 'Yes' : 'No') . "\n\n";
        
        // Test the routing
        $nextApprover = $approvalService->getNextApprover($matrix);
        
        if ($nextApprover) {
            echo "✅ Next Approver Found:\n";
            echo "  - Role: {$nextApprover->role}\n";
            echo "  - Approval Order: {$nextApprover->approval_order}\n";
            echo "  - Fund Type: {$nextApprover->fund_type}\n";
            echo "  - Category: {$nextApprover->category}\n";
            echo "  - Is Division Specific: " . ($nextApprover->is_division_specific ? 'Yes' : 'No') . "\n";
            
            // Verify the routing is correct
            $expectedOrder = null;
            $expectedRole = null;
            
            switch ($category) {
                case 'Operations':
                    $expectedOrder = 7;
                    $expectedRole = 'Head of Operations';
                    break;
                case 'Programs':
                    $expectedOrder = 8;
                    $expectedRole = 'Head of Programs';
                    break;
                case 'Other':
                    $expectedOrder = 9;
                    $expectedRole = 'Deputy Director General (DDG)';
                    break;
            }
            
            if ($nextApprover->approval_order == $expectedOrder && 
                strpos($nextApprover->role, $expectedRole) !== false) {
                echo "  ✅ ROUTING CORRECT: Expected {$expectedRole} (order {$expectedOrder})\n";
            } else {
                echo "  ❌ ROUTING INCORRECT: Expected {$expectedRole} (order {$expectedOrder}), got {$nextApprover->role} (order {$nextApprover->approval_order})\n";
            }
        } else {
            echo "❌ No next approver found!\n";
        }
        
        echo "\n" . str_repeat("-", 50) . "\n\n";
    }
    
    // Test the second step for Operations and Programs (should go to DDG)
    echo "=== Testing Second Step (Operations/Programs → DDG) ===\n";
    
    $secondStepCategories = ['Operations', 'Programs'];
    
    foreach ($secondStepCategories as $category) {
        echo "=== Testing {$category} → DDG ===\n";
        
        // Create a mock matrix at the appropriate level
        $matrix = new Matrix();
        $matrix->id = 999;
        $matrix->approval_level = ($category === 'Operations') ? 7 : 8;
        $matrix->forward_workflow_id = 1;
        $matrix->overall_status = 'pending';
        
        $division = new Division();
        $division->category = $category;
        $division->name = "Test {$category} Division";
        $matrix->setRelation('division', $division);
        
        $matrix->setAttribute('has_intramural', true);
        $matrix->setAttribute('has_extramural', true);
        $matrix->setAttribute('has_external_source', false);
        
        echo "Starting from approval level: {$matrix->approval_level}\n";
        echo "Division category: {$division->category}\n\n";
        
        $nextApprover = $approvalService->getNextApprover($matrix);
        
        if ($nextApprover) {
            echo "✅ Next Approver Found:\n";
            echo "  - Role: {$nextApprover->role}\n";
            echo "  - Approval Order: {$nextApprover->approval_order}\n";
            
            if ($nextApprover->approval_order == 9 && 
                strpos($nextApprover->role, 'Deputy Director General') !== false) {
                echo "  ✅ ROUTING CORRECT: Expected DDG (order 9)\n";
            } else {
                echo "  ❌ ROUTING INCORRECT: Expected DDG (order 9), got {$nextApprover->role} (order {$nextApprover->approval_order})\n";
            }
        } else {
            echo "❌ No next approver found!\n";
        }
        
        echo "\n" . str_repeat("-", 50) . "\n\n";
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
