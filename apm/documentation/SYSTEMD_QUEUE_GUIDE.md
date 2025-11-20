# Laravel Queue and Schedule Configuration with Systemd

This guide will help you configure Laravel queue workers and scheduled tasks using systemd on Linux systems.

## Quick Start (Automated Setup)

**For automated setup, use the provided shell script:**

```bash
# Make script executable
chmod +x setup-systemd.sh

# Run the setup script (requires sudo)
sudo ./setup-systemd.sh

# Or with custom options
sudo ./setup-systemd.sh --path /var/www/myapp --user nginx --queue default
```

The script will:
- ✅ Check prerequisites
- ✅ Create systemd service files
- ✅ Set proper permissions
- ✅ Enable and start services
- ✅ Show service status

**Available options:**
- `-h, --help` - Show help message
- `-p, --path PATH` - Set application path
- `-u, --user USER` - Set service user
- `-q, --queue QUEUE` - Set queue name
- `--skip-start` - Don't start services (only create and enable)
- `--skip-permissions` - Don't set file permissions
- `--status` - Show service status and exit

## Prerequisites

- Linux system with systemd (Ubuntu 18.04+, CentOS 7+, Debian 9+, etc.)
- Laravel application installed
- PHP CLI installed
- Supervisor or systemd access

## Manual Setup (Alternative)

If you prefer manual setup or need to customize the configuration, follow the steps below:

## 1. Queue Worker Configuration

### Create Queue Worker Service File

Create a systemd service file for the Laravel queue worker:

```bash
sudo nano /etc/systemd/system/laravel-queue-worker.service
```

Add the following content (adjust paths and user as needed):

```ini
[Unit]
Description=Laravel Queue Worker
After=network.target mysql.service redis.service

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=3
WorkingDirectory=/opt/homebrew/var/www/knowledge_hub
ExecStart=/usr/bin/php artisan queue:work --queue=default --tries=3 --timeout=90 --sleep=3 --max-jobs=1000 --max-time=3600
StandardOutput=journal
StandardError=journal

# Optional: Increase memory limit
Environment="PHP_MEMORY_LIMIT=512M"

[Install]
WantedBy=multi-user.target
```

**Important Configuration Options:**
- `--queue=default`: Process jobs from the 'default' queue
- `--tries=3`: Retry failed jobs up to 3 times
- `--timeout=90`: Maximum time a job can run (seconds)
- `--sleep=3`: Wait 3 seconds between jobs when queue is empty
- `--max-jobs=1000`: Restart worker after processing 1000 jobs (prevents memory leaks)
- `--max-time=3600`: Restart worker after 1 hour (prevents memory leaks)

### Enable and Start Queue Worker

```bash
# Reload systemd daemon
sudo systemctl daemon-reload

# Enable service to start on boot
sudo systemctl enable laravel-queue-worker.service

# Start the service
sudo systemctl start laravel-queue-worker.service

# Check status
sudo systemctl status laravel-queue-worker.service

# View logs
sudo journalctl -u laravel-queue-worker.service -f
```

### Managing Queue Worker

```bash
# Restart the service
sudo systemctl restart laravel-queue-worker.service

# Stop the service
sudo systemctl stop laravel-queue-worker.service

# View real-time logs
sudo journalctl -u laravel-queue-worker.service -f --lines=50

# View recent logs
sudo journalctl -u laravel-queue-worker.service --since "1 hour ago"
```

## 2. Schedule (Cron) Configuration

### Create Schedule Service File

Create a systemd service file for Laravel scheduler:

```bash
sudo nano /etc/systemd/system/laravel-schedule.service
```

Add the following content:

```ini
[Unit]
Description=Laravel Scheduler
After=network.target mysql.service redis.service

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=60
WorkingDirectory=/opt/homebrew/var/www/knowledge_hub
ExecStart=/usr/bin/php artisan schedule:run
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

### Create Schedule Timer File

Create a systemd timer to run the scheduler every minute:

```bash
sudo nano /etc/systemd/system/laravel-schedule.timer
```

Add the following content:

```ini
[Unit]
Description=Run Laravel Scheduler Every Minute
Requires=laravel-schedule.service

[Timer]
OnBootSec=1min
OnUnitActiveSec=1min
AccuracySec=1s

[Install]
WantedBy=timers.target
```

### Enable and Start Schedule

```bash
# Reload systemd daemon
sudo systemctl daemon-reload

# Enable timer to start on boot
sudo systemctl enable laravel-schedule.timer

# Start the timer
sudo systemctl start laravel-schedule.timer

# Check timer status
sudo systemctl status laravel-schedule.timer

# Check service status
sudo systemctl status laravel-schedule.service

# View logs
sudo journalctl -u laravel-schedule.service -f
```

### Managing Schedule

```bash
# List all timers
sudo systemctl list-timers

# Check if timer is active
sudo systemctl is-active laravel-schedule.timer

# Restart timer
sudo systemctl restart laravel-schedule.timer

