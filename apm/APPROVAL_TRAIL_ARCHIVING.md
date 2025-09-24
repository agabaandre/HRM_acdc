# Approval Trail Archiving System

This document describes the approval trail archiving system that ensures clean approval restarts when matrices or memos are returned by HOD.

## Overview

When a Head of Division (HOD) returns a matrix to draft or returns a memo, the system automatically archives all previous approval trails to ensure the approval process can restart cleanly without interference from old approval records.

## Database Schema

### New Columns Added

#### `activity_approval_trails` table:
- **`is_archived`**: `boolean` (default: 0)
- **Index**: `['is_archived', 'activity_id']`

#### `approval_trails` table:
- **`is_archived`**: `boolean` (default: 0)
- **Index**: `['is_archived', 'model_id', 'model_type']`

## Model Updates

### Creation Points Updated
All approval trail creation points now explicitly set `is_archived = 0`:
- **MatrixController::saveMatrixTrail()** - Matrix approval trails
- **ApprovalService::processApproval()** - Generic approval trails  
- **ActivityController::update_activity_status()** - Activity approval trails
- **ActivityController::convertToSingleMemo()** - Activity conversion trails
- **HasApprovalWorkflow::saveApprovalTrail()** - Trait-based trail creation

### ActivityApprovalTrail Model
```php
protected $fillable = [
    // ... existing fields
    'is_archived',
];

protected $casts = [
    // ... existing casts
    'is_archived' => 'boolean',
];

// New scope methods
public function scopeActive($query)
{
    return $query->where('is_archived', 0);
}

public function scopeArchived($query)
{
    return $query->where('is_archived', 1);
}
```

### ApprovalTrail Model
```php
protected $fillable = [
    // ... existing fields
    'is_archived',
];

protected function casts(): array
{
    return [
        // ... existing casts
        'is_archived' => 'boolean',
    ];
}

// New scope methods
public function scopeActive($query)
{
    return $query->where('is_archived', 0);
}

public function scopeArchived($query)
{
    return $query->where('is_archived', 1);
}
```

## Core Functionality

### Archive Function
```php
function archive_approval_trails($model)
{
    // For matrices, only archive when approval_order = 0 (draft/returned state)
    if ($modelType === 'App\Models\Matrix') {
        // Only archive if matrix is at approval_order 0 (draft or returned state)
        if ($model->approval_level != 0) {
            return 0; // Skip archiving for matrices not at approval_order 0
        }
        
        // Archive approval trails for the matrix
        $archivedCount = ApprovalTrail::where('model_id', $model->id)
            ->where('model_type', get_class($model))
            ->where('is_archived', 0)
            ->update(['is_archived' => 1]);
        
        // Also archive activity approval trails
        $activityArchivedCount = ActivityApprovalTrail::where('matrix_id', $model->id)
            ->where('is_archived', 0)
            ->update(['is_archived' => 1]);
            
        return $archivedCount;
    } else {
        // For memos, archive when returned (any approval level)
        $archivedCount = ApprovalTrail::where('model_id', $model->id)
            ->where('model_type', get_class($model))
            ->where('is_archived', 0)
            ->update(['is_archived' => 1]);
            
        return $archivedCount;
    }
}
```

### Integration Points

#### MatrixController Return Logic
```php
if($request->action !=='approved'){
    $matrix->forward_workflow_id = (intval($matrix->approval_level)==1)?null:1;
    $matrix->approval_level = ($matrix->approval_level==1)?0:1;
    $matrix->overall_status ='returned';
    
    // Archive approval trails to restart approval process
    archive_approval_trails($matrix);
    
    $notification_type = 'returned';
}
```

#### ApprovalService Return Logic
```php
if ($action !== 'approved') {
    $model->forward_workflow_id = intval($model->approval_level)==1?NULL:$model->forward_workflow_id;
    $model->approval_level = intval($model->approval_level)==1?0:1;
    $model->overall_status = 'returned';
    
    // Archive approval trails to restart approval process
    archive_approval_trails($model);
}
```

## Updated Approval Functions

All approval checking functions now exclude archived trails:

### done_approving()
```php
function done_approving($matrix)
{
    $approval = ApprovalTrail::where('model_id', $matrix->id)
        ->where('model_type', get_class($matrix))
        ->where('approval_order', '>=', $matrix->approval_level)
        ->where('staff_id', $user['staff_id'])
        ->where('is_archived', 0) // Only consider non-archived trails
        ->orderByDesc('id')
        ->first();
        
    return $approval && $approval->action === 'approved';
}
```

### hasUserApproved()
```php
public function hasUserApproved(Model $model, int $userId): bool
{
    $approval = ApprovalTrail::where('model_id', $model->id)
        ->where('model_type', get_class($model))
        ->where('approval_order', $model->approval_level)
        ->where('staff_id', $userId)
        ->where('is_archived', 0) // Only consider non-archived trails
        ->first();
        
    return $approval !== null && $approval->action === 'approved';
}
```

