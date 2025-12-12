# Database Backup System

Automatic database backup system with retention policies and OneDrive integration.

## Features

- **Automatic Daily Backups**: Creates daily backups at configured time
- **Automatic Monthly Backups**: Creates monthly backups on specified day
- **Retention Policies**: 
  - Keeps daily backups for last N days (default: 5 days (configurable)
  - Keeps monthly backups for last N months (configurable)
- **OneDrive Integration**: Optional automatic upload to OneDrive
- **Automatic Cleanup**: Removes old backups based on retention policies
- **Compression**: Optional gzip/zip compression
- **Email Notifications**: Optional email notifications on backup completion/failure

## Configuration

Edit `.env` file or `config/backup.php`:

```env
# Backup Storage Path
BACKUP_STORAGE_PATH=/path/to/backups

# Retention Policies
BACKUP_DAILY_DAYS=5
BACKUP_MONTHLY_MONTHS=5

# OneDrive Integration
BACKUP_ONEDRIVE_ENABLED=true
BACKUP_ONEDRIVE_FOLDER=Database Backups

# Backup Schedule
BACKUP_DAILY_TIME=02:00
BACKUP_MONTHLY_DAY=1

# Compression
BACKUP_COMPRESSION_ENABLED=true
BACKUP_COMPRESSION_FORMAT=gzip

# Notifications
BACKUP_NOTIFICATION_ENABLED=true
BACKUP_NOTIFICATION_EMAIL=admin@example.com
```

## Setup

1. **Configure Backup Path**: Set `BACKUP_STORAGE_PATH` in `.env` or ensure default path is writable
2. **Configure OneDrive** (Optional): Set `BACKUP_ONEDRIVE_ENABLED=true` and ensure Exchange OAuth is configured
3. **Set Up Cron Job**: Add to crontab:

```bash
# Daily backup at 2:00 AM
0 2 * * * cd /path/to/project && php artisan backup:database --type=daily --cleanup >> /dev/null 2>&1

# Monthly backup on 1st of month at 2:00 AM
0 2 1 * * cd /path/to/project && php artisan backup:database --type=monthly --cleanup >> /dev/null 2>&1

# Cleanup old backups daily at 3:00 AM
0 3 * * * cd /path/to/project && php artisan backup:cleanup >> /dev/null 2>&1
```

Or use Laravel's task scheduler (add to `app/Console/Kernel.php`):

```php
protected function schedule(Schedule $schedule)
{
    // Daily backup
    $schedule->command('backup:database --type=daily --cleanup')
             ->dailyAt('02:00');
    
    // Monthly backup
    $schedule->command('backup:database --type=monthly --cleanup')
             ->monthlyOn(1, '02:00');
    
    // Cleanup
    $schedule->command('backup:cleanup')
             ->dailyAt('03:00');
}
```

## Manual Commands

### Create Backup
```bash
# Daily backup
php artisan backup:database --type=daily

# Monthly backup
php artisan backup:database --type=monthly

# With cleanup
php artisan backup:database --type=daily --cleanup
```

### Cleanup Old Backups
```bash
php artisan backup:cleanup
```

### View Statistics
```bash
php artisan backup:stats
```

## OneDrive Integration

The system uses existing Microsoft OAuth credentials from Exchange email configuration. Ensure:
- `EXCHANGE_TENANT_ID` is set
- `EXCHANGE_CLIENT_ID` is set
- `EXCHANGE_CLIENT_SECRET` is set
- Application has `Files.ReadWrite` permission in Azure AD

To enable OneDrive backups:
1. Set `BACKUP_ONEDRIVE_ENABLED=true` in `.env`
2. Optionally set `BACKUP_ONEDRIVE_FOLDER` to customize folder name
3. Backups will automatically upload to OneDrive after creation

## File Naming

- Daily backups: `backup_daily_YYYY-MM-DD_HH-MM-SS.sql[.gz]`
- Monthly backups: `backup_monthly_YYYY-MM-DD_HH-MM-SS.sql[.gz]`

## Retention Policy

- **Daily Backups**: Kept for last N days (default: 5)
- **Monthly Backups**: One backup per month, kept for last N months (default: 5)
- Older backups are automatically deleted during cleanup

## Troubleshooting

### Backup Fails
- Check database credentials in `.env`
- Ensure `mysqldump` is available in PATH
- Check backup storage path is writable
- Check logs: `storage/logs/laravel.log`

### OneDrive Upload Fails
- Verify OAuth credentials are correct
- Check application has `Files.ReadWrite` scope
- Check logs for detailed error messages

### Cleanup Not Working
- Verify retention settings in config
- Check file permissions on backup directory
- Run cleanup manually: `php artisan backup:cleanup`

