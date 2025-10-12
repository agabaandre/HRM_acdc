#!/bin/bash

# Africa CDC APM - Job Management Script
# This script provides easy management of Laravel jobs and queue workers

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
APM_DIR="/opt/homebrew/var/www/staff/apm"
LOG_FILE="$APM_DIR/storage/logs/job-management.log"

# Logging function
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
    echo -e "${BLUE}[$(date '+%H:%M:%S')]${NC} $1"
}

# Error logging function
log_error() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ERROR: $1" >> "$LOG_FILE"
    echo -e "${RED}[$(date '+%H:%M:%S')] ERROR:${NC} $1"
}

# Success logging function
log_success() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - SUCCESS: $1" >> "$LOG_FILE"
    echo -e "${GREEN}[$(date '+%H:%M:%S')] SUCCESS:${NC} $1"
}

# Warning logging function
log_warning() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - WARNING: $1" >> "$LOG_FILE"
    echo -e "${YELLOW}[$(date '+%H:%M:%S')] WARNING:${NC} $1"
}

# Change to APM directory
cd "$APM_DIR" || {
    log_error "Failed to change to APM directory: $APM_DIR"
    exit 1
}

# Function to show help
show_help() {
    echo -e "${BLUE}Africa CDC APM - Job Management Script${NC}"
    echo ""
    echo "Usage: $0 [COMMAND]"
    echo ""
    echo "Commands:"
    echo "  status          - Show system and queue status"
    echo "  health          - Run comprehensive health check"
    echo "  test-notifications - Test notification system (dry run)"
    echo "  send-test <staff_id> - Send test notification to specific staff member"
    echo "  send-reminder <staff_id> - Send instant reminder to specific staff member"
    echo "  send-reminder-email <email> - Send instant reminder to specific email"
    echo "  send-reminder-all - Send instant reminders to all approvers"
    echo "  dispatch-daily  - Dispatch daily pending approvals job"
    echo "  process-queue   - Process one job from the queue"
    echo "  monitor-queue   - Monitor and process queue jobs"
    echo "  clear-failed    - Clear all failed jobs"
    echo "  retry-failed    - Retry all failed jobs"
    echo "  start-worker    - Start queue worker (background)"
    echo "  stop-worker     - Stop queue worker"
    echo "  restart-worker  - Restart queue worker"
    echo "  start-scheduler - Start scheduler (background)"
    echo "  stop-scheduler  - Stop scheduler"
    echo "  restart-scheduler - Restart scheduler"
    echo "  logs            - Show recent job logs"
    echo "  help            - Show this help message"
    echo ""
}

# Function to show status
show_status() {
    log "Checking system status..."
    
    echo -e "${BLUE}=== System Status ===${NC}"
    
    # Check if we're in the right directory
    if [ ! -f "artisan" ]; then
        log_error "Not in Laravel project directory"
        return 1
    fi
    
    # Run health check
    php artisan system:health-check
    
    echo ""
    echo -e "${BLUE}=== Queue Status ===${NC}"
    php artisan jobs:monitor-queue
    
    echo ""
    echo -e "${BLUE}=== Recent Activity ===${NC}"
    if [ -f "$LOG_FILE" ]; then
        tail -10 "$LOG_FILE"
    else
        echo "No log file found"
    fi
}

# Function to run health check
run_health_check() {
    log "Running comprehensive health check..."
    php artisan system:health-check
    log_success "Health check completed"
}

# Function to test notifications
test_notifications() {
    log "Testing notification system (dry run)..."
    php artisan jobs:test-daily-notifications
    log_success "Notification test completed"
}

# Function to dispatch daily notifications
dispatch_daily() {
    log "Dispatching daily pending approvals job..."
    php artisan jobs:dispatch-daily-notifications
    log_success "Daily notifications job dispatched"
}

# Function to send instant reminder to specific staff
send_reminder() {
    if [ -z "$2" ]; then
        log_error "Staff ID required. Usage: $0 send-reminder <staff_id>"
        exit 1
    fi
    log "Sending instant reminder to staff ID: $2"
    php artisan reminders:send-instant --staff-id="$2"
    log_success "Instant reminder sent to staff ID: $2"
}

# Function to send instant reminder to specific email
send_reminder_email() {
    if [ -z "$2" ]; then
        log_error "Email required. Usage: $0 send-reminder-email <email>"
        exit 1
    fi
    log "Sending instant reminder to email: $2"
    php artisan reminders:send-instant --email="$2"
    log_success "Instant reminder sent to email: $2"
}

