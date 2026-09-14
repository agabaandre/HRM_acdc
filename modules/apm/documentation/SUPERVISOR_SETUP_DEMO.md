# Supervisor Setup for Africa CDC APM - Online Demo

## Quick Setup Commands

### 1. Install Supervisor (if not already installed)
```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install supervisor

# CentOS/RHEL
sudo yum install supervisor
```

### 2. Run the Automated Setup
```bash
cd /opt/homebrew/var/www/staff/apm
sudo ./setup-supervisor.sh
```

### 3. Verify Installation
```bash
# Check Supervisor status
sudo supervisorctl status

# Check queue worker logs
tail -f storage/logs/worker.log

# Check scheduler logs
tail -f storage/logs/scheduler.log
```

## Manual Setup (Alternative)

If the automated setup doesn't work, follow these steps:

### 1. Copy Configuration Files
```bash
# Copy worker configuration
sudo cp supervisor-laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf

# Copy scheduler configuration
sudo cp supervisor-laravel-scheduler.conf /etc/supervisor/conf.d/laravel-scheduler.conf
```

### 2. Set Permissions
```bash
# Set proper ownership
sudo chown -R www-data:www-data /opt/homebrew/var/www/staff/apm/storage/logs
sudo chmod -R 755 /opt/homebrew/var/www/staff/apm/storage/logs
```

### 3. Update Supervisor
```bash
# Reload configuration
sudo supervisorctl reread
sudo supervisorctl update

# Start services
sudo supervisorctl start laravel-worker:*
sudo supervisorctl start laravel-scheduler:*
```

## Configuration Details

### Queue Worker Configuration
- **Processes**: 2 workers running simultaneously
- **Memory Limit**: 512MB per worker
- **Retry Attempts**: 3 tries per job
- **Sleep Time**: 3 seconds between job checks
- **Max Runtime**: 1 hour before restart

### Scheduler Configuration
- **Processes**: 1 scheduler process
- **Check Interval**: Every 60 seconds
- **Verbose Logging**: Enabled for debugging

## Management Commands

### Check Status
```bash
# Overall status
sudo supervisorctl status

# Specific service status
sudo supervisorctl status laravel-worker:*
sudo supervisorctl status laravel-scheduler:*
```

### Control Services
```bash
# Start services
sudo supervisorctl start laravel-worker:*
sudo supervisorctl start laravel-scheduler:*

# Stop services
sudo supervisorctl stop laravel-worker:*
sudo supervisorctl stop laravel-scheduler:*

# Restart services
sudo supervisorctl restart laravel-worker:*
sudo supervisorctl restart laravel-scheduler:*

# Restart all
sudo supervisorctl restart all
```

### View Logs
```bash
# Queue worker logs
tail -f storage/logs/worker.log

# Scheduler logs
tail -f storage/logs/scheduler.log

# Laravel application logs
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log
```

## Troubleshooting

### Common Issues

#### 1. Services Won't Start
```bash
# Check Supervisor logs
sudo tail -f /var/log/supervisor/supervisord.log

# Check configuration syntax
sudo supervisorctl reread

# Check file permissions
ls -la storage/logs/
```

#### 2. Jobs Not Processing
```bash
# Check if workers are running
ps aux | grep "queue:work"

# Check failed jobs
php artisan queue:failed

# Process one job manually
php artisan queue:work --once --verbose
```

#### 3. Permission Issues
```bash
# Fix ownership
sudo chown -R www-data:www-data storage/logs/
sudo chmod -R 755 storage/logs/

# Fix file permissions
sudo chmod +x artisan
```

#### 4. Memory Issues
```bash
# Check memory usage
free -m

# Reduce worker memory limit
# Edit /etc/supervisor/conf.d/laravel-worker.conf
# Change --memory=512 to --memory=256
sudo supervisorctl restart laravel-worker:*
```

### Debugging Commands

#### Check Queue Status
```bash
# Use the monitoring script
./queue-monitor.sh status

# Check database directly
php artisan tinker
>>> \App\Models\Job::count()
>>> \App\Models\FailedJob::count()
```

#### Test Email Sending
```bash
# Test mail configuration
php artisan tinker
>>> Mail::raw('Test email', function($msg) { $msg->to('test@example.com')->subject('Test'); });
```

#### Check Database Connection
```bash
php artisan tinker
>>> DB::connection()->getPdo();
```

## Production Optimizations

### 1. Increase Worker Count
For high-traffic sites, increase the number of workers:
```bash
# Edit /etc/supervisor/conf.d/laravel-worker.conf
# Change numprocs=2 to numprocs=4
sudo supervisorctl reread
sudo supervisorctl update
```

### 2. Log Rotation
Set up log rotation to prevent disk space issues:
```bash
# Create logrotate configuration
sudo nano /etc/logrotate.d/laravel-worker
```

Add this content:
```
/opt/homebrew/var/www/staff/apm/storage/logs/worker.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    create 644 www-data www-data
    postrotate
        supervisorctl restart laravel-worker:*
    endscript
}
```

### 3. Monitoring
Set up monitoring to alert when services fail:
```bash
# Create monitoring script
nano /opt/homebrew/var/www/staff/apm/monitor-supervisor.sh
```

Add this content:
```bash
#!/bin/bash
WORKER_COUNT=$(sudo supervisorctl status | grep "laravel-worker" | grep "RUNNING" | wc -l)
if [ $WORKER_COUNT -eq 0 ]; then
    echo "ALERT: No queue workers running!"
    # Add your alert mechanism here (email, Slack, etc.)
fi
```

Make it executable and add to crontab:
```bash
chmod +x monitor-supervisor.sh
crontab -e
# Add: */5 * * * * /opt/homebrew/var/www/staff/apm/monitor-supervisor.sh
```

## Verification Checklist

After setup, verify everything is working:

- [ ] Supervisor is running: `sudo supervisorctl status`
- [ ] Queue workers are running: `ps aux | grep "queue:work"`
- [ ] No failed jobs: `php artisan queue:failed`
- [ ] Logs are being written: `tail -f storage/logs/worker.log`
- [ ] Emails are being sent: Check email logs
- [ ] Document numbers are assigned: Check new documents

## Support

If you encounter issues:
1. Check the logs: `tail -f storage/logs/worker.log`
2. Check Supervisor status: `sudo supervisorctl status`
3. Check system resources: `htop` or `top`
4. Verify database connectivity: `php artisan tinker` then `DB::connection()->getPdo()`
5. Test email configuration: `php artisan tinker` then `Mail::raw('Test', function($msg) { $msg->to('test@example.com')->subject('Test'); });`
