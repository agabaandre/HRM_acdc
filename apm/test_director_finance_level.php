<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Staff;
use App\Models\Division;
use App\Models\NonTravelMemo;
use App\Models\WorkflowDefinition;
use App\Services\ApprovalService;

echo "=== Director Finance Level Analysis for Staff 558 ===\n\n";

try {
    // Get staff 558
    $staff = Staff::find(558);
    echo "Staff: {$staff->fname} {$staff->lname}\n";
    echo "Division ID: {$staff->division_id}\n\n";
    
    // Check what approval level Director Finance is
    echo "=== Director Finance Approval Level ===\n";
    $directorFinanceDef = WorkflowDefinition::where('role', 'LIKE', '%Director Finance%')
        ->orWhere('role', 'LIKE', '%Director of Finance%')
        ->orWhere('role', 'LIKE', '%Finance Director%')
        ->first();
    
    if ($directorFinanceDef) {
        echo "Director Finance found:\n";
        echo "  - ID: {$directorFinanceDef->id}\n";
        echo "  - Role: {$directorFinanceDef->role}\n";
        echo "  - Approval Order: {$directorFinanceDef->approval_order}\n";
        echo "  - Workflow ID: {$directorFinanceDef->workflow_id}\n";
        echo "  - Is Division Specific: " . ($directorFinanceDef->is_division_specific ? 'Yes' : 'No') . "\n";
    } else {
        echo "❌ Director Finance role not found!\n";
    }
    echo "\n";
    
    // Check all non-travel memos at approval level 6 (Director Finance level)
    echo "=== Non-Travel Memos at Approval Level 6 ===\n";
    $level6Memos = NonTravelMemo::where('approval_level', 6)
        ->where('overall_status', 'pending')
        ->with(['division', 'staff'])
        ->get();
    
    echo "Total non-travel memos at level 6: " . $level6Memos->count() . "\n\n";
    
    $approvalService = new ApprovalService();
    
    foreach ($level6Memos as $memo) {
        $canTakeAction = $approvalService->canTakeAction($memo, 558);
        echo "Memo ID: {$memo->id}\n";
        echo "  - Title: " . substr($memo->activity_title, 0, 80) . "...\n";
        echo "  - Division: {$memo->division->division_name} (ID: {$memo->division_id})\n";
        echo "  - Division Head: {$memo->division->division_head}\n";
        echo "  - Approval Level: {$memo->approval_level}\n";
        echo "  - Status: {$memo->overall_status}\n";
        echo "  - Can take action: " . ($canTakeAction ? 'Yes' : 'No') . "\n";
        echo "  - Staff 558 is division head? " . ($memo->division->division_head == 558 ? 'Yes' : 'No') . "\n";
        echo "\n";
    }
    
    // Check all non-travel memos at any level where staff 558 can take action
    echo "=== All Non-Travel Memos Where Staff 558 Can Take Action ===\n";
    $allMemos = NonTravelMemo::where('overall_status', 'pending')
        ->where('approval_level', '>', 0)
        ->with(['division', 'staff'])
        ->get();
    
    $actionableMemos = [];
    foreach ($allMemos as $memo) {
        $canTakeAction = $approvalService->canTakeAction($memo, 558);
        if ($canTakeAction) {
            $actionableMemos[] = $memo;
        }
    }
    
    echo "Total actionable non-travel memos: " . count($actionableMemos) . "\n\n";
    
    foreach ($actionableMemos as $memo) {
        echo "Memo ID: {$memo->id}\n";
        echo "  - Title: " . substr($memo->activity_title, 0, 80) . "...\n";
        echo "  - Division: {$memo->division->division_name} (ID: {$memo->division_id})\n";
        echo "  - Division Head: {$memo->division->division_head}\n";
        echo "  - Approval Level: {$memo->approval_level}\n";
        echo "  - Status: {$memo->overall_status}\n";
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