# Function to send instant reminders to all approvers
send_reminder_all() {
    log "Sending instant reminders to all approvers..."
    php artisan reminders:send-instant --all
    log_success "Instant reminders sent to all approvers"
}

# Function to process queue
process_queue() {
    log "Processing queue jobs..."
    php artisan jobs:process-queue
    log_success "Queue processing completed"
}

# Function to monitor queue
monitor_queue() {
    log "Monitoring queue..."
    php artisan jobs:monitor-queue
    log_success "Queue monitoring completed"
}

# Function to clear failed jobs
clear_failed() {
    log "Clearing failed jobs..."
    php artisan queue:clear-failed
    log_success "Failed jobs cleared"
}

# Function to retry failed jobs
retry_failed() {
    log "Retrying failed jobs..."
    php artisan queue:retry-failed
    log_success "Failed jobs retry initiated"
}

# Function to start queue worker
start_worker() {
    log "Starting queue worker..."
    if pgrep -f "artisan queue:work" > /dev/null; then
        log_warning "Queue worker is already running"
    else
        nohup php artisan queue:work --sleep=3 --tries=3 --max-time=3600 > /dev/null 2>&1 &
        log_success "Queue worker started (PID: $!)"
    fi
}

# Function to stop queue worker
stop_worker() {
    log "Stopping queue worker..."
    if pgrep -f "artisan queue:work" > /dev/null; then
        pkill -f "artisan queue:work"
        log_success "Queue worker stopped"
    else
        log_warning "Queue worker is not running"
    fi
}

# Function to restart queue worker
restart_worker() {
    log "Restarting queue worker..."
    stop_worker
    sleep 2
    start_worker
    log_success "Queue worker restarted"
}

# Function to start scheduler
start_scheduler() {
    log "Starting scheduler..."
    if pgrep -f "artisan schedule:work" > /dev/null; then
        log_warning "Scheduler is already running"
    else
        nohup php artisan schedule:work > /dev/null 2>&1 &
        log_success "Scheduler started (PID: $!)"
    fi
}

# Function to stop scheduler
stop_scheduler() {
    log "Stopping scheduler..."
    if pgrep -f "artisan schedule:work" > /dev/null; then
        pkill -f "artisan schedule:work"
        log_success "Scheduler stopped"
    else
        log_warning "Scheduler is not running"
    fi
}

# Function to restart scheduler
restart_scheduler() {
    log "Restarting scheduler..."
    stop_scheduler
    sleep 2
    start_scheduler
    log_success "Scheduler restarted"
}

# Function to show logs
show_logs() {
    log "Showing recent job logs..."
    if [ -f "$LOG_FILE" ]; then
        echo -e "${BLUE}=== Recent Job Management Logs ===${NC}"
        tail -20 "$LOG_FILE"
    else
        echo "No log file found at $LOG_FILE"
    fi
    
    echo ""
    echo -e "${BLUE}=== Recent Laravel Logs ===${NC}"
    if [ -f "storage/logs/laravel.log" ]; then
        tail -10 "storage/logs/laravel.log"
    else
        echo "No Laravel log file found"
    fi
}

# Main script logic
case "${1:-help}" in
    status)
        show_status
        ;;
    health)
        run_health_check
        ;;
    test-notifications)
        test_notifications
        ;;
    send-test)
        if [ -z "$2" ]; then
            log_error "Staff ID required. Usage: $0 send-test <staff_id>"
            exit 1
        fi
        log "Sending test notification to staff ID: $2"
        php artisan notifications:send-test "$2"
        log_success "Test notification sent to staff ID: $2"
        ;;
    send-reminder)
        send_reminder "$@"
        ;;
    send-reminder-email)
        send_reminder_email "$@"
        ;;
    send-reminder-all)
        send_reminder_all
        ;;
    dispatch-daily)
        dispatch_daily
        ;;
    process-queue)
        process_queue
        ;;
    monitor-queue)
        monitor_queue
        ;;
    clear-failed)
        clear_failed
        ;;
    retry-failed)
        retry_failed
        ;;
    start-worker)
        start_worker
        ;;
    stop-worker)
        stop_worker
        ;;
    restart-worker)
        restart_worker
        ;;
    start-scheduler)
        start_scheduler
        ;;
    stop-scheduler)
        stop_scheduler
        ;;
    restart-scheduler)
        restart_scheduler
        ;;
    logs)
        show_logs
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        log_error "Unknown command: $1"
        echo ""
        show_help
        exit 1
        ;;
esac

log_success "Command completed: $1"
