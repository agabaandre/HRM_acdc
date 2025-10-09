<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\RequestARF;

echo "=== Testing ARF PDF Internal Participants Fix ===\n\n";

try {
    $arf = RequestARF::find(14);
    
    if (!$arf) {
        echo "❌ ARF request #14 not found!\n";
        exit;
    }
    
    echo "ARF #14: {$arf->activity_title}\n";
    echo "ARF Number: {$arf->arf_number}\n\n";
    
    // Get source data like the controller does
    $sourceModel = null;
    $sourceData = [];
    
    if ($arf->model_type === 'App\\Models\\Activity') {
        $sourceModel = \App\Models\Activity::find($arf->source_id);
        if ($sourceModel) {
            $sourceData = [
                'id' => $sourceModel->id,
                'activity_title' => $sourceModel->activity_title,
                'background' => $sourceModel->background,
                'division' => $sourceModel->division,
                'fund_codes' => $sourceModel->fundCodes,
                'budget_breakdown' => $sourceModel->budget_breakdown,
                'internal_participants' => $sourceModel->internal_participants,
                'approval_trails' => $sourceModel->approvalTrails,
                'created_at' => $sourceModel->created_at,
                'updated_at' => $sourceModel->updated_at,
            ];
        }
    }
    
    // Process internal participants like the PDF does
    $internalParticipants = $sourceData['internal_participants'] ?? [];
    if (is_string($internalParticipants)) {
        $internalParticipants = json_decode($internalParticipants, true) ?? [];
    }
    if (!is_array($internalParticipants)) {
        $internalParticipants = [];
    }
    
    echo "Internal participants data:\n";
    echo "Raw data: " . json_encode($internalParticipants) . "\n\n";
    
    if (!empty($internalParticipants)) {
        echo "Found " . count($internalParticipants) . " internal participants:\n";
        $count = 1;
        foreach($internalParticipants as $participantId => $participantData) {
            // Fetch staff data from database like the PDF does
            $staff = \App\Models\Staff::where('staff_id', $participantId)
                ->with(['division'])
                ->first();
            
            if ($staff) {
                $participantName = $staff->fname . ' ' . $staff->lname;
                $division = $staff->division ? $staff->division->division_name : 'N/A';
                $dutyStation = $staff->duty_station_name ?? $staff->duty_station ?? 'N/A';
                $days = is_array($participantData) ? ($participantData['days'] ?? 1) : 1;
                
                echo "  {$count}. {$participantName} ({$division}) - {$dutyStation} - {$days} days\n";
                $count++;
            } else {
                echo "  {$count}. Staff ID {$participantId} not found in database\n";
                $count++;
            }
        }
        echo "\n✅ ARF PDF internal participants fix is working!\n";
    } else {
        echo "❌ No internal participants found in the data.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
