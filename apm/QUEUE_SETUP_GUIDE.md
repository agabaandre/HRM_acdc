# Queue Setup Guide for Document Number Assignment

## Overview

The document numbering system uses Laravel queues to automatically assign document numbers when documents are created. This guide explains how to set up and maintain the queue system.

## How It Works

### 1. **Automatic Job Dispatch**
When a document is created (Matrix, Activity, NonTravelMemo, etc.), the `HasDocumentNumber` trait automatically dispatches an `AssignDocumentNumberJob`:

```php
// This happens automatically when creating any document
$matrix = Matrix::create([...]);
// Job is automatically dispatched to assign document number
```

### 2. **Job Processing**
The job runs in the background and:
- Generates a unique document number
- Updates the document with the number
- Handles race conditions and errors

## Queue Setup Options

### Option 1: Development (Manual)

For development and testing:

```bash
# Start queue worker (runs until stopped)
php artisan queue:work

# Process one job and stop
php artisan queue:work --once

# Process with specific options
php artisan queue:work --tries=3 --timeout=60 --memory=512
```

### Option 2: Production (Supervisor)

For production servers, use Supervisor to keep the queue worker running:

#### 1. Install Supervisor
```bash
# Ubuntu/Debian
sudo apt-get install supervisor

# CentOS/RHEL
sudo yum install supervisor
```

#### 2. Create Configuration
```bash
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

Add this configuration:
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

#### 3. Start Supervisor
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

### Option 3: Systemd Service

Create a systemd service for the queue worker:

#### 1. Create Service File
```bash
sudo nano /etc/systemd/system/laravel-worker.service
```

Add this content:
```ini
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /opt/homebrew/var/www/staff/apm/artisan queue:work --sleep=3 --tries=3 --max-time=3600
WorkingDirectory=/opt/homebrew/var/www/staff/apm

[Install]
WantedBy=multi-user.target
```

#### 2. Enable and Start
```bash
sudo systemctl enable laravel-worker
sudo systemctl start laravel-worker
sudo systemctl status laravel-worker
```

## Monitoring Commands

### Check Queue Status
```bash
# Monitor jobs in real-time
php artisan monitor:document-jobs --watch

# Check once
php artisan monitor:document-jobs
```

### Test Document Creation
```bash
# Create test documents
php artisan test:document-creation --count=5

# Check if jobs are being processed
php artisan queue:work --once --verbose
```

### Queue Management
```bash
# Check queue size
php artisan queue:size

# Clear all jobs
php artisan queue:clear

# Retry failed jobs
php artisan queue:retry all

# Check failed jobs
php artisan queue:failed
```

## Troubleshooting

### Jobs Not Processing

1. **Check if queue worker is running:**
```bash
ps aux | grep "queue:work"
```

2. **Check queue configuration:**
```bash
php artisan config:show queue
```

3. **Check database connection:**
```bash
php artisan tinker
>>> DB::connection()->getPdo();
```

### Failed Jobs

1. **Check failed jobs:**
```bash
php artisan queue:failed
```

2. **Retry specific job:**
```bash
php artisan queue:retry [job-id]
```

3. **Retry all failed jobs:**
```bash
php artisan queue:retry all
```

### Performance Issues

1. **Monitor memory usage:**
```bash
php artisan queue:work --memory=512
```

2. **Limit processing time:**
```bash
php artisan queue:work --max-time=3600
```

3. **Process specific queue:**
```bash
php artisan queue:work --queue=high,default
```

## Production Checklist

### ✅ **Queue Worker Running**
- Supervisor or systemd service configured
- Worker process running continuously
- Auto-restart on failure enabled

### ✅ **Monitoring Setup**
- Log files configured and rotating
- Queue monitoring in place
- Alert system for failures

### ✅ **Error Handling**
- Failed job retry logic
- Dead letter queue for permanent failures
- Error notifications configured

### ✅ **Performance Tuned**
- Appropriate number of workers
- Memory limits set
- Timeout values configured

## Example Production Setup

### 1. **Supervisor Configuration**
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /opt/homebrew/var/www/staff/apm/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --memory=512
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/opt/homebrew/var/www/staff/apm/storage/logs/worker.log
stopwaitsecs=3600
```

### 2. **Log Rotation**
```bash
# /etc/logrotate.d/laravel-worker
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

### 3. **Monitoring Script**
```bash
#!/bin/bash
# /opt/homebrew/var/www/staff/apm/monitor-queue.sh

QUEUE_SIZE=$(php /opt/homebrew/var/www/staff/apm/artisan queue:size)
FAILED_JOBS=$(php /opt/homebrew/var/www/staff/apm/artisan queue:failed | wc -l)

if [ $QUEUE_SIZE -gt 100 ]; then
    echo "ALERT: Queue size is $QUEUE_SIZE"
fi

if [ $FAILED_JOBS -gt 10 ]; then
    echo "ALERT: $FAILED_JOBS failed jobs"
fi
```

## Testing the System

### 1. **Create Test Documents**
```bash
php artisan test:document-creation --count=10
```

### 2. **Monitor Processing**
```bash
php artisan monitor:document-jobs --watch
```

### 3. **Verify Results**
```bash
php artisan tinker
>>> App\Models\Matrix::whereNotNull('document_number')->count()
```

## Best Practices

1. **Always run queue workers in production**
2. **Monitor queue health regularly**
3. **Set up alerts for failures**
4. **Use appropriate number of workers**
5. **Configure log rotation**
6. **Test after deployments**
7. **Have a fallback for critical documents**

## Support

If you encounter issues:
1. Check the logs: `tail -f storage/logs/worker.log`
2. Monitor the queue: `php artisan monitor:document-jobs`
3. Check failed jobs: `php artisan queue:failed`
4. Restart workers: `sudo supervisorctl restart laravel-worker:*`