### done_approving_activity()
```php
function done_approving_activty($activity)
{
    $latest_approval = ActivityApprovalTrail::where('activity_id', $activity->id)
        ->where('matrix_id', $activity->matrix_id)
        ->where('approval_order', $activity->matrix->approval_level)
        ->where('staff_id', $user['staff_id'])
        ->where('action', 'passed')
        ->where('is_archived', 0) // Only consider non-archived trails
        ->orderByDesc('id')
        ->first();
        
    return isset($latest_approval->action);
}
```

## When Archiving Occurs

### Matrix Returns
- **Trigger**: When HOD returns a matrix to `approval_order = 0` (draft/returned state)
- **Condition**: Only archives when `$matrix->approval_level == 0`
- **Archived**: All `approval_trails` for the matrix + all `activity_approval_trails` for activities in the matrix
- **Result**: Clean slate for approval restart
- **Note**: Does NOT archive when matrix is at other approval levels (1, 2, 3, etc.)

### Memo Returns
- **Trigger**: When any approver returns a memo (action !== 'approved')
- **Condition**: Archives at any approval level for memos
- **Archived**: All `approval_trails` for the memo
- **Result**: Memo can be resubmitted with fresh approval trail

## Benefits

### 1. Clean Approval Restart
- **No interference** from old approval records
- **Fresh approval trail** starts from scratch
- **Consistent state** across all approval functions

### 2. Data Integrity
- **Historical records preserved** (archived, not deleted)
- **Audit trail maintained** for compliance
- **No data loss** during returns

### 3. System Reliability
- **Predictable behavior** after returns
- **No edge cases** from mixed old/new approval states
- **Consistent user experience**

## Usage Examples

### Query Active Approval Trails
```php
// Get only non-archived approval trails
$activeTrails = ApprovalTrail::active()->get();

// Get only archived approval trails
$archivedTrails = ApprovalTrail::archived()->get();

// Get active trails for specific model
$modelTrails = ApprovalTrail::active()
    ->forModelInstance($matrix)
    ->get();
```

### Check Approval Status
```php
// These functions automatically exclude archived trails
$canApprove = can_take_action($matrix);
$hasApproved = done_approving($matrix);
$userApproved = $approvalService->hasUserApproved($matrix, $userId);
```

### Manual Archiving
```php
// Manually archive trails for a specific model
$archivedCount = archive_approval_trails($matrix);
echo "Archived {$archivedCount} approval trails";
```

## Testing

### Test Archiving Function
```php
// Test the archiving system
$matrix = Matrix::find(7);
$archivedCount = archive_approval_trails($matrix);
echo "Archived {$archivedCount} trails";
```

### Test Approval Functions
```php
// Test that approval functions ignore archived trails
$matrix = Matrix::find(7);
echo "Can take action: " . (can_take_action($matrix) ? 'Yes' : 'No');
echo "Done approving: " . (done_approving($matrix) ? 'Yes' : 'No');
```

## Migration Commands

### Run Migrations
```bash
php artisan migrate
```

### Rollback Migrations
```bash
php artisan migrate:rollback --step=2
```

## Monitoring

### Check Archiving Status
```sql
-- Check archived vs active trails
SELECT 
    is_archived,
    COUNT(*) as count
FROM approval_trails 
GROUP BY is_archived;

-- Check activity approval trails
SELECT 
    is_archived,
    COUNT(*) as count
FROM activity_approval_trails 
GROUP BY is_archived;
```

### Log Monitoring
The system logs archiving activities:
```php
Log::info("Archived approval trails for matrix return", [
    'matrix_id' => $modelId,
    'approval_trails_archived' => $archivedCount,
    'activity_approval_trails_archived' => $activityArchivedCount
]);
```

## Troubleshooting

### Common Issues

1. **Approval functions still considering archived trails**
   - Check that all approval functions include `->where('is_archived', 0)`
   - Verify the scope methods are being used correctly

2. **Archiving not triggered on returns**
   - Ensure `archive_approval_trails($model)` is called in return logic
   - Check that the function is available in the scope

3. **Performance issues with large datasets**
   - Ensure indexes are created on `is_archived` columns
   - Consider adding composite indexes for common queries

### Debug Commands
```php
// Check if trails are being archived
$matrix = Matrix::find(7);
$trails = ApprovalTrail::where('model_id', $matrix->id)->get();
echo "Total: " . $trails->count();
echo "Active: " . $trails->where('is_archived', 0)->count();
echo "Archived: " . $trails->where('is_archived', 1)->count();
```

## Best Practices

1. **Always use scope methods** when querying approval trails
2. **Test archiving functionality** after any changes to approval logic
3. **Monitor logs** for archiving activities
4. **Use composite indexes** for performance optimization
5. **Document any custom archiving logic** for specific use cases

This system ensures that approval processes can restart cleanly after returns while maintaining complete historical records for audit purposes.
