# Africa CDC APM - Jobs and Commands Documentation

This document provides a comprehensive overview of all jobs, commands, and scheduled tasks in the Africa CDC Approvals Management System.

## 📋 Table of Contents

1. [Background Jobs](#background-jobs)
2. [Console Commands](#console-commands)
3. [Scheduled Tasks](#scheduled-tasks)
4. [Job Management Script](#job-management-script)
5. [System Health Monitoring](#system-health-monitoring)
6. [Troubleshooting](#troubleshooting)

---

## 🔄 Background Jobs

### Core Notification Jobs

#### 1. `SendDailyPendingApprovalsNotificationJob`
- **Purpose**: Sends daily pending approvals notifications to all approvers
- **Queue**: `default`
- **Tries**: 3
- **Timeout**: 300 seconds (5 minutes)
- **Schedule**: Daily at 9:00 AM, 4:00 PM, and 1:07 AM (GMT+3)
- **Dependencies**: `NotificationService`, `PendingApprovalsService`

#### 2. `SendNotificationEmailJob`
- **Purpose**: Sends individual email notifications using Exchange service
- **Queue**: `notifications`
- **Tries**: 3
- **Timeout**: 120 seconds
- **Dependencies**: Exchange OAuth service, email templates

#### 3. `SendMatrixNotificationJob`
- **Purpose**: Sends matrix approval notifications
- **Queue**: `default`
- **Tries**: 3
- **Timeout**: 60 seconds
- **Dependencies**: Exchange OAuth service, matrix templates

### Document Management Jobs

#### 4. `AssignDocumentNumberJob`
- **Purpose**: Assigns document numbers to new records
- **Queue**: `default`
- **Tries**: 3
- **Timeout**: 60 seconds
- **Dependencies**: Document numbering system

#### 5. `ResetDocumentCountersJob`
- **Purpose**: Resets document counters for specific periods
- **Queue**: `default`
- **Tries**: 3
- **Timeout**: 60 seconds

### Maintenance Jobs

#### 6. `ArchiveOldApprovalTrailsJob`
- **Purpose**: Archives old approval trails to maintain database performance
- **Queue**: `default`
- **Tries**: 3
- **Timeout**: 300 seconds

---

## 🖥️ Console Commands

### Job Management Commands

#### `jobs:test-daily-notifications`
- **Purpose**: Test daily pending approvals notifications without sending emails
- **Usage**: `php artisan jobs:test-daily-notifications`
- **Output**: Shows which approvers would receive notifications and how many pending items they have

#### `jobs:dispatch-daily-notifications`
- **Purpose**: Manually dispatch daily pending approvals notification job
- **Usage**: `php artisan jobs:dispatch-daily-notifications`
- **Output**: Dispatches job to queue for processing

#### `jobs:process-queue`
- **Purpose**: Process one job from the queue
- **Usage**: `php artisan jobs:process-queue`
- **Output**: Processes and displays job execution details

#### `jobs:monitor-queue`
- **Purpose**: Monitor and process queue jobs
- **Usage**: `php artisan jobs:monitor-queue`
- **Output**: Shows queue status and processes jobs if available

### Notification Commands

#### `notifications:daily-pending-approvals`
- **Purpose**: Send daily pending approvals notifications to all approvers
- **Usage**: `php artisan notifications:daily-pending-approvals [--test] [--force]`
- **Options**:
  - `--test`: Run in test mode (dry run)
  - `--force`: Force run even if not scheduled time
- **Schedule**: Daily at 9:00 AM, 4:00 PM, and 1:07 AM (GMT+3)

#### `notifications:send-test {staff_id}`
- **Purpose**: Send test pending approvals notification to specific staff member
- **Usage**: `php artisan notifications:send-test 558`
- **Output**: Sends personalized notification to specified staff member

#### `notifications:test-email`
- **Purpose**: Test email notification system
- **Usage**: `php artisan notifications:test-email`
- **Output**: Tests email configuration and delivery

#### `notifications:test-all`
- **Purpose**: Test all notification systems
- **Usage**: `php artisan notifications:test-all`
- **Output**: Comprehensive notification system test

### Document Management Commands

#### `assign:document-numbers`
- **Purpose**: Assign document numbers to existing records that don't have them
- **Usage**: `php artisan assign:document-numbers`
- **Output**: Processes records and assigns missing document numbers

#### `assign:missing-document-numbers`
- **Purpose**: Assign document numbers to records that are missing them
- **Usage**: `php artisan assign:missing-document-numbers`
- **Output**: Identifies and assigns missing document numbers

#### `document:reset-counters`
- **Purpose**: Reset document counters for a specific year, division, or document type
- **Usage**: `php artisan document:reset-counters`
- **Output**: Resets document numbering counters

#### `fix:document-conflicts`
- **Purpose**: Fix document number conflicts and reset counters after deletions
- **Usage**: `php artisan fix:document-conflicts`
- **Output**: Resolves document number conflicts

#### `monitor:document-jobs`
- **Purpose**: Monitor document number assignment jobs
- **Usage**: `php artisan monitor:document-jobs`
- **Output**: Shows status of document assignment jobs

#### `monitor:document-numbers`
- **Purpose**: Monitor and automatically assign document numbers to records that need them
- **Usage**: `php artisan monitor:document-numbers`
- **Output**: Monitors and assigns document numbers automatically

### Data Synchronization Commands

#### `directorates:sync`
- **Purpose**: Synchronize directorates data
- **Usage**: `php artisan directorates:sync`
- **Schedule**: Daily at 6:00 AM and 11:00 PM (GMT+3)

#### `divisions:sync`
- **Purpose**: Synchronize divisions data
- **Usage**: `php artisan divisions:sync`
- **Schedule**: Daily at 6:05 AM and 11:05 PM (GMT+3)

#### `staff:sync`
- **Purpose**: Synchronize staff data
- **Usage**: `php artisan staff:sync`
- **Schedule**: Daily at 6:10 AM and 11:10 PM (GMT+3)

### Approval Management Commands

#### `approval:archive-trails`
- **Purpose**: Archive old approval trails for matrices and activities
- **Usage**: `php artisan approval:archive-trails`
- **Output**: Archives old approval trails to maintain performance

#### `approval:manage-trails`
- **Purpose**: Manage approval trails - show statistics, archive old trails, or cleanup
- **Usage**: `php artisan approval:manage-trails`
- **Output**: Provides approval trail management options

### System Health Commands

#### `system:health-check`
- **Purpose**: Check system health and configuration
- **Usage**: `php artisan system:health-check`
- **Output**: Comprehensive system health report including:
  - Database connectivity
  - Queue tables status
  - Storage permissions
  - Log file availability

### Queue Management Commands

#### `queue:clear-failed`
- **Purpose**: Clear all failed jobs from the queue
- **Usage**: `php artisan queue:clear-failed`
- **Output**: Removes all failed jobs from the queue

#### `queue:retry-failed`
- **Purpose**: Retry all failed jobs
- **Usage**: `php artisan queue:retry-failed`
- **Output**: Retries all failed jobs in the queue

---

## ⏰ Scheduled Tasks

### Daily Data Synchronization
- **6:00 AM GMT+3**: `directorates:sync`
- **6:05 AM GMT+3**: `divisions:sync`
- **6:10 AM GMT+3**: `staff:sync`

### Evening Data Synchronization
- **11:00 PM GMT+3**: `directorates:sync`
- **11:05 PM GMT+3**: `divisions:sync`
- **11:10 PM GMT+3**: `staff:sync`

### Daily Notifications
- **9:00 AM GMT+3**: `notifications:daily-pending-approvals` (Morning reminders)
- **4:00 PM GMT+3**: `notifications:daily-pending-approvals` (Evening reminders)
- **1:07 AM GMT+3**: `notifications:daily-pending-approvals` (Test notifications)

---

## 🛠️ Job Management Script

The `manage-jobs.sh` script provides easy management of all jobs and services:

### Available Commands

```bash
# System Status
./manage-jobs.sh status              # Show system and queue status
./manage-jobs.sh health              # Run comprehensive health check

# Notification Management
./manage-jobs.sh test-notifications  # Test notification system (dry run)
./manage-jobs.sh send-test <staff_id> # Send test notification to specific staff
./manage-jobs.sh dispatch-daily      # Dispatch daily pending approvals job

# Queue Management
./manage-jobs.sh process-queue       # Process one job from the queue
./manage-jobs.sh monitor-queue       # Monitor and process queue jobs
./manage-jobs.sh clear-failed        # Clear all failed jobs
./manage-jobs.sh retry-failed        # Retry all failed jobs

# Service Management
./manage-jobs.sh start-worker        # Start queue worker (background)
./manage-jobs.sh stop-worker         # Stop queue worker
./manage-jobs.sh restart-worker      # Restart queue worker
./manage-jobs.sh start-scheduler     # Start scheduler (background)
./manage-jobs.sh stop-scheduler      # Stop scheduler
./manage-jobs.sh restart-scheduler   # Restart scheduler

# Logs and Monitoring
./manage-jobs.sh logs                # Show recent job logs
```

---

## 📊 System Health Monitoring

### Health Check Components

The `system:health-check` command verifies:

1. **Database Connectivity**
   - Connection to MySQL database
   - Query execution capability

2. **Queue System**
   - `jobs` table existence and accessibility
   - `failed_jobs` table existence and accessibility
   - Current queue status (pending/failed jobs count)

3. **Storage System**
   - Storage directory writability
   - Log file accessibility

4. **Logging System**
   - Laravel log file existence
   - Log file readability

### Monitoring Commands

```bash
# Quick status check
./manage-jobs.sh status

# Comprehensive health check
./manage-jobs.sh health

# Queue monitoring
./manage-jobs.sh monitor-queue

# View logs
./manage-jobs.sh logs
```

---

## 🔧 Troubleshooting

### Common Issues

#### 1. Jobs Not Processing
```bash
# Check queue status
./manage-jobs.sh monitor-queue

# Restart queue worker
./manage-jobs.sh restart-worker

# Check failed jobs
php artisan queue:failed
```

#### 2. Notifications Not Sending
```bash
# Test notification system
./manage-jobs.sh test-notifications

# Send test to specific user
./manage-jobs.sh send-test 558

# Check Exchange configuration
php artisan system:health-check
```

#### 3. Document Numbers Not Assigning
```bash
# Monitor document jobs
php artisan monitor:document-jobs

# Assign missing document numbers
php artisan assign:missing-document-numbers

# Fix document conflicts
php artisan fix:document-conflicts
```

#### 4. Scheduled Tasks Not Running
```bash
# Check scheduler status
./manage-jobs.sh status

# Restart scheduler
./manage-jobs.sh restart-scheduler

# Test scheduled commands manually
php artisan notifications:daily-pending-approvals --test
```

### Log Files

- **Main Log**: `storage/logs/laravel.log`
- **Job Management Log**: `storage/logs/job-management.log`
- **Daily Logs**: `storage/logs/laravel-YYYY-MM-DD.log`

### Service Management

#### Queue Worker Service
- **Service File**: `laravel-queue-apm.service`
- **Status**: `systemctl status laravel-queue-apm`
- **Start**: `systemctl start laravel-queue-apm`
- **Stop**: `systemctl stop laravel-queue-apm`

#### Scheduler Service
- **Service File**: `laravel-scheduler.service`
- **Status**: `systemctl status laravel-scheduler`
- **Start**: `systemctl start laravel-scheduler`
- **Stop**: `systemctl stop laravel-scheduler`

---

## 📈 Performance Monitoring

### Queue Metrics
- **Pending Jobs**: `php artisan queue:monitor`
- **Failed Jobs**: `php artisan queue:failed`
- **Job Processing Rate**: Monitor logs for processing times

### Notification Metrics
- **Daily Notifications Sent**: Check logs for notification counts
- **Email Delivery Success Rate**: Monitor Exchange service logs
- **Approver Coverage**: Test command shows which approvers have pending items

### System Resources
- **Memory Usage**: Monitor queue worker memory consumption
- **Database Performance**: Check query execution times
- **Storage Usage**: Monitor log file sizes and cleanup needs

---

## 🚀 Quick Start Guide

### 1. Start All Services
```bash
./manage-jobs.sh start-scheduler
./manage-jobs.sh start-worker
```

### 2. Verify System Health
```bash
./manage-jobs.sh health
```

### 3. Test Notifications
```bash
./manage-jobs.sh test-notifications
./manage-jobs.sh send-test 558
```

### 4. Monitor System
```bash
./manage-jobs.sh status
./manage-jobs.sh logs
```

---

## 📞 Support

For issues or questions regarding jobs and commands:

1. Check system health: `./manage-jobs.sh health`
2. Review logs: `./manage-jobs.sh logs`
3. Test specific functionality using the test commands
4. Check service status using systemctl commands

---

*Last Updated: October 10, 2025*
*Version: 1.0*
