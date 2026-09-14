# Queue Troubleshooting Guide for Africa CDC APM

## Quick Diagnosis Commands

### 1. Check Queue Status
```bash
# Use the monitoring script
./queue-monitor.sh status

# Or check manually
php artisan queue:failed
ps aux | grep "queue:work"
```

### 2. Common Issues & Solutions

#### Issue: No Queue Worker Running
**Symptoms:**
- Jobs are queued but never processed
- No email notifications sent
- Documents don't get assigned numbers

**Solutions:**
```bash
# Start queue worker
./queue-monitor.sh start

# Or manually
nohup php artisan queue:work --daemon > /dev/null 2>&1 &

# For production with systemd
sudo cp laravel-queue-worker.service /etc/systemd/system/
sudo systemctl enable laravel-queue-worker
sudo systemctl start laravel-queue-worker
```

#### Issue: Failed Jobs
**Symptoms:**
- Jobs appear in `php artisan queue:failed`
- Error messages in logs

**Solutions:**
```bash
# Retry all failed jobs
./queue-monitor.sh retry

# Or manually
php artisan queue:retry all

# Clear permanently failed jobs
./queue-monitor.sh clear
```

#### Issue: Jobs Stuck in Queue
**Symptoms:**
- Jobs queued but not processing
- Queue worker running but jobs remain

**Solutions:**
```bash
# Restart queue worker
./queue-monitor.sh restart

# Process one job manually to see errors
./queue-monitor.sh process
```

#### Issue: Email Jobs Failing
**Symptoms:**
- `SendMatrixNotificationJob` in failed jobs
- No email notifications received

**Common Causes:**
1. **Missing Route Error**: `Route [single-memos.show] not defined`
   - **Fix**: Already fixed in Activity model
   
2. **SMTP Configuration Issues**:
   ```bash
   # Check mail configuration
   php artisan tinker
   >>> config('mail')
   ```

3. **Log Permission Issues**:
   ```bash
   # Fix log permissions
   sudo chown -R www-data:www-data storage/logs/
   chmod -R 775 storage/logs/
   ```

#### Issue: Document Number Jobs Failing
**Symptoms:**
- `AssignDocumentNumberJob` in failed jobs
- Documents created without numbers

**Common Causes:**
1. **Model Not Found**: Activity/Matrix deleted after job queued
   - **Fix**: Clear old failed jobs
   ```bash
   ./queue-monitor.sh clear
   ```

2. **Database Connection Issues**:
   ```bash
   # Test database connection
   php artisan tinker
   >>> DB::connection()->getPdo();
   ```

## Production Setup

### 1. Systemd Service (Recommended)
```bash
# Copy service file
sudo cp laravel-queue-worker.service /etc/systemd/system/

# Enable and start
sudo systemctl enable laravel-queue-worker
sudo systemctl start laravel-queue-worker

# Check status
sudo systemctl status laravel-queue-worker
```

### 2. Supervisor (Alternative)
```bash
# Install supervisor
sudo apt-get install supervisor

# Create configuration
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

Add this content:
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /opt/homebrew/var/www/staff/apm/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/opt/homebrew/var/www/staff/apm/storage/logs/worker.log
stopwaitsecs=3600
```

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

### 3. Cron Job (Backup)
```bash
# Add to crontab
crontab -e

# Add this line to run every minute
* * * * * cd /opt/homebrew/var/www/staff/apm && php artisan queue:work --once
```

## Monitoring Commands

### Real-time Monitoring
```bash
# Monitor queue status
./queue-monitor.sh monitor

# Watch logs
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log

# Check specific job processing
php artisan queue:work --once --verbose
```

### Health Checks
```bash
# Check if worker is running
ps aux | grep "queue:work"

# Check failed jobs count
php artisan queue:failed | grep -c "database@default"

# Check queue size
php artisan tinker --execute="echo \App\Models\Job::count();"
```

## Email Configuration

### Test Email Sending
```bash
# Test mail configuration
php artisan tinker
>>> Mail::raw('Test email', function($msg) { $msg->to('test@example.com')->subject('Test'); });

# Check mail logs
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log | grep -i mail
```

### Common Email Issues
1. **SMTP Settings**: Check `.env` file for correct SMTP configuration
2. **Authentication**: Ensure SMTP credentials are correct
3. **Firewall**: Check if SMTP port (587/465) is open
4. **Rate Limiting**: Some SMTP providers have rate limits

## Database Issues

### Check Queue Tables
```sql
-- Check jobs table
SELECT * FROM jobs ORDER BY created_at DESC LIMIT 10;

-- Check failed_jobs table
SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 10;

-- Check job_batches table
SELECT * FROM job_batches ORDER BY created_at DESC LIMIT 10;
```

### Clean Up Old Jobs
```bash
# Clear old completed jobs
php artisan queue:prune-batches --hours=24

# Clear old failed jobs
php artisan queue:prune-failed --hours=168  # 7 days
```

## Performance Optimization

### Queue Worker Settings
```bash
# Optimize for your server
php artisan queue:work \
  --sleep=3 \
  --tries=3 \
  --max-time=3600 \
  --memory=512 \
  --timeout=60
```

### Multiple Workers
```bash
# Run multiple workers for better performance
for i in {1..4}; do
  nohup php artisan queue:work --sleep=3 --tries=3 > /dev/null 2>&1 &
done
```

## Emergency Procedures

### If Queue Completely Stops
1. **Check system resources**:
   ```bash
   df -h  # Check disk space
   free -m  # Check memory
   top  # Check CPU usage
   ```

2. **Restart everything**:
   ```bash
   ./queue-monitor.sh restart
   ```

3. **Clear problematic jobs**:
   ```bash
   ./queue-monitor.sh clear
   ```

### If Emails Stop Working
1. **Check SMTP configuration**:
   ```bash
   php artisan config:show mail
   ```

2. **Test email manually**:
   ```bash
   php artisan tinker
   >>> Mail::raw('Test', function($msg) { $msg->to('your@email.com')->subject('Test'); });
   ```

3. **Check logs for errors**:
   ```bash
   tail -f storage/logs/laravel-$(date +%Y-%m-%d).log | grep -i "mail\|smtp\|phpmailer"
   ```

## Contact & Support

If you continue to have issues:
1. Check the logs: `tail -f storage/logs/laravel-$(date +%Y-%m-%d).log`
2. Run diagnostics: `./queue-monitor.sh status`
3. Check system resources: `htop` or `top`
4. Verify database connectivity: `php artisan tinker` then `DB::connection()->getPdo()`
