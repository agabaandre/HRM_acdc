# Database Backup System

Automatic database backup system with retention policies and OneDrive integration.

## Features

- **Automatic Daily Backups**: Creates daily backups at configured time
- **Automatic Monthly Backups**: Creates monthly backups on specified day
- **Automatic Annual Backups**: Creates annual backups (one per year)
- **Multiple Database Support**: Configure and backup multiple databases on the same server
- **Database Management Interface**: Web-based interface to add, edit, and manage database configurations
- **Per-Database Retention Policies**: 
  - Keeps daily backups for last N days (default: 5 days, configurable)
  - Keeps monthly backups for last N months (default: 6 months, configurable)
  - Keeps annual backups for last N years (default: 1 year, configurable)
  - **One backup per database per day/month/year**: Only the most recent backup for each database within each period is kept
- **OneDrive Integration**: Optional automatic upload to OneDrive
- **Automatic Cleanup**: Removes old backups based on retention policies
- **Compression**: Optional gzip/zip compression
- **Email Notifications**: Optional email notifications on backup completion/failure
- **Disk Space Monitoring**: Automatic disk space monitoring with email alerts
- **Secure Password Storage**: Database passwords are encrypted in the database
- **Connection Testing**: Test database connections before saving configurations

## Configuration

Edit `.env` file or `config/backup.php`:

```env
# Backup Storage Path
BACKUP_STORAGE_PATH=/path/to/backups

# Retention Policies
BACKUP_DAILY_DAYS=5
BACKUP_MONTHLY_MONTHS=6
BACKUP_ANNUAL_YEARS=1

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
# Backup notifications will use BACKUP_DISK_NOTIFICATION_EMAILS if configured
# Otherwise, falls back to BACKUP_NOTIFICATION_EMAIL (single email)
BACKUP_NOTIFICATION_EMAIL=admin@example.com

# Disk Space Monitoring
BACKUP_DISK_MONITOR_ENABLED=true
BACKUP_DISK_WARNING_THRESHOLD=80
BACKUP_DISK_CRITICAL_THRESHOLD=90
BACKUP_DISK_NOTIFICATION_EMAILS=admin1@example.com,admin2@example.com
BACKUP_DISK_CHECK_INTERVAL=24
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

# Annual backup on January 1st at 2:00 AM (optional - can be run manually)
0 2 1 1 * cd /path/to/project && php artisan backup:database --type=annual --cleanup >> /dev/null 2>&1

# Cleanup old backups daily at 3:00 AM
0 3 * * * cd /path/to/project && php artisan backup:cleanup >> /dev/null 2>&1

# Check disk space every 6 hours
0 */6 * * * cd /path/to/project && php artisan backup:check-disk-space >> /dev/null 2>&1
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
    
    // Annual backup (on January 1st)
    $schedule->command('backup:database --type=annual --cleanup')
             ->yearlyOn(1, 1, '02:00');
    
    // Cleanup
    $schedule->command('backup:cleanup')
             ->dailyAt('03:00');
    
    // Disk space monitoring
    $schedule->command('backup:check-disk-space')
             ->everySixHours();
}
```

## Manual Commands

### Create Backup
```bash
# Daily backup
php artisan backup:database --type=daily

# Monthly backup
php artisan backup:database --type=monthly

# Annual backup
php artisan backup:database --type=annual

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

## Disk Space Monitoring

The system includes automatic disk space monitoring with email notifications:

- **Warning Threshold**: Default 80% - sends warning email to administrators
- **Critical Threshold**: Default 90% - sends critical alert email
- **Automatic Checks**: Can be scheduled via cron or Laravel scheduler
- **Manual Check**: Available via web interface or `php artisan backup:check-disk-space`

### Configuration
```env
BACKUP_DISK_MONITOR_ENABLED=true
BACKUP_DISK_WARNING_THRESHOLD=80
BACKUP_DISK_CRITICAL_THRESHOLD=90
BACKUP_DISK_NOTIFICATION_EMAILS=admin1@example.com,admin2@example.com
BACKUP_DISK_CHECK_INTERVAL=24
```

## Multiple Database Support

The backup system supports backing up multiple databases on the same server:

1. **Add Databases**: Use the "Manage Databases" button in the backup interface to add database configurations
2. **Database Configuration**: Each database can have its own host, port, username, and password
3. **Priority System**: Set backup priority to control the order in which databases are backed up
4. **Active/Inactive**: Enable or disable databases without deleting configurations
5. **Default Database**: Mark one database as default (falls back to `.env` config if no databases configured)

### Database Management Features

- **Web Interface**: Add, edit, and delete database configurations through the web UI
- **Encrypted Passwords**: Database passwords are encrypted using Laravel's Crypt
- **Connection Testing**: Test database connectivity before saving configurations
- **Priority Ordering**: Higher priority databases are backed up first

## File Naming

- Daily backups: `backup_daily_dbname_YYYY-MM-DD_HH-MM-SS.sql[.gz]`
- Monthly backups: `backup_monthly_dbname_YYYY-MM-DD_HH-MM-SS.sql[.gz]`
- Annual backups: `backup_annual_dbname_YYYY-MM-DD_HH-MM-SS.sql[.gz]`

Where `dbname` is the database name from the configuration.

## Retention Policy

The retention policy ensures efficient storage management:

- **Daily Backups**: 
  - One backup per database per day (most recent kept)
  - Kept for last N days (default: 5)
  - If multiple backups exist for the same database on the same day, only the most recent is kept
  
- **Monthly Backups**: 
  - One backup per database per month (most recent kept)
  - Kept for last N months (default: 6)
  - If multiple backups exist for the same database in the same month, only the most recent is kept
  
- **Annual Backups**: 
  - One backup per database per year (most recent kept)
  - Kept for last N years (default: 1)
  - If multiple backups exist for the same database in the same year, only the most recent is kept

- **Per-Database Policy**: Each database has independent retention. Backups from different databases don't affect each other.
- **Automatic Cleanup**: Older backups are automatically deleted during cleanup based on their respective retention policies
- **Manual Deletion Disabled**: Backups cannot be manually deleted to prevent accidental data loss. Only automated cleanup removes old backups.

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

### Disk Space Monitoring Not Working
- Verify `BACKUP_DISK_MONITOR_ENABLED=true` in `.env`
- Check notification emails are configured
- Ensure Exchange OAuth credentials are set for email sending
- Run manual check: `php artisan backup:check-disk-space`

