#!/bin/bash

# Process All Jobs Script
# This script will process all 393 jobs in your queue
# Run this on your production server: bash process_all_jobs.sh

echo "=== Processing All Jobs ==="

# Get current directory
CURRENT_DIR=$(pwd)
PHP_PATH=$(which php)

echo "Current Directory: $CURRENT_DIR"
echo "PHP Path: $PHP_PATH"

# Check initial status
echo "Checking initial queue status..."
$PHP_PATH artisan queue:monitor default

# Start queue worker in background
echo "Starting queue worker to process all jobs..."
nohup $PHP_PATH artisan queue:work --daemon --tries=3 --timeout=60 > /dev/null 2>&1 &
QUEUE_PID=$!

echo "Queue worker started with PID: $QUEUE_PID"

# Monitor progress
echo "Monitoring job processing..."
while true; do
    JOBS_COUNT=$($PHP_PATH artisan tinker --execute="echo DB::table('jobs')->count();" 2>/dev/null | tail -1)
    
    if [ -z "$JOBS_COUNT" ] || [ "$JOBS_COUNT" = "0" ]; then
        echo "All jobs processed!"
        break
    fi
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Jobs remaining: $JOBS_COUNT"
    sleep 10
done

# Stop the queue worker
echo "Stopping queue worker..."
kill $QUEUE_PID 2>/dev/null || true

# Check final status
echo "Checking final queue status..."
$PHP_PATH artisan queue:monitor default

echo ""
echo "=== Processing Complete ==="
echo "All jobs have been processed."
echo "Check the logs for any errors: sudo journalctl -u laravel-queue-apm.service -f"
