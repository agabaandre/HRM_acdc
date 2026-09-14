#!/bin/bash

# Laravel Supervisor + Cron Setup Script
# This script sets up queue workers through Supervisor and scheduler through cron

PROJECT_DIR="/var/www/html/staff/apm"
SUPERVISOR_DIR="/etc/supervisor/conf.d"

echo "Setting up Laravel with Supervisor (queue) + Cron (scheduler)..."

# Create logs directory if it doesn't exist
mkdir -p $PROJECT_DIR/storage/logs

# Copy queue worker config only
echo "Setting up queue worker with Supervisor..."
sudo cp $PROJECT_DIR/supervisor-laravel-worker.conf $SUPERVISOR_DIR/laravel-worker.conf

# Set proper permissions
sudo chown www-data:www-data $PROJECT_DIR/storage/logs
sudo chmod 755 $PROJECT_DIR/storage/logs

# Update supervisor configuration
echo "Updating supervisor configuration..."
sudo supervisorctl reread
sudo supervisorctl update

# Start the queue worker
echo "Starting queue worker..."
sudo supervisorctl start laravel-worker:*

# Setup cron job for scheduler
echo "Setting up cron job for scheduler..."
CRON_JOB="* * * * * cd $PROJECT_DIR && php artisan schedule:run >> /var/log/laravel-scheduler.log 2>&1"

# Add cron job if it doesn't exist
if ! crontab -l | grep -q "schedule:run"; then
    (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
    echo "✅ Cron job added successfully"
else
    echo "⚠️  Cron job already exists"
fi

# Show status
echo "Checking service status..."
sudo supervisorctl status

echo ""
echo "✅ Setup complete!"
echo ""
echo "Services running:"
echo "- laravel-worker (Supervisor): Processes queued jobs (document numbering, etc.)"
echo "- laravel-scheduler (Cron): Runs scheduled commands every minute (divisions sync, etc.)"
echo ""
echo "Logs location:"
echo "- Queue worker: $PROJECT_DIR/storage/logs/worker.log"
echo "- Scheduler: /var/log/laravel-scheduler.log"
echo ""
echo "Management commands:"
echo "- Check queue status: sudo supervisorctl status"
echo "- Restart queue worker: sudo supervisorctl restart laravel-worker:*"
echo "- Check cron jobs: crontab -l"
echo "- View queue logs: tail -f $PROJECT_DIR/storage/logs/worker.log"
echo "- View scheduler logs: tail -f /var/log/laravel-scheduler.log"
