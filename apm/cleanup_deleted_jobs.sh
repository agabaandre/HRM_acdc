#!/bin/bash

# Cleanup Deleted Jobs Script
# Run this on your production server: bash cleanup_deleted_jobs.sh

echo "=== Cleaning Up Deleted Jobs ==="

# Get current directory
CURRENT_DIR=$(pwd)
PHP_PATH=$(which php)

echo "Current Directory: $CURRENT_DIR"
echo "PHP Path: $PHP_PATH"

# Check current queue status
echo "Checking current queue status..."
$PHP_PATH artisan queue:monitor default

# Clear all jobs from the queue
echo "Clearing all jobs from queue..."
$PHP_PATH artisan queue:clear

# Clear failed jobs
echo "Clearing failed jobs..."
$PHP_PATH artisan queue:flush

# Restart queue worker to ensure clean state
echo "Restarting queue worker..."
sudo systemctl restart laravel-queue-apm.service

# Wait a moment for restart
sleep 3

# Check queue status after cleanup
echo "Checking queue status after cleanup..."
$PHP_PATH artisan queue:monitor default

# Check if queue worker is running
echo "Checking queue worker status..."
sudo systemctl status laravel-queue-apm.service --no-pager

echo ""
echo "=== Cleanup Complete ==="
echo "All jobs have been cleared and queue worker restarted."
echo "The queue should now be clean and ready for new jobs."
