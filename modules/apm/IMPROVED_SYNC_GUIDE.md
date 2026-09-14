# Improved Data Synchronization Guide

## Overview

The improved synchronization system provides better error handling, count verification, dynamic URLs, and comprehensive logging for all data synchronization operations.

## Features

### ✅ **Enhanced Features**
- **Dynamic URLs**: Configurable API endpoints via environment variables
- **Count Verification**: Ensures source count matches database count
- **Progress Bars**: Visual progress indication during sync
- **Retry Logic**: Automatic retry with exponential backoff
- **Comprehensive Logging**: Detailed logs for debugging and monitoring
- **Error Handling**: Better error messages and recovery
- **Data Validation**: Enhanced data cleaning and validation
- **Skip Logic**: Intelligent skipping of invalid records

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# API Configuration
STAFF_API_BASE_URL=https://cbp.africacdc.org
STAFF_API_TOKEN=YWZyY2FjZGNzdGFmZnRyYWNrZXI
STAFF_API_USERNAME=your_username
STAFF_API_PASSWORD=your_password
```

### API Endpoints

The system uses dynamic endpoints configured in `config/services.php`:

```php
'staff_api' => [
    'base_url' => env('STAFF_API_BASE_URL', 'https://cbp.africacdc.org'),
    'token' => env('STAFF_API_TOKEN', 'YWZyY2FjZGNzdGFmZnRyYWNrZXI'),
    'username' => env('STAFF_API_USERNAME'),
    'password' => env('STAFF_API_PASSWORD'),
    'endpoints' => [
        'staff' => '/staff/share/get_current_staff',
        'divisions' => '/staff/share/divisions',
        'directorates' => '/staff/share/directorates',
    ],
],
```

## Commands

### Individual Entity Sync

#### Staff Sync
```bash
# Improved staff sync
php artisan staff:sync-improved

# Force sync even if counts match
php artisan staff:sync-improved --force
```

#### Divisions Sync
```bash
# Improved divisions sync
php artisan divisions:sync-improved

# Force sync even if counts match
php artisan divisions:sync-improved --force
```

#### Directorates Sync
```bash
# Improved directorates sync
php artisan directorates:sync-improved

# Force sync even if counts match
php artisan directorates:sync-improved --force
```

### Master Sync Command

#### Sync All Data
```bash
# Sync all entities (directorates, divisions, staff)
php artisan data:sync-all

# Force sync all entities
php artisan data:sync-all --force

# Sync specific entity only
php artisan data:sync-all --entity=staff
php artisan data:sync-all --entity=divisions
php artisan data:sync-all --entity=directorates
```

## Output Examples

### Successful Sync
```
🚀 Starting improved staff sync from Africa CDC API...
Current database count: 245
Making API request to: https://cbp.africacdc.org/staff/share/get_current_staff/YWZyY2FjZGNzdGFmZnRyYWNrZXI
Successfully fetched 250 records from API
Processing 250 staff records...
████████████████████████████████████████ 100%

==================================================
SYNC RESULTS
==================================================
Source API Records: 250
Database Records: 250
Created: 5
Updated: 245
Failed: 0
Skipped: 0
✅ SUCCESS: Source count matches database count
==================================================
```

### Sync with Issues
```
🚀 Starting improved divisions sync from Africa CDC API...
Current database count: 12
Making API request to: https://cbp.africacdc.org/staff/share/divisions/YWZyY2FjZGNzdGFmZnRyYWNrZXI
Successfully fetched 15 records from API
Processing 15 division records...
████████████████████████████████████████ 100%

