<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Matrix;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use App\Models\Activity;
use App\Services\ApprovalService;
use App\Models\WorkflowDefinition;

echo "=== Testing Notification Recipients ===\n\n";

try {
    $approvalService = new ApprovalService();
    
    // Test different model types
    $testModels = [
        'Matrix' => Matrix::with(['division', 'forwardWorkflow'])->find(1),
        'NonTravelMemo' => NonTravelMemo::with(['division', 'forwardWorkflow'])->find(11),
        'SpecialMemo' => SpecialMemo::with(['division', 'forwardWorkflow'])->find(4),
        'Activity' => Activity::with(['division', 'forwardWorkflow'])->find(206),
    ];
    
    foreach ($testModels as $modelType => $model) {
        if (!$model) {
            echo "❌ {$modelType} not found, skipping...\n\n";
            continue;
        }
        
        echo "=== Testing {$modelType} ID: {$model->id} ===\n";
        echo "Current Approval Level: {$model->approval_level}\n";
        echo "Overall Status: {$model->overall_status}\n";
        echo "Division: " . ($model->division ? $model->division->name : 'None') . "\n";
        echo "Division Category: " . ($model->division ? $model->division->category : 'None') . "\n\n";
        
        // Get current workflow definition
        $currentDefinition = WorkflowDefinition::where('approval_order', $model->approval_level)
            ->where('workflow_id', $model->forward_workflow_id)
            ->where('is_enabled', 1)
            ->first();
            
        if ($currentDefinition) {
            echo "Current Workflow Definition:\n";
            echo "  - Role: {$currentDefinition->role}\n";
            echo "  - Is Division Specific: " . ($currentDefinition->is_division_specific ? 'Yes' : 'No') . "\n";
            echo "  - Division Reference Column: {$currentDefinition->division_reference_column}\n";
            echo "  - Fund Type: {$currentDefinition->fund_type}\n";
            echo "  - Category: {$currentDefinition->category}\n\n";
        } else {
            echo "❌ No current workflow definition found\n\n";
            continue;
        }
        
        // Test notification recipient
        $recipient = $approvalService->getNotificationRecipient($model);
        
        if ($recipient) {
            echo "✅ Notification Recipient Found:\n";
            echo "  - Name: {$recipient->fname} {$recipient->lname}\n";
            echo "  - Email: {$recipient->work_email}\n";
            echo "  - Job Title: {$recipient->job_name}\n";
            echo "  - Staff ID: {$recipient->staff_id}\n";
            
            // Check if this is a division-specific approver
            if ($currentDefinition->is_division_specific && $model->division) {
                $division = $model->division;
                $referenceColumn = $currentDefinition->division_reference_column;
                $divisionStaffId = $division->$referenceColumn ?? null;
                
                if ($divisionStaffId == $recipient->staff_id) {
                    echo "  ✅ CORRECT: This is the division-specific approver\n";
                } else {
                    echo "  ❌ INCORRECT: This should be the division-specific approver (ID: {$divisionStaffId})\n";
                }
            } else {
                echo "  ℹ️  This is a regular approver (not division-specific)\n";
            }
        } else {
            echo "❌ No notification recipient found\n";
        }
        
        echo "\n" . str_repeat("-", 60) . "\n\n";
    }
    
    // Test specific division-specific scenarios
    echo "=== Testing Division-Specific Scenarios ===\n";
    
    // Find a matrix at Head of Division level (level 1)
    $hodMatrix = Matrix::with(['division', 'forwardWorkflow'])
        ->where('approval_level', 1)
        ->where('overall_status', 'pending')
        ->first();
        
    if ($hodMatrix) {
        echo "Testing HOD Matrix ID: {$hodMatrix->id}\n";
        $recipient = $approvalService->getNotificationRecipient($hodMatrix);
        
        if ($recipient) {
            echo "Recipient: {$recipient->fname} {$recipient->lname} (ID: {$recipient->staff_id})\n";
            
            // Check if this matches the division head
            if ($hodMatrix->division && $hodMatrix->division->division_head == $recipient->staff_id) {
                echo "✅ CORRECT: Recipient is the division head\n";
            } else {
                echo "❌ INCORRECT: Recipient should be the division head\n";
                echo "Division Head ID: " . ($hodMatrix->division->division_head ?? 'None') . "\n";
            }
        } else {
            echo "❌ No recipient found\n";
        }
    } else {
        echo "No HOD-level matrix found for testing\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
