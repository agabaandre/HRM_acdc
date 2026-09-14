<?php
/**
 * Run from project root: cd /opt/homebrew/var/www/staff && php artisan tinker
 * Then paste this file content, or: include 'apm/debug_approvable_activities.php';
 *
 * Analyzes why get_approvable_activities might return all activities for
 * Grants Officer (staff_id 558, approval_order 3) on matrix 23.
 */
$matrixId = 23;
$staffId = 558;

// Set session so helpers see this user (Grants Officer)
$staff = \App\Models\Staff::find($staffId);
$user = session('user', []);
$user['staff_id'] = $staffId;
$user['division_id'] = $staff->division_id ?? 0;
session(['user' => $user]);
echo "Running as staff_id={$staffId} division_id=" . $user['division_id'] . "\n\n";

$matrix = \App\Models\Matrix::with(['activities', 'division'])->find($matrixId);
if (!$matrix) {
    echo "Matrix {$matrixId} not found.\n";
    return;
}

echo "=== Matrix {$matrixId} ===\n";
echo "approval_level: " . $matrix->approval_level . "\n";
echo "forward_workflow_id: " . $matrix->forward_workflow_id . "\n";
echo "overall_status: " . $matrix->overall_status . "\n";
echo "activities count: " . $matrix->activities->count() . "\n\n";

// Definitions at this level
$definitions = \App\Models\WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
    ->where('approval_order', $matrix->approval_level)
    ->where('is_enabled', 1)
    ->get();

echo "=== Workflow definitions at approval_order={$matrix->approval_level} ===\n";
foreach ($definitions as $d) {
    $allowed = $d->allowed_funders;
    if (is_string($allowed)) $allowed = json_decode($allowed, true);
    $has558 = \App\Models\Approver::where('workflow_dfn_id', $d->id)->where('staff_id', $staffId)->exists();
    echo "  id={$d->id} role={$d->role} allowed_funders=" . json_encode($allowed) . " staff_558=" . ($has558 ? 'YES' : 'no') . "\n";
}

// Which definition would get_user_workflow_definition_for_matrix return?
$userDef = get_user_workflow_definition_for_matrix($matrix);
if (!$userDef) {
    echo "\n*** get_user_workflow_definition_for_matrix returned NULL (user not considered approver at this level) ***\n";
} else {
    $af = $userDef->allowed_funders;
    if (is_string($af)) $af = json_decode($af, true);
    echo "\n=== User's workflow definition (used for filtering) ===\n";
    echo "  id={$userDef->id} role={$userDef->role} allowed_funders=" . json_encode($af) . "\n";
}

// Per-activity: funder IDs and match
echo "\n=== Activities and their funder IDs ===\n";
foreach ($matrix->activities->where('is_single_memo', 0) as $a) {
    $funderIds = get_activity_funder_ids($a);
    $allowed = $userDef ? (is_string($userDef->allowed_funders) ? json_decode($userDef->allowed_funders, true) : $userDef->allowed_funders) : [];
    $allowed = is_array($allowed) ? array_map('intval', array_filter($allowed)) : [];
    $matches = !empty($allowed) ? (count(array_intersect($funderIds, $allowed)) > 0) : true;
    $budgetPreview = $a->budget_breakdown ? (is_string($a->budget_breakdown) ? substr($a->budget_breakdown, 0, 80) . '...' : json_encode(array_keys($a->budget_breakdown ?? []))) : 'null';
    echo "  activity_id={$a->id} title=" . substr($a->activity_title ?? '', 0, 40) . " funder_ids=" . json_encode($funderIds) . " allowed=" . json_encode($allowed) . " matches=" . ($matches ? 'YES' : 'no') . " budget_keys=" . (is_array($a->budget_breakdown) ? json_encode(array_keys($a->budget_breakdown)) : 'n/a') . "\n";
}

// Clear cache and run get_approvable_activities
\Illuminate\Support\Facades\Cache::forget("approvable_activities_v3_{$matrix->id}_{$staffId}_{$matrix->approval_level}");
$approvable = get_approvable_activities($matrix);
echo "\n=== get_approvable_activities count ===\n";
echo $approvable->count() . " activities\n";

// If user's definition has allowed_funders but we still see all, check empty activity funder IDs
if ($userDef && !empty(is_string($userDef->allowed_funders) ? json_decode($userDef->allowed_funders, true) : $userDef->allowed_funders) && $approvable->count() === $matrix->activities->where('is_single_memo', 0)->count()) {
    echo "\n*** WARNING: Definition has allowed_funders but approvable count equals all activities. Check if get_activity_funder_ids returns empty for activities (then canApprove is true). ***\n";
}
echo "Done.\n";
