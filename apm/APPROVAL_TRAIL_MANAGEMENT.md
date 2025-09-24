# Approval Trail Management System

This document describes the automated approval trail management system for archiving and cleaning up old approval trails.

## Overview

The system provides automated tools to:
- **Archive old approval trails** for matrices and activities (ALL trails regardless of approval order and status)
- **Exclude specific matrices** from archiving (e.g., current active matrices)
- **Show statistics** about approval trail usage
- **Clean up old archived trails** to free up database space

**Important**: The archiving job archives ALL approval trails older than the specified days, regardless of their approval order or overall status. This is different from the automatic archiving that only occurs when matrices are returned to draft/returned state.

## Commands

### 1. Archive Old Approval Trails

#### Basic Command
```bash
php artisan approval:archive-trails
```

#### With Options
```bash
php artisan approval:archive-trails --matrix-id=123 --days=30 --dry-run
```

#### Options
- `--matrix-id=N` - Exclude matrix ID N from archiving (use "all" to archive all matrices)
- `--days=N` - Archive trails older than N days (default: 30)
- `--dry-run` - Show what would be archived without actually archiving
- `--queue` - Dispatch job to queue instead of running immediately

#### Examples
```bash
# Archive ALL matrices older than 30 days
php artisan approval:archive-trails --matrix-id=all --days=30

# Archive trails older than 30 days, excluding matrix 123
php artisan approval:archive-trails --matrix-id=123 --days=30

# Dry run to see what would be archived (all matrices)
php artisan approval:archive-trails --matrix-id=all --days=30 --dry-run

# Archive very old trails (90+ days) via queue, excluding matrix 456
php artisan approval:archive-trails --matrix-id=456 --days=90 --queue

# Archive all matrices older than 7 days (immediate execution)
php artisan approval:archive-trails --matrix-id=all --days=7
```

### 2. Manage Approval Trails (Advanced)

#### Show Statistics
```bash
php artisan approval:manage-trails stats
```

#### Archive Trails
```bash
# Archive all matrices older than 30 days
php artisan approval:manage-trails archive --matrix-id=all --days=30

# Archive trails excluding matrix 123
php artisan approval:manage-trails archive --matrix-id=123 --days=30
```

#### Cleanup Old Archived Trails
```bash
php artisan approval:manage-trails cleanup --days=180 --force
```

## Job: ArchiveOldApprovalTrailsJob

### Purpose
Automatically archives old approval trails for matrices and activities while excluding specified matrices.

### Features
- **Comprehensive archiving** - Archives ALL trails regardless of approval order and status
- **Selective exclusion** - Exclude specific matrix IDs from archiving
- **Date-based filtering** - Archive trails older than specified days
- **Dry run support** - Preview what will be archived
- **Comprehensive logging** - Detailed logs of all operations
- **Queue support** - Can be dispatched to background queue

### Important Behavior
- **Archives ALL trails** older than the specified days, regardless of:
  - Approval order (0, 1, 2, 3, etc.)
  - Overall status (draft, pending, approved, returned, etc.)
  - Current workflow state
- **Only excludes** trails from specified matrix IDs
- **Different from automatic archiving** which only archives when matrices are returned to draft/returned state

### Parameters
- `$excludeMatrixId` - Matrix ID to exclude from archiving
- `$daysOld` - Number of days old to consider for archiving
- `$dryRun` - Whether to perform a dry run

### Usage in Code
```php
use App\Jobs\ArchiveOldApprovalTrailsJob;

// Archive trails older than 30 days, excluding matrix 123
ArchiveOldApprovalTrailsJob::dispatch(123, 30, false);

// Dry run to see what would be archived
ArchiveOldApprovalTrailsJob::dispatch(null, 30, true);
```

## Scheduling

### Daily Archiving (Recommended)
Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Archive approval trails older than 30 days daily at 2 AM
    $schedule->job(new ArchiveOldApprovalTrailsJob(null, 30, false))
             ->dailyAt('02:00')
             ->name('archive-approval-trails')
             ->withoutOverlapping();
}
```

### Weekly Cleanup (Optional)
```php
// Clean up archived trails older than 180 days weekly
$schedule->command('approval:manage-trails cleanup --days=180 --force')
         ->weekly()
         ->sundays()
         ->at('03:00');
```

## Statistics

The `stats` action provides comprehensive information:

### Matrix Approval Trails
- Total count
- Active (non-archived) count
- Archived count
- Recent (last 30 days) count
- Old (30+ days) count

### Activity Approval Trails
- Same statistics as matrix trails

### Top Matrices
- Shows top 10 matrices by approval trail count
- Helps identify matrices with excessive trail activity

## Safety Features

### Dry Run Mode
- Preview operations without making changes
- Use `--dry-run` flag
- Shows exactly what would be affected

### Confirmation Prompts
- Interactive confirmation for destructive operations
- Use `--force` to skip confirmations in scripts

### Exclusion Support
- Exclude specific matrices from archiving
- Useful for active matrices that shouldn't be archived
- Prevents accidental archiving of current work

### Logging
- Comprehensive logging of all operations
- Error tracking and debugging support
- Audit trail of all archiving activities

## Best Practices

### 1. Regular Archiving
- Run daily archiving for trails older than 30 days
- Use queue for large operations
- Monitor logs for any issues

### 2. Matrix Exclusion
- Always exclude currently active matrices
- Use matrix ID from current work
- Update exclusions as work progresses

### 3. Cleanup Strategy
- Only cleanup archived trails older than 180+ days
- Run cleanup monthly or quarterly
- Always use dry run first

### 4. Monitoring
- Check statistics regularly
- Monitor database size growth
- Review logs for any errors

## Examples

### Daily Maintenance Script
```bash
#!/bin/bash
# Daily approval trail maintenance

# Show current statistics
php artisan approval:manage-trails stats

# Archive old trails (exclude current active matrix 456)
php artisan approval:archive-trails --matrix-id=456 --days=30 --queue

# Check for very old archived trails
php artisan approval:manage-trails cleanup --days=180 --dry-run
```

### Emergency Cleanup
```bash
# Force cleanup of very old archived trails
php artisan approval:manage-trails cleanup --days=365 --force
```

### Development/Testing
```bash
# Test archiving with dry run
php artisan approval:archive-trails --matrix-id=123 --days=7 --dry-run

# Test with immediate execution
php artisan approval:archive-trails --matrix-id=123 --days=7
```

## Troubleshooting

### Common Issues

1. **Permission Errors**
   - Ensure proper database permissions
   - Check file system permissions for logs

2. **Memory Issues**
   - Use `--queue` for large operations
   - Consider processing in smaller batches

3. **Foreign Key Constraints**
   - Ensure matrices exist before excluding them
   - Check for orphaned activity trails

### Log Locations
- Laravel logs: `storage/logs/laravel.log`
- Job logs: Check queue worker logs
- Database logs: Check MySQL/PostgreSQL logs

### Recovery
- Archived trails can be unarchived by setting `is_archived = 0`
- Deleted trails cannot be recovered (cleanup is permanent)
- Always backup before major cleanup operations
