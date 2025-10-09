<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\RequestARF;

echo "=== Testing ARF Approval Trails ===\n\n";

try {
    $arf = RequestARF::find(14);
    
    if (!$arf) {
        echo "❌ ARF request #14 not found!\n";
        exit;
    }
    
    echo "ARF #14: {$arf->activity_title}\n";
    echo "ARF Number: {$arf->arf_number}\n\n";
    
    // Get approval trails
    $approvalTrails = $arf->approvalTrails;
    
    echo "Approval Trails Count: " . $approvalTrails->count() . "\n\n";
    
    if ($approvalTrails->count() > 0) {
        echo "All Approval Trails:\n";
        foreach ($approvalTrails as $index => $trail) {
            echo "  {$index}. Staff ID: {$trail->staff_id}\n";
            echo "     Status: {$trail->status}\n";
            echo "     Comments: " . ($trail->comments ?? 'N/A') . "\n";
            echo "     Created: {$trail->created_at}\n";
            echo "     Updated: {$trail->updated_at}\n";
            
            // Get staff info
            $staff = \App\Models\Staff::find($trail->staff_id);
            if ($staff) {
                echo "     Staff Name: {$staff->fname} {$staff->lname}\n";
                echo "     Job Title: {$staff->job_name}\n";
                echo "     Division: " . ($staff->division ? $staff->division->division_name : 'N/A') . "\n";
            } else {
                echo "     Staff: Not found in database\n";
            }
            echo "\n";
        }
        
        // Get the last approver
        $lastApprover = $approvalTrails->last();
        echo "Last Approver:\n";
        echo "  Staff ID: {$lastApprover->staff_id}\n";
        echo "  Status: {$lastApprover->status}\n";
        
        $lastStaff = \App\Models\Staff::find($lastApprover->staff_id);
        if ($lastStaff) {
            echo "  Name: {$lastStaff->fname} {$lastStaff->lname}\n";
            echo "  Job Title: {$lastStaff->job_name}\n";
            echo "  Division: " . ($lastStaff->division ? $lastStaff->division->division_name : 'N/A') . "\n";
        }
    } else {
        echo "❌ No approval trails found.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
