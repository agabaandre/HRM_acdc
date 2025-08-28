# Activity Operations Feature Setup

## Overview
The Activity Operations feature allows users to perform additional actions on approved activities, including creating ARF requests, service requests, and printing activities. This feature is controlled by an environment variable and only appears when both the activity and its matrix are approved.

## Environment Configuration

### 1. Add Environment Variable
Add the following line to your `.env` file:

```env
ALLOW_ACTIVITY_OPERATIONS=true
```

- Set to `true` to enable the feature
- Set to `false` or omit to disable the feature

### 2. Default Behavior
If the environment variable is not set, the feature defaults to `false` (disabled).

## Feature Details

### When Buttons Appear
The Activity Operations section only appears when:
1. `ALLOW_ACTIVITY_OPERATIONS=true` in environment
2. Activity status is `approved`
3. Matrix status is `approved`

### Available Operations

#### 1. Create ARF Request (Extramural Activities)
- **Appears when**: Activity fund type is "Extramural"
- **Button**: Green "Create ARF Request" button
- **Action**: Redirects to ARF creation form with activity ID pre-filled
- **Route**: `request-arf.create`

#### 2. Request for Services (Intramural Activities)
- **Appears when**: Activity fund type is "Intramural" or other
- **Button**: Blue "Request for Services" button
- **Action**: Redirects to service request creation form with activity ID pre-filled
- **Route**: `service-requests.create`

#### 3. Print Activity
- **Appears for**: All approved activities
- **Button**: Gray "Print Activity" button
- **Action**: Generates PDF print view of the activity
- **Route**: `matrices.activities.show` with `?print=pdf` parameter

## Implementation Details

### Helper Function
The feature uses a helper function in `app/Helpers/home_helper.php`:

```php
function allow_activity_operations(): bool
{
    return env('ALLOW_ACTIVITY_OPERATIONS', false);
}
```

### View Location
The Activity Operations section is added to:
`resources/views/activities/show.blade.php`

### Print Button Override
When `allow_activity_operations()` returns `true`, the print button is always visible regardless of other conditions.

## Usage Examples

### Enable the Feature
```bash
# In your .env file
ALLOW_ACTIVITY_OPERATIONS=true
```

### Disable the Feature
```bash
# In your .env file
ALLOW_ACTIVITY_OPERATIONS=false
# OR remove the line entirely
```

### Clear Cache After Changes
```bash
php artisan cache:clear
php artisan view:clear
```

## Security Notes

- The feature only appears for approved activities and matrices
- Users must have appropriate permissions to access the target routes
- The environment variable provides a global on/off switch for the feature
- Individual route permissions are still enforced

## Troubleshooting

### Buttons Not Appearing
1. Check that `ALLOW_ACTIVITY_OPERATIONS=true` in `.env`
2. Verify both activity and matrix are approved
3. Clear application cache
4. Check browser console for JavaScript errors

### Routes Not Working
1. Verify the target routes exist (`request-arf.create`, `service-requests.create`)
2. Check user permissions for the target routes
3. Ensure the target controllers and views are properly implemented

## Future Enhancements

- Add Change Request functionality when implemented
- Support for additional activity operation types
- Role-based permissions for specific operations
- Activity operation history tracking
