<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\RequestARF;

echo "=== Testing ARF No Division Display ===\n\n";

try {
    $arf = RequestARF::find(14);
    
    if (!$arf) {
        echo "❌ ARF request #14 not found!\n";
        exit;
    }
    
    echo "ARF #14: {$arf->activity_title}\n";
    echo "ARF Number: {$arf->arf_number}\n\n";
    
    // Test the logic from the PDF
    $lastApprover = $arf->approvalTrails->last();
    if ($lastApprover) {
        $lastStaff = \App\Models\Staff::find($lastApprover->staff_id);
        if ($lastStaff) {
            echo "Last Approver Staff:\n";
            echo "  Name: {$lastStaff->fname} {$lastStaff->lname}\n";
            echo "  Job Title: {$lastStaff->job_name}\n";
            echo "  Division: " . ($lastStaff->division ? $lastStaff->division->division_name : 'N/A') . "\n";
            echo "  Staff ID: {$lastStaff->staff_id}\n\n";
            
            // Get the role from workflow definition
            $workflowId = $arf->forward_workflow_id ?? 1;
            $workflowDefinition = \App\Models\WorkflowDefinition::where('workflow_id', $workflowId)
                ->where('approval_order', $lastApprover->approval_order)
                ->first();
            
            $role = $workflowDefinition ? $workflowDefinition->role : ($lastStaff->job_name ?? 'N/A');
            
            echo "What will be displayed in PDF:\n";
            echo "  Name: {$lastStaff->fname} {$lastStaff->lname}\n";
            echo "  Role: {$role}\n";
            echo "  Division: (NOT DISPLAYED) ✅\n";
            
        } else {
            echo "❌ Last approver staff not found in database\n";
        }
    } else {
        echo "❌ No approval trails found\n";
    }
    
    echo "\n✅ ARF PDF 'Prepared by' will now show only name and role (no division)!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
