# Document Number Management System

This document describes the comprehensive document number management system for the APM application, including assignment, monitoring, and conflict resolution.

## Overview

The document numbering system automatically assigns unique document numbers to various types of records in the system. It uses a queue-based approach to ensure proper handling of document returns, resets, and conflicts.

## Document Types

| Type | Code | Description | Example |
|------|------|-------------|---------|
| Quarterly Matrix | QM | Regular activities in matrices | AU/CDC/EPR/IM/QM/012 |
| Single Memo | SM | Single memo activities | AU/CDC/EPR/IM/SM/005 |
| Non-Travel Memo | NT | Non-travel memos | AU/CDC/EPR/IM/NT/003 |
| Special Memo | SPM | Special memos | AU/CDC/EPR/IM/SPM/001 |
| Service Request | SR | Service requests | AU/CDC/EPR/IM/SR/002 |
| Request ARF | ARF | Request ARFs | AU/CDC/EPR/IM/ARF/004 |

## Commands

### 1. Assign Missing Document Numbers

Assigns document numbers to records that are missing them.

```bash
# Basic usage - assign missing document numbers
php artisan assign:missing-document-numbers

# Use queue system (recommended for production)
php artisan assign:missing-document-numbers --queue --user=558

# Dry run to see what would be assigned
php artisan assign:missing-document-numbers --dry-run --user=558

# Assign for specific table only
php artisan assign:missing-document-numbers --table=activities --user=558

# Force assignment even if document numbers exist
php artisan assign:missing-document-numbers --force --user=558
```

**Options:**
- `--dry-run`: Show what would be assigned without making changes
- `--table=`: Assign numbers for specific table only
- `--force`: Force assignment even if document numbers exist
- `--user=558`: User ID to use for session and audit logging (default: 558)
- `--queue`: Dispatch jobs to queue instead of immediate assignment

### 2. Monitor Document Numbers

Continuously monitors for records without document numbers and optionally assigns them.

```bash
# One-time check
php artisan monitor:document-numbers --user=558

# Auto-assign missing document numbers
php artisan monitor:document-numbers --auto-assign --user=558

# Run as daemon (continuous monitoring)
php artisan monitor:document-numbers --daemon --user=558

# Custom check interval (default: 300 seconds = 5 minutes)
php artisan monitor:document-numbers --daemon --check-interval=60 --user=558
```

**Options:**
- `--user=558`: User ID to use for session and audit logging (default: 558)
- `--auto-assign`: Automatically assign document numbers to records that need them
- `--check-interval=300`: Check interval in seconds (default: 5 minutes)
- `--daemon`: Run as daemon to continuously monitor

### 3. Fix Document Number Conflicts

Resolves conflicts in document number assignment.

```bash
# Fix all conflicts
php artisan fix:document-number-conflicts

# Dry run to see what would be fixed
php artisan fix:document-number-conflicts --dry-run

# Fix for specific division
php artisan fix:document-number-conflicts --division=1

# Reset counters after fixing
php artisan fix:document-number-conflicts --reset-counters
```

## Queue System

The document number assignment uses Laravel's queue system to ensure proper processing and conflict resolution.

### Queue Worker

Make sure the queue worker is running:

```bash
# Process jobs once
php artisan queue:work --once

# Run queue worker continuously
php artisan queue:work

# Run with specific queue
php artisan queue:work --queue=default
```

### Queue Configuration

The system uses the `default` queue for document number assignment jobs. Ensure your queue configuration is properly set up in `config/queue.php`.

## Automatic Assignment

Document numbers are automatically assigned when:

1. **New records are created** - The `HasDocumentNumber` trait automatically dispatches `AssignDocumentNumberJob`
2. **Records are returned** - When a document is returned and needs a new number
3. **Document numbers are reset** - When a document number is set to null
4. **Conflicts are resolved** - When duplicate numbers are detected and resolved

## Conflict Resolution

The system includes robust conflict resolution:

1. **Retry Logic** - Automatically retries failed assignments
2. **Uniqueness Checks** - Verifies numbers across all tables
3. **Next Available Number** - Finds the next available number when conflicts occur
4. **Counter Reset** - Resets counters after deletions to prevent gaps

## Monitoring and Maintenance

### Regular Monitoring

Set up a cron job to run the monitor command regularly:

```bash
# Add to crontab - check every 5 minutes
*/5 * * * * cd /path/to/apm && php artisan monitor:document-numbers --auto-assign --user=558

# Or check every hour
0 * * * * cd /path/to/apm && php artisan monitor:document-numbers --auto-assign --user=558
```

### Systemd Service (Recommended)

Create a systemd service for continuous monitoring:

```bash
# Create service file
sudo nano /etc/systemd/system/apm-document-monitor.service

# Add content:
[Unit]
Description=APM Document Number Monitor
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/opt/homebrew/var/www/staff/apm
ExecStart=/usr/bin/php artisan monitor:document-numbers --daemon --auto-assign --user=558
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target

# Enable and start service
sudo systemctl enable apm-document-monitor.service
sudo systemctl start apm-document-monitor.service
```

## Troubleshooting

### Common Issues

1. **Document numbers not assigned**
   - Check if queue worker is running
   - Verify user ID has proper permissions
   - Check logs for errors

2. **Conflicts in document numbers**
   - Run `php artisan fix:document-number-conflicts`
   - Check for duplicate numbers across tables

3. **Queue jobs not processing**
   - Ensure queue worker is running
   - Check queue configuration
   - Verify database connection

### Logs

Check the following logs for issues:

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Queue logs
tail -f storage/logs/queue.log

# Systemd service logs
sudo journalctl -u apm-document-monitor.service -f
```

## API Methods

### DocumentNumberService

```php
// Generate document number for a model
$documentNumber = DocumentNumberService::generateForModel($model, 'QM');

// Generate document number directly
$documentNumber = DocumentNumberService::generateDocumentNumber('QM', 'IM', 1);

// Check if document number is unique
$isUnique = DocumentNumberService::isDocumentNumberUnique('AU/CDC/EPR/IM/QM/012');

// Find next available number
$nextNumber = DocumentNumberService::findNextAvailableNumber('QM', 'IM', 1);

// Reset counter after deletion
DocumentNumberService::resetCounterAfterDeletion('QM', 'IM', 1);
```

### AssignDocumentNumberJob

```php
// Dispatch job for document number assignment
AssignDocumentNumberJob::dispatch($model, 'QM');

// Dispatch with delay
AssignDocumentNumberJob::dispatch($model, 'QM')->delay(now()->addMinutes(5));
```

## Best Practices

1. **Always use the queue system** for document number assignment in production
2. **Set up monitoring** to catch missing document numbers early
3. **Use proper user IDs** for audit logging
4. **Monitor queue workers** to ensure they're running
5. **Regular maintenance** - run conflict resolution commands periodically
6. **Backup before major changes** - always backup before running force commands

## Security Considerations

- Document numbers are generated server-side to prevent manipulation
- User ID is required for audit logging
- Queue system prevents race conditions
- Uniqueness constraints prevent duplicates
- Conflict resolution maintains data integrity

## Performance Considerations

- Queue system prevents blocking operations
- Batch processing for large datasets
- Caching for frequently accessed data
- Database indexes on document_number columns
- Regular cleanup of old queue jobs