# View schedule logs
sudo journalctl -u laravel-schedule.service -f
```

## 3. Alternative: Using Cron (Simpler but Less Control)

If you prefer using traditional cron instead of systemd timer:

```bash
# Edit crontab
sudo crontab -e -u www-data

# Add this line (runs every minute)
* * * * * cd /opt/homebrew/var/www/knowledge_hub && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

## 4. Queue Configuration (config/queue.php)

Ensure your queue configuration is set correctly. Check `config/queue.php`:

```php
'default' => env('QUEUE_CONNECTION', 'database'),

'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
    ],
    
    // Or use Redis for better performance
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
],
```

## 5. Database Queue Setup

If using database queue driver, create the jobs table:

```bash
php artisan queue:table
php artisan migrate
```

## 6. Testing Configuration

### Test Queue Worker

```bash
# Dispatch a test job
php artisan test:email test@example.com

# Check queue status
php artisan queue:work --once --verbose

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### Test Schedule

```bash
# Run scheduler manually
php artisan schedule:run

# List scheduled tasks
php artisan schedule:list

# Test specific scheduled command
php artisan telescope:prune --hours=4
```

## 7. Monitoring and Maintenance

### View Queue Statistics

```bash
# Check queue worker status
sudo systemctl status laravel-queue-worker.service

# Monitor queue worker logs
sudo journalctl -u laravel-queue-worker.service -f

# Check for failed jobs
php artisan queue:failed

# Monitor queue database (if using database driver)
# Connect to database and check the 'jobs' table
```

### Common Commands

```bash
# Clear failed jobs
php artisan queue:flush

# Restart queue worker (reloads code changes)
sudo systemctl restart laravel-queue-worker.service

# Check systemd service status
sudo systemctl status laravel-queue-worker.service
sudo systemctl status laravel-schedule.timer

# View all systemd services
sudo systemctl list-units --type=service | grep laravel
```

## 8. Troubleshooting

### Queue Worker Not Processing Jobs

1. Check if service is running:
   ```bash
   sudo systemctl status laravel-queue-worker.service
   ```

2. Check logs:
   ```bash
   sudo journalctl -u laravel-queue-worker.service -n 50
   ```

3. Check queue connection:
   ```bash
   php artisan queue:work --once --verbose
   ```

### Schedule Not Running

1. Check timer status:
   ```bash
   sudo systemctl status laravel-schedule.timer
   ```

2. Check service logs:
   ```bash
   sudo journalctl -u laravel-schedule.service -n 50
   ```

3. Manually test:
   ```bash
   php artisan schedule:run
   ```

### Permission Issues

Ensure the service user (www-data) has proper permissions:

```bash
# Set ownership
sudo chown -R www-data:www-data /opt/homebrew/var/www/knowledge_hub/storage
sudo chown -R www-data:www-data /opt/homebrew/var/www/knowledge_hub/bootstrap/cache

# Set permissions
sudo chmod -R 775 /opt/homebrew/var/www/knowledge_hub/storage
sudo chmod -R 775 /opt/homebrew/var/www/knowledge_hub/bootstrap/cache
```

## 9. Production Recommendations

### Multiple Queue Workers

For high traffic, run multiple queue workers:

```bash
# Create additional service files
sudo cp /etc/systemd/system/laravel-queue-worker.service /etc/systemd/system/laravel-queue-worker-2.service

# Edit and change the service name
sudo nano /etc/systemd/system/laravel-queue-worker-2.service

# Enable and start
sudo systemctl enable laravel-queue-worker-2.service
sudo systemctl start laravel-queue-worker-2.service
```

### Using Supervisor (Alternative to Systemd)

Supervisor is another popular option for managing Laravel queues:

```bash
# Install supervisor
sudo apt-get install supervisor  # Ubuntu/Debian
sudo yum install supervisor       # CentOS/RHEL

# Create config file
sudo nano /etc/supervisor/conf.d/laravel-queue-worker.conf

# Add configuration
[program:laravel-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /opt/homebrew/var/www/knowledge_hub/artisan queue:work --queue=default --tries=3 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/opt/homebrew/var/www/knowledge_hub/storage/logs/queue-worker.log
stopwaitsecs=3600

# Reload and start
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-queue-worker:*
```

## 10. Environment Variables

Ensure your `.env` file has:

```env
QUEUE_CONNECTION=database
# or
QUEUE_CONNECTION=redis

# For Redis queue
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_QUEUE=default
```

## Summary

After following this guide, you should have:

1. ✅ Queue worker running via systemd service
2. ✅ Scheduler running via systemd timer
3. ✅ All services configured to start on boot
4. ✅ Proper logging and monitoring setup
5. ✅ All mail jobs using the 'default' queue

**Quick Reference:**

```bash
# Queue Worker
sudo systemctl start laravel-queue-worker.service
sudo systemctl status laravel-queue-worker.service
sudo journalctl -u laravel-queue-worker.service -f

# Scheduler
sudo systemctl start laravel-schedule.timer
sudo systemctl status laravel-schedule.timer
sudo journalctl -u laravel-schedule.service -f
```

