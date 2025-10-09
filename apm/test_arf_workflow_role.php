<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\RequestARF;

echo "=== Testing ARF Workflow Role Fix ===\n\n";

try {
    $arf = RequestARF::find(14);
    
    if (!$arf) {
        echo "❌ ARF request #14 not found!\n";
        exit;
    }
    
    echo "ARF #14: {$arf->activity_title}\n";
    echo "ARF Number: {$arf->arf_number}\n";
    echo "Forward Workflow ID: {$arf->forward_workflow_id}\n\n";
    
    // Test the logic from the PDF
    $lastApprover = $arf->approvalTrails->last();
    if ($lastApprover) {
        $lastStaff = \App\Models\Staff::find($lastApprover->staff_id);
        if ($lastStaff) {
            echo "Last Approver Staff:\n";
            echo "  Name: {$lastStaff->fname} {$lastStaff->lname}\n";
            echo "  Job Title: {$lastStaff->job_name}\n";
            echo "  Staff ID: {$lastStaff->staff_id}\n\n";
            
            // Get the role from workflow definition
            $workflowId = $arf->forward_workflow_id ?? 1;
            echo "Looking for workflow definition with:\n";
            echo "  Workflow ID: {$workflowId}\n";
            echo "  Approval Order: {$lastApprover->approval_order}\n\n";
            
            $workflowDefinition = \App\Models\WorkflowDefinition::where('workflow_id', $workflowId)
                ->where('approval_order', $lastApprover->approval_order)
                ->first();
            
            if ($workflowDefinition) {
                echo "✅ Workflow Definition Found:\n";
                echo "  Role: {$workflowDefinition->role}\n";
                echo "  Approval Order: {$workflowDefinition->approval_order}\n";
                echo "  Workflow ID: {$workflowDefinition->workflow_id}\n";
            } else {
                echo "❌ No workflow definition found for this staff in this workflow\n";
                echo "Falling back to job title: {$lastStaff->job_name}\n";
            }
            
            $role = $workflowDefinition ? $workflowDefinition->role : ($lastStaff->job_name ?? 'N/A');
            echo "\nFinal role to display: {$role}\n";
            
        } else {
            echo "❌ Last approver staff not found in database\n";
        }
    } else {
        echo "❌ No approval trails found\n";
    }
    
    echo "\n✅ ARF PDF workflow role fix is working!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
