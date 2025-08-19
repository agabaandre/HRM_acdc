# Approval System Refactoring

## Overview

The approval system has been refactored to be generic and reusable across different model types while maintaining backward compatibility. Activities remain tied to matrices as a special case.

## What Was Changed

### 1. New Generic Components

#### **ApprovalTrail Model** (`app/Models/ApprovalTrail.php`)
- Replaces both `MatrixApprovalTrail` and `ActivityApprovalTrail`
- Uses polymorphic relationships to work with any model
- Supports both generic models and activities (with matrix_id)

#### **HasApprovalWorkflow Trait** (`app/Traits/HasApprovalWorkflow.php`)
- Provides approval functionality to any model
- Includes methods for workflow navigation, status checks, and approval trails
- Can be used by any model that needs approval functionality

#### **ApprovalService** (`app/Services/ApprovalService.php`)
- Generic service for handling approvals
- Works with any model type
- Handles permission checks, status updates, and notifications

#### **GenericApprovalController** (`app/Http/Controllers/GenericApprovalController.php`)
- Generic controller for handling approvals
- Can be used by any model type
- Includes batch operations and approval trail display

#### **GenericApprovalHelper** (`app/Helpers/GenericApprovalHelper.php`)
- Generic helper functions for approval logic
- Maintains backward compatibility with existing functions
- Can work with any model type

### 2. Database Changes

#### **Migration: `rename_matrix_approval_trails_to_approval_trails`**
- Renames `matrix_approval_trails` to `approval_trails`
- Adds polymorphic relationship columns (`model_id`, `model_type`)
- Makes `matrix_id` nullable (only used for activities)
- Adds `approval_order` column for better tracking

### 3. Model Updates

#### **Matrix Model**
- Now uses `HasApprovalWorkflow` trait
- Maintains backward compatibility
- Uses new `ApprovalTrail` model for relationships

#### **Activity Model**
- Now uses `HasApprovalWorkflow` trait
- Maintains special relationship with matrices
- Uses new `ApprovalTrail` model for relationships

## How to Use the New System

### 1. Adding Approval to a New Model

#### **Step 1: Add Approval Columns**
```php
// Migration
Schema::table('your_table', function (Blueprint $table) {
    $table->string('overall_status')->default('pending');
    $table->foreignId('forward_workflow_id')->nullable();
    $table->foreignId('reverse_workflow_id')->nullable();
    $table->unsignedInteger('approval_level')->default(1);
    $table->unsignedInteger('next_approval_level')->nullable();
});
```

#### **Step 2: Update Model**
```php
use App\Traits\HasApprovalWorkflow;

class YourModel extends Model
{
    use HasFactory, HasApprovalWorkflow;
    
    protected $fillable = [
        // ... existing fields
        'overall_status',
        'forward_workflow_id',
        'reverse_workflow_id',
        'approval_level',
        'next_approval_level',
    ];
}
```

#### **Step 3: Add Routes**
```php
// In routes/web.php
Route::post('/your-model/{model}/approve', [GenericApprovalController::class, 'updateStatus'])
    ->name('your-model.approve');
Route::post('/your-model/{model}/submit', [GenericApprovalController::class, 'submitForApproval'])
    ->name('your-model.submit');
```

#### **Step 4: Add Views**
```php
@if(can_take_action_generic($model))
    @include('partials.approval-actions', ['resource' => $model])
@endif

@include('partials.approval-trail', ['trails' => $model->approvalTrails])
```

### 2. Using the Approval Service

```php
use App\Services\ApprovalService;

class YourController extends Controller
{
    protected ApprovalService $approvalService;
    
    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }
    
    public function approve(Request $request, YourModel $model)
    {
        $userId = user_session('staff_id');
        
        if (!$this->approvalService->canTakeAction($model, $userId)) {
            return redirect()->back()->with('error', 'Not authorized');
        }
        
        $this->approvalService->processApproval(
            $model, 
            $request->action, 
            $request->comment ?? ''
        );
        
        return redirect()->back()->with('success', 'Approved successfully');
    }
}
```

### 3. Using Helper Functions

```php
// Check if user can take action
if (can_take_action_generic($model)) {
    // Show approval buttons
}

// Check if user has already approved
if (done_approving_generic($model)) {
    // Show approved status
}

// Get approval trails
$trails = get_approval_trails_generic($model);

// Save approval trail
save_approval_trail_generic($model, 'Comment', 'approved');
```

## Special Case: Activities

Activities maintain their special relationship with matrices:

1. **Matrix ID Tracking**: Activities still track their `matrix_id` in approval trails
2. **Matrix-Based Logic**: Some approval logic still considers the parent matrix
3. **Backward Compatibility**: All existing activity approval functionality works unchanged

## Backward Compatibility

### Existing Functions Still Work
- `can_take_action($matrix)` → Now uses generic version
- `done_approving($matrix)` → Now uses generic version
- `still_with_creator($matrix)` → Now uses generic version
- `get_matrix_notification_recipient($matrix)` → Now uses generic version

### Existing Relationships Still Work
- `$matrix->matrixApprovalTrails` → Now uses `ApprovalTrail` with filtering
- `$activity->activityApprovalTrails` → Now uses `ApprovalTrail` with filtering

## Benefits

### 1. **Reusability**
- Any model can now have approval functionality
- No need to duplicate approval logic
- Consistent approval behavior across models

### 2. **Maintainability**
- Single source of truth for approval logic
- Easier to update and fix approval issues
- Centralized approval configuration

### 3. **Flexibility**
- Models can have different approval workflows
- Easy to add new approval types
- Support for complex approval scenarios

### 4. **Performance**
- Optimized database queries
- Reduced code duplication
- Better caching opportunities

## Migration Guide

### For Existing Code
1. **No Changes Required**: Existing code continues to work
2. **Optional Updates**: Can gradually migrate to use new generic functions
3. **New Features**: Use new generic components for new approval features

### For New Models
1. **Use Generic Components**: Leverage the new trait, service, and helpers
2. **Follow Patterns**: Use the established patterns for consistency
3. **Test Thoroughly**: Ensure approval workflows work correctly

## Testing

### Unit Tests
- Test approval service methods
- Test trait functionality
- Test helper functions

### Integration Tests
- Test approval workflows
- Test notification system
- Test database relationships

### Manual Testing
- Test approval flows for each model type
- Test backward compatibility
- Test edge cases and error conditions

## Future Enhancements

### 1. **Approval Conditions**
- Add support for conditional approvals
- Implement approval rules engine
- Support for dynamic approval paths

### 2. **Notification System**
- Enhanced notification types
- Push notifications
- SMS notifications

### 3. **Approval Analytics**
- Approval time tracking
- Bottleneck identification
- Performance metrics

### 4. **Mobile Support**
- Mobile-friendly approval interface
- Offline approval capabilities
- Mobile notifications

## Support

For questions or issues with the refactored approval system:

1. Check the backup folder for original implementation
2. Review the generic components for usage examples
3. Test with the provided helper functions
4. Consult the migration guide for specific scenarios 