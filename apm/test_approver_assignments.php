<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Staff;
use App\Models\Approver;
use App\Models\WorkflowDefinition;
use App\Models\NonTravelMemo;

echo "=== Approver Assignments for Staff 558 ===\n\n";

try {
    // Get staff 558
    $staff = Staff::find(558);
    echo "Staff: {$staff->fname} {$staff->lname}\n\n";
    
    // Check approver assignments
    echo "=== Approver Table Assignments ===\n";
    $approvers = Approver::where('staff_id', 558)->with('workflowDefinition')->get();
    echo "Total approver assignments: " . $approvers->count() . "\n\n";
    
    foreach ($approvers as $approver) {
        echo "Approver ID: {$approver->id}\n";
        echo "  - Staff ID: {$approver->staff_id}\n";
        echo "  - Workflow Definition ID: {$approver->workflow_dfn_id}\n";
        if ($approver->workflowDefinition) {
            echo "  - Role: {$approver->workflowDefinition->role}\n";
            echo "  - Approval Order: {$approver->workflowDefinition->approval_order}\n";
            echo "  - Workflow ID: {$approver->workflowDefinition->workflow_id}\n";
            echo "  - Is Division Specific: " . ($approver->workflowDefinition->is_division_specific ? 'Yes' : 'No') . "\n";
        }
        echo "\n";
    }
    
    // Check workflow definitions for non-travel memos
    echo "=== Workflow Definitions for Non-Travel Memos ===\n";
    $nonTravelWorkflowId = \App\Models\WorkflowModel::getWorkflowIdForModel('NonTravelMemo');
    echo "Non-Travel Workflow ID: {$nonTravelWorkflowId}\n\n";
    
    $workflowDefs = WorkflowDefinition::where('workflow_id', $nonTravelWorkflowId)
        ->where('is_division_specific', 0)
        ->orderBy('approval_order')
        ->get();
    
    echo "Non-division-specific workflow definitions:\n";
    foreach ($workflowDefs as $def) {
        echo "  - Order {$def->approval_order}: {$def->role} (ID: {$def->id})\n";
    }
    echo "\n";
    
    // Check if staff 558 is assigned to any of these workflow definitions
    echo "=== Staff 558 Assignment Check ===\n";
    $assignedDefIds = $approvers->pluck('workflow_dfn_id')->toArray();
    $nonTravelDefIds = $workflowDefs->pluck('id')->toArray();
    
    echo "Staff 558 assigned to workflow definitions: " . implode(', ', $assignedDefIds) . "\n";
    echo "Non-travel workflow definition IDs: " . implode(', ', $nonTravelDefIds) . "\n";
    
    $intersection = array_intersect($assignedDefIds, $nonTravelDefIds);
    echo "Intersection (staff assigned to non-travel definitions): " . implode(', ', $intersection) . "\n";
    
    if (empty($intersection)) {
        echo "❌ Staff 558 is NOT assigned to any non-travel workflow definitions!\n";
        echo "This explains why they can only see division-specific memos.\n";
    } else {
        echo "✅ Staff 558 IS assigned to non-travel workflow definitions.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
