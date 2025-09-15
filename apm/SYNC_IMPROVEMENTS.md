# Data Synchronization Improvements

## Overview

The existing sync commands have been enhanced with the following improvements:

- **Dynamic URLs**: Configurable API endpoints via environment variables
- **Count Verification**: Ensures source count matches database count
- **Progress Bars**: Visual progress indication during sync
- **Better Error Handling**: Enhanced error messages and logging
- **Retry Logic**: Automatic retry with exponential backoff
- **Comprehensive Reporting**: Detailed sync results and statistics

## Environment Variables

Add these to your `.env` file for dynamic URL configuration:

```env
# API Base URL (includes full path structure)
BASE_URL=http://localhost/staff/

# API Configuration
STAFF_API_TOKEN=YWZyY2FjZGNzdGFmZnRyYWNrZXI
STAFF_API_USERNAME=your_username
STAFF_API_PASSWORD=your_password
```

## Improved Commands

### Staff Sync
```bash
# Basic sync
php artisan staff:sync

# Force sync even if counts match
php artisan staff:sync --force
```

### Divisions Sync
```bash
# Basic sync
php artisan divisions:sync

# Force sync even if counts match
php artisan divisions:sync --force
```

### Directorates Sync
```bash
# Basic sync
php artisan directorates:sync

# Force sync even if counts match
php artisan directorates:sync --force
```

## New Features

### 1. Dynamic URLs
- API base URL uses `BASE_URL` from environment
- API token configurable via `STAFF_API_TOKEN`
- Endpoints configurable in `config/services.php`

### 2. Count Verification
- Shows source API record count
- Shows database record count
- Warns if counts don't match
- Confirms success when counts match

### 3. Progress Bars
- Visual progress indication during processing
- Shows current progress and estimated completion

### 4. Enhanced Error Handling
- Better error messages with context
- Automatic retry with exponential backoff
- Detailed logging of failures

### 5. Comprehensive Reporting
```
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

## Configuration

The sync commands now use dynamic configuration from `config/services.php`:

```php
'staff_api' => [
    'base_url' => env('BASE_URL', 'http://localhost/staff/'),
    'token' => env('STAFF_API_TOKEN', 'YWZyY2FjZGNzdGFmZnRyYWNrZXI'),
    'username' => env('STAFF_API_USERNAME'),
    'password' => env('STAFF_API_PASSWORD'),
    'endpoints' => [
        'staff' => '/share/get_current_staff',
        'divisions' => '/share/divisions',
        'directorates' => '/share/directorates',
    ],
],
```

## Benefits

1. **Reliability**: Better error handling and retry logic
2. **Visibility**: Progress bars and detailed reporting
3. **Flexibility**: Dynamic URLs and configurable endpoints
4. **Monitoring**: Comprehensive logging and count verification
5. **Maintainability**: Cleaner code and better error messages

## Usage Examples

### Check Current Counts
```bash
# Check staff count
php artisan tinker
>>> \App\Models\Staff::count()

# Check divisions count
>>> \App\Models\Division::count()

# Check directorates count
>>> \App\Models\Directorate::count()
```

### Run All Syncs
```bash
# Run all sync commands
php artisan staff:sync
php artisan divisions:sync
php artisan directorates:sync
```

### Monitor Sync Logs
```bash
# Watch sync logs in real-time
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log | grep -i sync
```

## Troubleshooting

### Common Issues

1. **Count Mismatch**: Check logs for failed records
2. **API Errors**: Verify credentials and network connectivity
3. **Memory Issues**: Increase PHP memory limit if needed

### Debug Commands
```bash
# Run with verbose output
php artisan staff:sync -vvv

# Check API configuration
php artisan tinker
>>> config('services.staff_api')
```

The improved sync commands provide better reliability, visibility, and maintainability for your data synchronization needs.
