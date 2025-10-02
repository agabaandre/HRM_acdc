#!/bin/bash

# Quick Queue Worker Fix Script
# Run this on your production server: bash quick_queue_fix.sh

echo "=== Quick Queue Worker Fix ==="

# Get current directory
CURRENT_DIR=$(pwd)
PHP_PATH=$(which php)

echo "Current Directory: $CURRENT_DIR"
echo "PHP Path: $PHP_PATH"

# Stop the failing service
echo "Stopping failing service..."
sudo systemctl stop laravel-queue-apm.service

# Disable the service temporarily
echo "Disabling service..."
sudo systemctl disable laravel-queue-apm.service

# Create a new service file
echo "Creating new service file..."
sudo tee /etc/systemd/system/laravel-queue-apm.service > /dev/null <<EOF
[Unit]
Description=Laravel Queue Worker for ACDC APM
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=$CURRENT_DIR
ExecStart=$PHP_PATH artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal
SyslogIdentifier=laravel-queue-apm

# Environment variables
Environment=APP_ENV=production
Environment=APP_DEBUG=false

# Resource limits
LimitNOFILE=65536
MemoryLimit=512M

[Install]
WantedBy=multi-user.target
EOF

# Reload systemd
echo "Reloading systemd..."
sudo systemctl daemon-reload

# Enable the service
echo "Enabling service..."
sudo systemctl enable laravel-queue-apm.service

# Start the service
echo "Starting service..."
sudo systemctl start laravel-queue-apm.service

# Check status
echo "Checking service status..."
sudo systemctl status laravel-queue-apm.service

echo ""
echo "=== Fix Complete ==="
echo "Service should now be running properly."
echo "To monitor logs: sudo journalctl -u laravel-queue-apm.service -f"
echo "To check status: sudo systemctl status laravel-queue-apm.service"
