#!/bin/bash

# Fix Systemd Queue Issues Script
# Run this on your production server: bash fix_systemd_queue_issues.sh

echo "=== Fixing Systemd Queue Issues ==="

SERVICE_NAME="laravel-queue-apm.service"
CURRENT_DIR=$(pwd)
PHP_PATH=$(which php)

echo "Current Directory: $CURRENT_DIR"
echo "PHP Path: $PHP_PATH"

# Step 1: Stop the service
echo "Step 1: Stopping service..."
sudo systemctl stop $SERVICE_NAME

# Step 2: Check if service is enabled
echo "Step 2: Checking if service is enabled..."
if ! systemctl is-enabled $SERVICE_NAME >/dev/null 2>&1; then
    echo "  Enabling service..."
    sudo systemctl enable $SERVICE_NAME
else
    echo "  Service is already enabled"
fi

# Step 3: Check service file
echo "Step 3: Checking service file..."
SERVICE_FILE="/etc/systemd/system/$SERVICE_NAME"

if [ -f "$SERVICE_FILE" ]; then
    echo "  Service file exists: $SERVICE_FILE"
    
    # Check if working directory is correct
    if ! grep -q "WorkingDirectory=$CURRENT_DIR" "$SERVICE_FILE"; then
        echo "  ⚠️  Working directory mismatch in service file"
        echo "  Current: $CURRENT_DIR"
        echo "  Service file:"
        grep "WorkingDirectory=" "$SERVICE_FILE" || echo "    Not found"
    else
        echo "  ✅ Working directory is correct"
    fi
    
    # Check if PHP path is correct
    if ! grep -q "ExecStart=$PHP_PATH" "$SERVICE_FILE"; then
        echo "  ⚠️  PHP path mismatch in service file"
        echo "  Current: $PHP_PATH"
        echo "  Service file:"
        grep "ExecStart=" "$SERVICE_FILE" || echo "    Not found"
    else
        echo "  ✅ PHP path is correct"
    fi
else
    echo "  ❌ Service file not found: $SERVICE_FILE"
    echo "  Please create the service file first"
    exit 1
fi

# Step 4: Reload systemd
echo "Step 4: Reloading systemd..."
sudo systemctl daemon-reload

# Step 5: Start the service
echo "Step 5: Starting service..."
sudo systemctl start $SERVICE_NAME

# Step 6: Wait a moment
echo "Step 6: Waiting for service to start..."
sleep 5

# Step 7: Check service status
echo "Step 7: Checking service status..."
sudo systemctl status $SERVICE_NAME --no-pager

# Step 8: Check if queue worker is running
echo "Step 8: Checking queue worker processes..."
QUEUE_PROCESSES=$(ps aux | grep "queue:work" | grep -v grep | wc -l)

if [ "$QUEUE_PROCESSES" -gt 0 ]; then
    echo "  ✅ Queue worker processes found: $QUEUE_PROCESSES"
    ps aux | grep "queue:work" | grep -v grep
else
    echo "  ❌ No queue worker processes found"
    echo "  Checking service logs..."
    sudo journalctl -u $SERVICE_NAME --no-pager -n 10
fi

# Step 9: Test job processing
echo "Step 9: Testing job processing..."
JOBS_COUNT=$(php artisan tinker --execute="echo DB::table('jobs')->count();" 2>/dev/null | tail -1)

if [ -n "$JOBS_COUNT" ] && [ "$JOBS_COUNT" -gt 0 ]; then
    echo "  Jobs in queue: $JOBS_COUNT"
    
    # Try to process one job
    echo "  Attempting to process one job..."
    php artisan queue:work --once --timeout=30 >/dev/null 2>&1
    
    # Check if job count decreased
    NEW_JOBS_COUNT=$(php artisan tinker --execute="echo DB::table('jobs')->count();" 2>/dev/null | tail -1)
    
    if [ -n "$NEW_JOBS_COUNT" ] && [ "$NEW_JOBS_COUNT" -lt "$JOBS_COUNT" ]; then
        echo "  ✅ Job processing works (jobs: $JOBS_COUNT -> $NEW_JOBS_COUNT)"
    else
        echo "  ❌ Job processing may not be working"
    fi
else
    echo "  No jobs in queue to test"
fi

echo ""
echo "=== Fix Complete ==="

# Final status check
echo "Final Status:"
echo "  Service status: $(systemctl is-active $SERVICE_NAME)"
echo "  Queue processes: $(ps aux | grep 'queue:work' | grep -v grep | wc -l)"
echo "  Jobs in queue: $(php artisan tinker --execute="echo DB::table('jobs')->count();" 2>/dev/null | tail -1)"

echo ""
echo "If issues persist, check logs:"
echo "  sudo journalctl -u $SERVICE_NAME -f"
