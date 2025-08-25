#!/bin/bash

# Setup Cron Job for Laravel Scheduler
# This script will add the necessary cron job to run Laravel's scheduler

echo "Setting up cron job for Laravel Scheduler..."
echo ""

# Get the current project directory
PROJECT_DIR=$(pwd)
echo "Project directory: $PROJECT_DIR"

# Check if cron is installed
if ! command -v crontab &> /dev/null; then
    echo "Error: crontab command not found. Please install cron first."
    exit 1
fi

# Create the cron job entry
CRON_JOB="* * * * * cd $PROJECT_DIR && php artisan schedule:run >> /dev/null 2>&1"

echo "Adding cron job: $CRON_JOB"
echo ""

# Add to crontab
(crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -

# Check if it was added successfully
if crontab -l | grep -q "schedule:run"; then
    echo "✅ Cron job added successfully!"
    echo ""
    echo "Current crontab entries:"
    crontab -l
    echo ""
    echo "The Laravel scheduler will now run every minute."
    echo "Your sync commands are scheduled for:"
    echo "  - Early morning: 6:00 AM - 6:10 AM (GMT+3)"
    echo "  - Late night: 11:00 PM - 11:10 PM (GMT+3)"
    echo ""
    echo "To monitor the scheduler:"
    echo "  php artisan schedule:list"
    echo "  php artisan schedule:run"
    echo ""
    echo "To remove the cron job later:"
    echo "  crontab -e"
    echo "  (then delete the line with 'schedule:run')"
else
    echo "❌ Failed to add cron job. Please check your permissions."
    exit 1
fi
