#!/bin/bash

echo "🔧 Fixing Laravel 12 Queue Worker Configuration..."

# Stop existing service
echo "Stopping existing queue service..."
sudo systemctl stop laravel-queue-apm.service 2>/dev/null || true

# Copy the corrected service file
echo "Installing corrected service file..."
sudo cp laravel12-queue-apm.service /etc/systemd/system/laravel-queue-apm.service

# Reload systemd
echo "Reloading systemd daemon..."
sudo systemctl daemon-reload

# Enable the service
echo "Enabling service..."
sudo systemctl enable laravel-queue-apm.service

# Start the service
echo "Starting queue worker..."
sudo systemctl start laravel-queue-apm.service

# Check status
echo "Checking service status..."
sudo systemctl status laravel-queue-apm.service --no-pager

echo ""
echo "✅ Laravel 12 Queue Worker Fixed!"
echo ""
echo "To monitor the queue:"
echo "  php artisan queue:monitor database:default"
echo ""
echo "To check service logs:"
echo "  sudo journalctl -u laravel-queue-apm.service -f"
echo ""
echo "To manually test the queue worker:"
echo "  php artisan queue:work database --sleep=3 --tries=3 --timeout=90 --verbose"
