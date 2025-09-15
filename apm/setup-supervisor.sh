#!/bin/bash

# Laravel Supervisor Setup Script
# This script sets up both queue workers and scheduler through Supervisor

PROJECT_DIR="/opt/homebrew/var/www/staff/apm"
SUPERVISOR_DIR="/etc/supervisor/conf.d"

echo "Setting up Laravel Supervisor configuration..."

# Create logs directory if it doesn't exist
mkdir -p $PROJECT_DIR/storage/logs

# Copy supervisor configurations
echo "Copying supervisor configurations..."

# Copy queue worker config
sudo cp $PROJECT_DIR/supervisor-laravel-worker.conf $SUPERVISOR_DIR/laravel-worker.conf

# Copy scheduler config  
sudo cp $PROJECT_DIR/supervisor-laravel-scheduler.conf $SUPERVISOR_DIR/laravel-scheduler.conf

# Set proper permissions
sudo chown www-data:www-data $PROJECT_DIR/storage/logs
sudo chmod 755 $PROJECT_DIR/storage/logs

# Update supervisor configuration
echo "Updating supervisor configuration..."
sudo supervisorctl reread
sudo supervisorctl update

# Start the services
echo "Starting Laravel services..."
sudo supervisorctl start laravel-worker:*
sudo supervisorctl start laravel-scheduler:*

# Show status
echo "Checking service status..."
sudo supervisorctl status

echo ""
echo "✅ Setup complete!"
echo ""
echo "Services running:"
echo "- laravel-worker: Processes queued jobs (document numbering, etc.)"
echo "- laravel-scheduler: Runs scheduled commands (divisions sync, etc.)"
echo ""
echo "Logs location:"
echo "- Queue worker: $PROJECT_DIR/storage/logs/worker.log"
echo "- Scheduler: $PROJECT_DIR/storage/logs/scheduler.log"
echo ""
echo "Management commands:"
echo "- Check status: sudo supervisorctl status"
echo "- Restart all: sudo supervisorctl restart all"
echo "- Restart worker: sudo supervisorctl restart laravel-worker:*"
echo "- Restart scheduler: sudo supervisorctl restart laravel-scheduler:*"
echo "- View logs: tail -f $PROJECT_DIR/storage/logs/worker.log"
echo "- View scheduler logs: tail -f $PROJECT_DIR/storage/logs/scheduler.log"
