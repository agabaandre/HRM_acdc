<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Staff;
use App\Models\NonTravelMemo;
use App\Services\PendingApprovalsService;
use App\Services\ApprovalService;

echo "=== PendingApprovalsService Debug for Staff 558 ===\n\n";

try {
    // Get staff 558
    $staff = Staff::find(558);
    echo "Staff: {$staff->fname} {$staff->lname}\n";
    echo "Division ID: {$staff->division_id}\n\n";
    
    // Test PendingApprovalsService
    $pendingService = new PendingApprovalsService([
        'staff_id' => 558,
        'division_id' => $staff->division_id,
        'permissions' => [],
        'name' => $staff->fname . ' ' . $staff->lname,
        'email' => $staff->work_email,
        'base_url' => config('app.url')
    ]);
    
    // Use reflection to access protected methods
    $reflection = new ReflectionClass($pendingService);
    
    // Test getUserApprovalLevels
    $getUserApprovalLevels = $reflection->getMethod('getUserApprovalLevels');
    $getUserApprovalLevels->setAccessible(true);
    $approvalLevels = $getUserApprovalLevels->invoke($pendingService, 'NonTravelMemo');
    
    echo "=== getUserApprovalLevels('NonTravelMemo') ===\n";
    echo "Approval levels: " . implode(', ', $approvalLevels) . "\n\n";
    
    // Test getUserDivisionIds
    $getUserDivisionIds = $reflection->getMethod('getUserDivisionIds');
    $getUserDivisionIds->setAccessible(true);
    $divisionIds = $getUserDivisionIds->invoke($pendingService);
    
    echo "=== getUserDivisionIds() ===\n";
    echo "Division IDs: " . implode(', ', $divisionIds) . "\n\n";
    
    // Test isDivisionSpecificApprover
    $isDivisionSpecificApprover = $reflection->getMethod('isDivisionSpecificApprover');
    $isDivisionSpecificApprover->setAccessible(true);
    $isDivSpecific = $isDivisionSpecificApprover->invoke($pendingService);
    
    echo "=== isDivisionSpecificApprover() ===\n";
    echo "Is division specific: " . ($isDivSpecific ? 'Yes' : 'No') . "\n\n";
    
    // Test the actual query that PendingApprovalsService would run
    echo "=== Simulating PendingApprovalsService Query ===\n";
    
    $query = NonTravelMemo::with(['staff', 'division', 'approvalTrails.staff', 'forwardWorkflow.workflowDefinitions.approvers.staff'])
        ->where('overall_status', 'pending')
        ->where('forward_workflow_id', '!=', null)
        ->where('approval_level', '>', 0);
    
    // Apply approval levels filter
    if (!empty($approvalLevels)) {
        $query->whereIn('approval_level', $approvalLevels);
    }
    
    // Apply division filter
    if (!empty($divisionIds)) {
        $query->whereIn('division_id', $divisionIds);
    }
    
    echo "Query SQL: " . $query->toSql() . "\n";
    echo "Query bindings: " . json_encode($query->getBindings()) . "\n\n";
    
    $memos = $query->get();
    echo "Memos found by query: " . $memos->count() . "\n\n";
    
    foreach ($memos as $memo) {
        echo "Memo ID: {$memo->id}\n";
        echo "  - Title: " . substr($memo->activity_title, 0, 60) . "...\n";
        echo "  - Division: {$memo->division->division_name} (ID: {$memo->division_id})\n";
        echo "  - Approval Level: {$memo->approval_level}\n";
        echo "  - Status: {$memo->overall_status}\n";
        echo "\n";
    }
    
    // Test isCurrentApprover for each memo
    echo "=== Testing isCurrentApprover for each memo ===\n";
    $approvalService = new ApprovalService();
    
    foreach ($memos as $memo) {
        $canTakeAction = $approvalService->canTakeAction($memo, 558);
        echo "Memo ID {$memo->id}: Can take action = " . ($canTakeAction ? 'Yes' : 'No') . "\n";
    }
    
    // Test the final result
    echo "\n=== Final PendingApprovalsService Result ===\n";
    $summaryStats = $pendingService->getSummaryStats();
    echo "Total pending: {$summaryStats['total_pending']}\n";
    echo "By category:\n";
    foreach ($summaryStats['by_category'] as $category => $count) {
        echo "  - {$category}: {$count}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
