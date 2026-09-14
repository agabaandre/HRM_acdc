#!/bin/bash

# Nuclear Queue Cleanup Script
# This script completely cleans the queue and removes problematic jobs
# Run this on your production server: bash nuclear_queue_cleanup.sh

echo "=== Nuclear Queue Cleanup ==="

# Get current directory
CURRENT_DIR=$(pwd)
PHP_PATH=$(which php)

echo "Current Directory: $CURRENT_DIR"
echo "PHP Path: $PHP_PATH"

# Stop queue worker first
echo "Stopping queue worker..."
sudo systemctl stop laravel-queue-apm.service

# Check current queue status
echo "Checking current queue status..."
$PHP_PATH artisan queue:monitor default

# Clear all jobs from the queue
echo "Clearing all jobs from queue..."
$PHP_PATH artisan queue:clear

# Clear failed jobs
echo "Clearing failed jobs..."
$PHP_PATH artisan queue:flush

# Clear the jobs table directly (nuclear option)
echo "Clearing jobs table directly..."
$PHP_PATH artisan tinker --execute="DB::table('jobs')->truncate();"

# Clear failed_jobs table
echo "Clearing failed_jobs table..."
$PHP_PATH artisan tinker --execute="DB::table('failed_jobs')->truncate();"

# Restart queue worker
echo "Restarting queue worker..."
sudo systemctl start laravel-queue-apm.service

# Wait for restart
sleep 5

# Check queue status after cleanup
echo "Checking queue status after cleanup..."
$PHP_PATH artisan queue:monitor default

# Check if queue worker is running
echo "Checking queue worker status..."
sudo systemctl status laravel-queue-apm.service --no-pager

# Test queue with a simple job
echo "Testing queue with a simple job..."
$PHP_PATH artisan tinker --execute="
use App\Jobs\SendMatrixNotificationJob;
use App\Models\Matrix;
use App\Models\Staff;

\$matrix = Matrix::first();
\$staff = Staff::where('active', 1)->first();

if (\$matrix && \$staff) {
    dispatch(new SendMatrixNotificationJob(\$matrix, \$staff, 'test', 'Queue cleanup test'));
    echo 'Test job dispatched successfully';
} else {
    echo 'No matrix or staff found for testing';
}
"

# Check queue status after test
echo "Checking queue status after test..."
$PHP_PATH artisan queue:monitor default

echo ""
echo "=== Nuclear Cleanup Complete ==="
echo "All jobs have been completely cleared."
echo "Queue worker has been restarted."
echo "Test job has been dispatched to verify functionality."
echo ""
echo "Monitor the queue with: sudo journalctl -u laravel-queue-apm.service -f"
