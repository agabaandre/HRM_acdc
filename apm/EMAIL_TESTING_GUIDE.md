# Email System Testing Guide

This guide provides comprehensive testing tools for the Africa CDC APM email notification system.

## 🚀 Quick Start

### Option 1: Automated Test Script (Recommended)
```bash
# Run the complete test suite
./production-email-test.sh
```

### Option 2: Laravel Command
```bash
# Test all email components
php artisan test:email-system

# Test specific components
php artisan test:email-system --test-type=basic --email=your@email.com
php artisan test:email-system --test-type=matrix --email=your@email.com
php artisan test:email-system --test-type=daily
php artisan test:email-system --test-type=queue
```

## 📧 Test Types

### 1. Basic Email Test
Tests the fundamental email sending capability using Laravel's Mail facade.
```bash
php artisan test:email-system --test-type=basic --email=test@example.com
```

### 2. Matrix Notification Test
Tests the matrix notification job system.
```bash
php artisan test:email-system --test-type=matrix --email=test@example.com
```

### 3. Daily Notification Test
Tests the daily pending approvals notification system.
```bash
php artisan test:email-system --test-type=daily
```

### 4. Queue System Test
Tests the queue processing system.
```bash
php artisan test:email-system --test-type=queue
```

## 🔧 Manual Testing Commands

### Check Queue Status
```bash
# View failed jobs
php artisan queue:failed

# Check queue size
php artisan tinker --execute="echo 'Jobs in queue: ' . \Illuminate\Support\Facades\DB::table('jobs')->count();"

# Process one job
php artisan queue:work --once --verbose
```

### Send Manual Notifications
```bash
# Send daily notifications manually
php artisan tinker --execute="\App\Jobs\SendDailyPendingApprovalsNotificationJob::dispatch(); echo 'Daily notification dispatched';"

# Send matrix notification manually
php artisan tinker --execute="
\$matrix = \App\Models\Matrix::first();
\$staff = \App\Models\Staff::first();
if (\$matrix && \$staff) {
    \App\Jobs\SendMatrixNotificationJob::dispatch(\$matrix, \$staff, 'test', 'Manual test notification');
    echo 'Matrix notification dispatched';
} else {
    echo 'No test data available';
}
"
```

### Check System Status
```bash
# Check systemd services (if using systemd)
sudo systemctl status laravel-queue-worker
sudo systemctl status laravel-scheduler

# Check recent logs
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log | grep -i "mail\|email\|notification"
```

## 🐛 Troubleshooting

### Common Issues

1. **No emails being sent**
   - Check mail configuration in `.env`
   - Verify queue workers are running
   - Check logs for errors

2. **Jobs failing**
   - Check `php artisan queue:failed`
   - Review error logs
   - Verify database connectivity

3. **Template errors**
   - Clear view cache: `php artisan view:clear`
   - Check template syntax
   - Verify all required variables are passed

### Debug Commands
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Check configuration
php artisan config:show mail
php artisan config:show queue

# Test database connection
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connected successfully';"
```

## 📊 Monitoring

### Real-time Monitoring
```bash
# Monitor queue processing
php artisan queue:work --verbose

# Monitor logs
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log

# Check systemd services
sudo journalctl -u laravel-queue-worker -f
sudo journalctl -u laravel-scheduler -f
```

### Web Interface
Access the Systemd Monitor at: `Settings → Systemd Monitor`
- View service status
- Send manual notifications
- Monitor queue statistics
- Control services

## 🔒 Production Checklist

Before running in production:

- [ ] Update email addresses in test scripts
- [ ] Verify SMTP configuration
- [ ] Ensure queue workers are running
- [ ] Test with real data
- [ ] Monitor logs for errors
- [ ] Set up proper logging
- [ ] Configure backup procedures

## 📞 Support

If you encounter issues:

1. Check the logs first
2. Run the diagnostic commands
3. Verify system configuration
4. Check the troubleshooting section
5. Contact system administrator

## 📝 Log Files

Important log locations:
- `storage/logs/laravel-YYYY-MM-DD.log` - Application logs
- `storage/logs/worker.log` - Queue worker logs (if using systemd)
- `/var/log/syslog` - System logs (if using systemd)

## 🎯 Success Indicators

A successful email system test should show:
- ✅ Basic email sent successfully
- ✅ Matrix notification job dispatched
- ✅ Daily notification job dispatched
- ✅ Queue system processing jobs
- ✅ No failed jobs in the queue
- ✅ Emails received in inbox
