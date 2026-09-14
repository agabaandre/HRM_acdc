#!/bin/bash

# Queue Monitor Script for Africa CDC APM
# This script helps monitor and manage the queue system

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_DIR="/opt/homebrew/var/www/staff/apm"
LOG_FILE="$PROJECT_DIR/storage/logs/queue-monitor.log"

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Function to check if queue worker is running
check_queue_worker() {
    local worker_count=$(ps aux | grep "queue:work" | grep -v grep | wc -l)
    if [ $worker_count -gt 0 ]; then
        echo -e "${GREEN}✓${NC} Queue worker is running ($worker_count processes)"
        return 0
    else
        echo -e "${RED}✗${NC} No queue worker is running"
        return 1
    fi
}

# Function to check failed jobs
check_failed_jobs() {
    local failed_count=$(cd "$PROJECT_DIR" && php artisan queue:failed 2>/dev/null | grep -c "database@default" || echo "0")
    if [ $failed_count -gt 0 ]; then
        echo -e "${YELLOW}⚠${NC} $failed_count failed jobs found"
        return 1
    else
        echo -e "${GREEN}✓${NC} No failed jobs"
        return 0
    fi
}

# Function to check queue size
check_queue_size() {
    local queue_size=$(cd "$PROJECT_DIR" && php artisan tinker --execute="echo \App\Models\Job::count();" 2>/dev/null | tail -1 || echo "0")
    if [ "$queue_size" -gt 0 ]; then
        echo -e "${YELLOW}⚠${NC} $queue_size jobs in queue"
        return 1
    else
        echo -e "${GREEN}✓${NC} Queue is empty"
        return 0
    fi
}

# Function to start queue worker
start_queue_worker() {
    echo -e "${BLUE}Starting queue worker...${NC}"
    cd "$PROJECT_DIR"
    nohup php artisan queue:work --sleep=3 --tries=3 --max-time=3600 --memory=512 > /dev/null 2>&1 &
    sleep 2
    if check_queue_worker; then
        log_message "Queue worker started successfully"
        echo -e "${GREEN}Queue worker started successfully${NC}"
    else
        log_message "Failed to start queue worker"
        echo -e "${RED}Failed to start queue worker${NC}"
    fi
}

# Function to process failed jobs
retry_failed_jobs() {
    echo -e "${BLUE}Retrying failed jobs...${NC}"
    cd "$PROJECT_DIR"
    php artisan queue:retry all
    log_message "Retried all failed jobs"
    echo -e "${GREEN}Failed jobs retried${NC}"
}

# Function to clear failed jobs
clear_failed_jobs() {
    echo -e "${BLUE}Clearing failed jobs...${NC}"
    cd "$PROJECT_DIR"
    php artisan queue:flush
    log_message "Cleared all failed jobs"
    echo -e "${GREEN}Failed jobs cleared${NC}"
}

# Function to process one job manually
process_one_job() {
    echo -e "${BLUE}Processing one job...${NC}"
    cd "$PROJECT_DIR"
    php artisan queue:work --once --verbose
}

# Function to show queue status
show_status() {
    echo -e "${BLUE}=== Queue Status ===${NC}"
    check_queue_worker
    check_failed_jobs
    check_queue_size
    echo ""
}

# Function to show help
show_help() {
    echo "Queue Monitor for Africa CDC APM"
    echo ""
    echo "Usage: $0 [command]"
    echo ""
    echo "Commands:"
    echo "  status     - Show current queue status"
    echo "  start      - Start queue worker"
    echo "  stop       - Stop all queue workers"
    echo "  restart    - Restart queue workers"
    echo "  retry      - Retry all failed jobs"
    echo "  clear      - Clear all failed jobs"
    echo "  process    - Process one job manually"
    echo "  monitor    - Monitor queue in real-time"
    echo "  help       - Show this help message"
    echo ""
}

# Function to stop queue workers
stop_queue_workers() {
    echo -e "${BLUE}Stopping queue workers...${NC}"
    pkill -f "queue:work"
    sleep 2
    if ! check_queue_worker; then
        log_message "Queue workers stopped"
        echo -e "${GREEN}Queue workers stopped${NC}"
    else
        echo -e "${YELLOW}Some workers may still be running${NC}"
    fi
}

# Function to restart queue workers
restart_queue_workers() {
    echo -e "${BLUE}Restarting queue workers...${NC}"
    stop_queue_workers
    sleep 2
    start_queue_worker
}

# Function to monitor queue in real-time
monitor_queue() {
    echo -e "${BLUE}Monitoring queue (Press Ctrl+C to stop)...${NC}"
    while true; do
        clear
        echo -e "${BLUE}=== Queue Monitor - $(date) ===${NC}"
        show_status
        echo "Press Ctrl+C to stop monitoring..."
        sleep 5
    done
}

# Main script logic
case "${1:-status}" in
    "status")
        show_status
        ;;
    "start")
        start_queue_worker
        ;;
    "stop")
        stop_queue_workers
        ;;
    "restart")
        restart_queue_workers
        ;;
    "retry")
        retry_failed_jobs
        ;;
    "clear")
        clear_failed_jobs
        ;;
    "process")
        process_one_job
        ;;
    "monitor")
        monitor_queue
        ;;
    "help"|"-h"|"--help")
        show_help
        ;;
    *)
        echo -e "${RED}Unknown command: $1${NC}"
        show_help
        exit 1
        ;;
esac
