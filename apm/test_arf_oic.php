<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\RequestARF;

echo "=== Testing ARF OIC Detection ===\n\n";

try {
    $arf = RequestARF::find(14);
    
    if (!$arf) {
        echo "❌ ARF request #14 not found!\n";
        exit;
    }
    
    echo "ARF #14: {$arf->activity_title}\n";
    echo "ARF Number: {$arf->arf_number}\n\n";
    
    // Check all approval trails for OIC
    $approvalTrails = $arf->approvalTrails;
    
    echo "Approval Trails Analysis:\n";
    foreach ($approvalTrails as $index => $trail) {
        echo "  Trail {$index}:\n";
        echo "    Staff ID: {$trail->staff_id}\n";
        echo "    OIC Staff ID: " . ($trail->oic_staff_id ?? 'NULL') . "\n";
        echo "    Action: {$trail->action}\n";
        echo "    Approval Order: {$trail->approval_order}\n";
        
        // Get regular staff
        $staff = \App\Models\Staff::find($trail->staff_id);
        if ($staff) {
            echo "    Regular Staff: {$staff->fname} {$staff->lname}\n";
        }
        
        // Get OIC staff if exists
        if ($trail->oic_staff_id) {
            $oicStaff = \App\Models\Staff::find($trail->oic_staff_id);
            if ($oicStaff) {
                echo "    OIC Staff: {$oicStaff->fname} {$oicStaff->lname} (OIC)\n";
            }
        }
        echo "\n";
    }
    
    // Test the last approver logic
    $lastApprover = $arf->approvalTrails->last();
    if ($lastApprover) {
        echo "Last Approver Analysis:\n";
        echo "  Staff ID: {$lastApprover->staff_id}\n";
        echo "  OIC Staff ID: " . ($lastApprover->oic_staff_id ?? 'NULL') . "\n";
        
        // Determine who actually signed
        $actualSigner = null;
        $isOic = false;
        
        if ($lastApprover->oic_staff_id) {
            $actualSigner = \App\Models\Staff::find($lastApprover->oic_staff_id);
            $isOic = true;
            echo "  Actual Signer: OIC - {$actualSigner->fname} {$actualSigner->lname}\n";
        } else {
            $actualSigner = \App\Models\Staff::find($lastApprover->staff_id);
            echo "  Actual Signer: Regular - {$actualSigner->fname} {$actualSigner->lname}\n";
        }
        
        if ($actualSigner) {
            // Get the role from workflow definition
            $workflowId = $arf->forward_workflow_id ?? 1;
            $workflowDefinition = \App\Models\WorkflowDefinition::where('workflow_id', $workflowId)
                ->where('approval_order', $lastApprover->approval_order)
                ->first();
            
            $role = $workflowDefinition ? $workflowDefinition->role : ($actualSigner->job_name ?? 'N/A');
            
            echo "  Should display:\n";
            echo "    Name: {$actualSigner->fname} {$actualSigner->lname}" . ($isOic ? " (OIC)" : "") . "\n";
            echo "    Role: {$role}\n";
            echo "    Division: (NOT DISPLAYED)\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
