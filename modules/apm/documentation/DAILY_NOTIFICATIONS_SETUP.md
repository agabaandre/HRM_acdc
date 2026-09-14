# Daily Pending Approvals Notification System

## Overview
This system automatically sends email notifications to all approvers twice daily at 9:00 AM and 4:00 PM (Africa/Addis_Ababa timezone) with a summary of their pending approvals.

## System Components

### 1. Job Class
- **File**: `app/Jobs/SendDailyPendingApprovalsNotificationJob.php`
- **Purpose**: Processes all approvers and sends notifications
- **Features**:
  - Handles both division-specific and regular approvers
  - Only sends emails to approvers with pending items
  - Creates notification records in database
  - Uses PHPMailer for email delivery
  - Includes retry mechanism (3 attempts)
  - 5-minute timeout for bulk operations

### 2. Console Command
- **File**: `app/Console/Commands/SendDailyPendingApprovalsCommand.php`
- **Command**: `php artisan notifications:daily-pending-approvals`
- **Options**:
  - `--test`: Run in test mode (dry run, no emails sent)
  - `--force`: Force run even if not scheduled time

### 3. Email Template
- **File**: `resources/views/emails/daily-pending-approvals-notification.blade.php`
- **Features**:
  - Responsive HTML design
  - Summary statistics
  - Categorized pending items
  - Direct links to view items
  - Professional styling

### 4. Scheduling
- **File**: `routes/console.php`
- **Schedule**: Twice daily at 9:00 AM and 4:00 PM (Africa/Addis_Ababa timezone)
- **Features**:
  - Without overlapping protection
  - Background execution
  - Automatic timezone handling
  - Dynamic subject lines (Morning/Evening)
  - Dynamic greetings in email content

## Performance Metrics

### Benchmark Results
- **Total Approvers**: 75
- **Approvers with Pending Items**: 57 (76%)
- **Total Pending Items**: 281
- **Processing Time**: ~1.6 seconds
- **Memory Usage**: ~360MB (acceptable)
- **Email Delivery**: 57 emails per day

### System Requirements
- **PHP**: 8.2+
- **Laravel**: 12.x
- **Memory**: 512MB+ recommended
- **Queue Workers**: Required for production

## Setup Instructions

### 1. Queue Configuration
Ensure your queue is properly configured in `.env`:
```env
QUEUE_CONNECTION=database
# or
QUEUE_CONNECTION=redis
```

### 2. Email Configuration
Verify email settings in `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@africacdc.org
MAIL_FROM_NAME="Africa CDC APM"
MAIL_SUBJECT_PREFIX="Approval Management System"
```

### 3. Database Migration
Run the queue table migration if using database queue:
```bash
php artisan queue:table
php artisan migrate
```

### 4. Schedule Setup
The schedule is already configured. To verify:
```bash
php artisan schedule:list
```

### 5. Queue Worker Setup
For production, set up queue workers:
```bash
# Start queue worker
php artisan queue:work --daemon

# Or use supervisor for production
```

## Testing Commands

### Test Mode (Dry Run)
```bash
php artisan notifications:daily-pending-approvals --test
```
- Shows what would be sent without sending emails
- Displays summary statistics
- Lists all approvers and their pending items

### Force Run
```bash
php artisan notifications:daily-pending-approvals --force
```
- Runs immediately regardless of time
- Sends actual emails
- Useful for testing

### Manual Schedule Run
```bash
php artisan schedule:run
```
- Runs all scheduled commands
- Useful for testing the scheduler

## Monitoring

### Logs
Monitor the application logs:
```bash
tail -f storage/logs/laravel.log
```

### Queue Status
Check queue status:
```bash
php artisan queue:work --once
```

### Email Delivery
- Check email delivery rates
- Monitor bounce rates
- Set up alerts for failures

## Troubleshooting

### Common Issues

1. **Permission Denied (Logs)**
   - Fix: `chmod -R 775 storage/logs`
   - Fix: `chown -R www-data:www-data storage/logs`

2. **Queue Not Processing**
   - Check: `php artisan queue:work`
   - Verify: Queue configuration in `.env`

3. **Emails Not Sending**
   - Check: SMTP configuration
   - Verify: Email credentials
   - Test: `php artisan tinker` and send test email

4. **Schedule Not Running**
   - Check: Cron job setup
   - Verify: `php artisan schedule:list`
   - Test: `php artisan schedule:run`

### Performance Issues

1. **High Memory Usage**
   - Consider: Processing in batches
   - Implement: Memory clearing between batches

2. **Slow Processing**
   - Consider: Using Redis queue
   - Implement: Multiple queue workers

3. **Email Rate Limits**
   - Implement: Rate limiting
   - Consider: Staggered sending

## Security Considerations

1. **Email Content**
   - No sensitive data in emails
   - Only summary information
   - Links require authentication

2. **Access Control**
   - Only approvers receive notifications
   - Based on actual approval levels
   - Respects division boundaries

3. **Data Privacy**
   - Minimal data in email content
   - Secure links to full details
   - No personal information exposure

## Maintenance

### Regular Tasks
1. Monitor queue workers
2. Check email delivery rates
3. Review notification logs
4. Update approver lists as needed

### Monthly Tasks
1. Review performance metrics
2. Check for failed jobs
3. Update email templates if needed
4. Verify schedule accuracy

## Support

For issues or questions:
1. Check application logs
2. Verify configuration
3. Test with `--test` flag
4. Contact system administrator

---

**Last Updated**: September 14, 2025
**Version**: 1.0
**Status**: Production Ready
