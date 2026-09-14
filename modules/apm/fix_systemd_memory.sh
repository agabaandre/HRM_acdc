#!/bin/bash

# Fix Systemd Memory Limit Issue
# Run this on your production server: bash fix_systemd_memory.sh

echo "=== Fixing Systemd Memory Limit Issue ==="

# Get current directory
CURRENT_DIR=$(pwd)
PHP_PATH=$(which php)

echo "Current Directory: $CURRENT_DIR"
echo "PHP Path: $PHP_PATH"

# Stop the service
echo "Stopping service..."
sudo systemctl stop laravel-queue-apm.service

# Create corrected service file with MemoryMax instead of MemoryLimit
echo "Creating corrected service file..."
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

# Resource limits (using MemoryMax instead of MemoryLimit)
LimitNOFILE=65536
MemoryMax=512M

[Install]
WantedBy=multi-user.target
EOF

# Reload systemd
echo "Reloading systemd..."
sudo systemctl daemon-reload

# Start the service
echo "Starting service..."
sudo systemctl start laravel-queue-apm.service

# Check status
echo "Checking service status..."
sudo systemctl status laravel-queue-apm.service

echo ""
echo "=== Fix Complete ==="
echo "Service should now be running without memory limit errors."
echo "To monitor logs: sudo journalctl -u laravel-queue-apm.service -f"
