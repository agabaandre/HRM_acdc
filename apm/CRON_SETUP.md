# Cron Job Setup for Sync Commands

This guide explains how to set up automatic scheduling for your sync commands to run early morning and late night in GMT+3 timezone.

## Overview

The following sync commands are now scheduled to run automatically:

- **`directorates:sync`** - Syncs directorates data from Africa CDC API
- **`divisions:sync`** - Syncs divisions data from Africa CDC API  
- **`staff:sync`** - Syncs staff data from Africa CDC API

## Schedule (GMT+3 / East Africa Time)

### Early Morning Sync
- **6:00 AM** - Directorates sync
- **6:05 AM** - Divisions sync  
- **6:10 AM** - Staff sync

### Late Night Sync
- **11:00 PM** - Directorates sync
- **11:05 PM** - Divisions sync
- **11:10 PM** - Staff sync

## Setup Instructions

### 1. Verify Laravel Scheduler is Working

The scheduler is already configured and working! You can verify it with:

```bash
# Test the schedule list
php artisan schedule:list

# Test a specific command
php artisan directorates:sync
php artisan divisions:sync
php artisan staff:sync
```

### 2. Set Up Cron Job

Add this cron job to your server's crontab to run the Laravel scheduler every minute:

```bash
# Edit crontab
crontab -e

# Add this line (adjust the path to your Laravel project)
* * * * * cd /opt/homebrew/var/www/staff/apm && php artisan schedule:run >> /dev/null 2>&1
```

### 3. Alternative: Direct Cron Setup

If you prefer to set up cron jobs directly instead of using Laravel's scheduler:

```bash
# Edit crontab
crontab -e

# Early morning sync (6:00-6:10 AM GMT+3)
0 6 * * * cd /opt/homebrew/var/www/staff/apm && php artisan directorates:sync >> /var/log/sync-directorates.log 2>&1
5 6 * * * cd /opt/homebrew/var/www/staff/apm && php artisan divisions:sync >> /var/log/sync-divisions.log 2>&1
10 6 * * * cd /opt/homebrew/var/www/staff/apm && php artisan staff:sync >> /var/log/sync-staff.log 2>&1

# Late night sync (11:00-11:10 PM GMT+3)
0 23 * * * cd /opt/homebrew/var/www/staff/apm && php artisan directorates:sync >> /var/log/sync-directorates.log 2>&1
5 23 * * * cd /opt/homebrew/var/www/staff/apm && php artisan divisions:sync >> /var/log/sync-divisions.log 2>&1
10 23 * * * cd /opt/homebrew/var/www/staff/apm && php artisan staff:sync >> /var/log/sync-staff.log 2>&1
```

## Features

### Built-in Safety Features
- **`withoutOverlapping()`** - Prevents multiple instances from running simultaneously
- **`runInBackground()`** - Runs commands in background to avoid blocking
- **`onFailure()`** - Logs errors if commands fail
- **Timezone Support** - Automatically handles GMT+3 timezone

### Logging
All sync operations are logged to Laravel's log files. Check logs at:
- `storage/logs/laravel.log`

### Monitoring
You can monitor the scheduler with:
```bash
# View scheduled tasks
php artisan schedule:list

# Test the scheduler
php artisan schedule:test

# Run the scheduler manually
php artisan schedule:run
```

## Troubleshooting

### Check if Cron is Running
```bash
# Check cron service status
sudo systemctl status cron

# Check cron logs
sudo tail -f /var/log/cron.log
```

### Test Commands Manually
```bash
# Test each command individually
php artisan directorates:sync
php artisan divisions:sync  
php artisan staff:sync
```

### Check Laravel Logs
```bash
# View recent logs
tail -f storage/logs/laravel.log
```

### Verify Timezone
The scheduler is set to `Africa/Nairobi` timezone (GMT+3). Verify your server timezone:
```bash
# Check server timezone
date
timedatectl
```

## Security Notes

- Ensure your `.env` file has the correct API credentials
- The cron job runs as the user who owns the project
- Consider setting up log rotation for the sync logs
- Monitor disk space usage from logs

## Support

If you encounter issues:
1. Check the Laravel logs first
2. Verify cron service is running
3. Test commands manually
4. Check server timezone settings
5. Ensure proper file permissions