==================================================
SYNC RESULTS
==================================================
Source API Records: 15
Database Records: 14
Created: 2
Updated: 12
Failed: 1
Skipped: 0
⚠️  WARNING: Source count (15) does not match database count (14)
==================================================
```

## Data Validation

### Staff Data
- **Email Validation**: Only syncs staff with valid @africacdc.org emails
- **Required Fields**: staff_id, work_email, fname, lname
- **Date Cleaning**: Converts invalid dates to null
- **String Cleaning**: Trims whitespace and handles empty values

### Divisions Data
- **Required Fields**: division_id, division_name
- **Date Cleaning**: Handles '0000-00-00' dates
- **Numeric Cleaning**: Validates numeric fields

### Directorates Data
- **Required Fields**: name
- **Active Status**: Determines active status from various fields
- **ID Handling**: Creates new records if no ID provided

## Error Handling

### API Errors
- **Retry Logic**: 3 attempts with exponential backoff
- **Timeout Handling**: 60-second timeout per request
- **Connection Errors**: Detailed error messages

### Data Errors
- **Validation Errors**: Skip invalid records with logging
- **Database Errors**: Log and continue processing
- **Missing Fields**: Use default values or skip

## Logging

### Log Levels
- **INFO**: Successful operations, counts, results
- **WARNING**: Count mismatches, skipped records
- **ERROR**: Failed operations, API errors

### Log Structure
```json
{
    "level": "info",
    "message": "Sync completed for Staff",
    "context": {
        "source_count": 250,
        "db_count": 250,
        "created": 5,
        "updated": 245,
        "failed": 0,
        "skipped": 0,
        "count_match": true
    }
}
```

## Monitoring

### Check Sync Status
```bash
# Check recent sync logs
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log | grep -i sync

# Check specific entity sync
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log | grep -i "staff sync"
```

### Database Verification
```bash
# Check counts
php artisan tinker
>>> \App\Models\Staff::count()
>>> \App\Models\Division::count()
>>> \App\Models\Directorate::count()
```

## Troubleshooting

### Common Issues

#### 1. API Connection Failed
```bash
# Check API credentials
php artisan tinker
>>> config('services.staff_api')

# Test API endpoint manually
curl -u "username:password" "https://cbp.africacdc.org/staff/share/get_current_staff/YWZyY2FjZGNzdGFmZnRyYWNrZXI"
```

#### 2. Count Mismatch
```bash
# Check for failed records
grep -i "failed to sync" storage/logs/laravel-$(date +%Y-%m-%d).log

# Check for skipped records
grep -i "skipped" storage/logs/laravel-$(date +%Y-%m-%d).log
```

#### 3. Memory Issues
```bash
# Increase memory limit
php -d memory_limit=512M artisan staff:sync-improved
```

### Debug Mode
```bash
# Enable verbose logging
php artisan staff:sync-improved -v

# Check specific record processing
php artisan tinker
>>> $staff = \App\Models\Staff::where('staff_id', 'SOME_ID')->first();
>>> $staff->toArray();
```

## Scheduling

### Add to Cron
```bash
# Edit crontab
crontab -e

# Add daily sync at 2 AM
0 2 * * * cd /opt/homebrew/var/www/staff/apm && php artisan data:sync-all >> storage/logs/sync.log 2>&1

# Add hourly staff sync
0 * * * * cd /opt/homebrew/var/www/staff/apm && php artisan staff:sync-improved >> storage/logs/staff-sync.log 2>&1
```

### Laravel Scheduler
Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Daily full sync at 2 AM
    $schedule->command('data:sync-all')
             ->dailyAt('02:00')
             ->withoutOverlapping()
             ->runInBackground();

    // Hourly staff sync
    $schedule->command('staff:sync-improved')
             ->hourly()
             ->withoutOverlapping()
             ->runInBackground();
}
```

## Performance Optimization

### Batch Processing
- Process records in batches for large datasets
- Use database transactions for consistency
- Implement memory-efficient processing

### Caching
- Cache API responses for short periods
- Use Redis for session storage
- Implement query result caching

### Monitoring
- Set up alerts for sync failures
- Monitor API rate limits
- Track sync performance metrics

## Migration from Old Commands

### Replace Old Commands
```bash
# Old commands (still work)
php artisan staff:sync
php artisan divisions:sync
php artisan directorates:sync

# New improved commands
php artisan staff:sync-improved
php artisan divisions:sync-improved
php artisan directorates:sync-improved
php artisan data:sync-all
```

### Gradual Migration
1. Test new commands in development
2. Run both old and new commands in parallel
3. Compare results and verify accuracy
4. Switch to new commands in production
5. Remove old commands after verification

## Support

For issues or questions:
1. Check logs: `tail -f storage/logs/laravel-$(date +%Y-%m-%d).log`
2. Run debug commands: `php artisan data:sync-all --entity=staff -v`
3. Verify configuration: `php artisan tinker` then `config('services.staff_api')`
4. Check database connectivity: `php artisan tinker` then `DB::connection()->getPdo()`
