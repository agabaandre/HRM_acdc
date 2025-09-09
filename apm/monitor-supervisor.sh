#!/bin/bash

# Laravel Supervisor Monitoring Script
# Monitors both queue workers and scheduler

PROJECT_DIR="/var/www/html/staff/apm"

echo "=== Laravel Supervisor Status ==="
echo ""

# Check supervisor status
echo "📊 Supervisor Status:"
sudo supervisorctl status | grep -E "(laravel-worker|laravel-scheduler)"

echo ""
echo "📈 Queue Statistics:"
# Check queue size
QUEUE_SIZE=$(php $PROJECT_DIR/artisan queue:size 2>/dev/null || echo "N/A")
echo "Queue size: $QUEUE_SIZE jobs"

# Check failed jobs
FAILED_JOBS=$(php $PROJECT_DIR/artisan queue:failed 2>/dev/null | wc -l)
echo "Failed jobs: $FAILED_JOBS"

echo ""
echo "📅 Scheduled Commands:"
# Show scheduled commands
php $PROJECT_DIR/artisan schedule:list 2>/dev/null || echo "Could not retrieve schedule list"

echo ""
echo "📋 Recent Logs (last 10 lines):"
echo "--- Queue Worker Log ---"
tail -n 10 $PROJECT_DIR/storage/logs/worker.log 2>/dev/null || echo "No worker log found"

echo ""
echo "--- Scheduler Log ---"
tail -n 10 $PROJECT_DIR/storage/logs/scheduler.log 2>/dev/null || echo "No scheduler log found"

echo ""
echo "🔧 Management Commands:"
echo "Restart all: sudo supervisorctl restart all"
echo "Restart worker: sudo supervisorctl restart laravel-worker:*"
echo "Restart scheduler: sudo supervisorctl restart laravel-scheduler:*"
echo "View live logs: tail -f $PROJECT_DIR/storage/logs/worker.log"
echo "View scheduler logs: tail -f $PROJECT_DIR/storage/logs/scheduler.log"
