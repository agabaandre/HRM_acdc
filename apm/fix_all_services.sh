#!/bin/bash

# Fix All Laravel Services (Queue Worker + Scheduler)
# Run this on your production server: bash fix_all_services.sh

echo "=== Fixing All Laravel Services ==="

# Get current directory
CURRENT_DIR=$(pwd)
PHP_PATH=$(which php)

echo "Current Directory: $CURRENT_DIR"
echo "PHP Path: $PHP_PATH"

# Stop existing services
echo "Stopping existing services..."
sudo systemctl stop laravel-queue-apm.service 2>/dev/null || true
sudo systemctl stop laravel-scheduler.service 2>/dev/null || true

# Fix Queue Worker Service
echo "Creating corrected queue worker service..."
sudo tee /etc/systemd/system/laravel-queue-apm.service > /dev/null <<EOF
[Unit]
Description=Laravel Queue Worker for Africa CDC APM
After=network.target

[Service]
Type=simple
User=andrew
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

# Fix Scheduler Service
echo "Creating corrected scheduler service..."
sudo tee /etc/systemd/system/laravel-scheduler.service > /dev/null <<EOF
[Unit]
Description=Laravel Scheduler for Africa CDC APM
After=network.target

[Service]
Type=simple
User=andrew
Group=www-data
WorkingDirectory=$CURRENT_DIR
ExecStart=$PHP_PATH artisan schedule:work
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal
SyslogIdentifier=laravel-scheduler

# Environment variables
Environment=APP_ENV=production
Environment=APP_DEBUG=false

# Resource limits
LimitNOFILE=65536
MemoryMax=256M

[Install]
WantedBy=multi-user.target
EOF

# Reload systemd
echo "Reloading systemd..."
sudo systemctl daemon-reload

# Enable services
echo "Enabling services..."
sudo systemctl enable laravel-queue-apm.service
sudo systemctl enable laravel-scheduler.service

# Start services
echo "Starting services..."
sudo systemctl start laravel-queue-apm.service
sudo systemctl start laravel-scheduler.service

# Check status
echo "Checking service status..."
echo ""
echo "=== Queue Worker Status ==="
sudo systemctl status laravel-queue-apm.service --no-pager

echo ""
echo "=== Scheduler Status ==="
sudo systemctl status laravel-scheduler.service --no-pager

echo ""
echo "=== Service Management Commands ==="
echo "Check queue worker: sudo systemctl status laravel-queue-apm.service"
echo "Check scheduler: sudo systemctl status laravel-scheduler.service"
echo "Restart queue worker: sudo systemctl restart laravel-queue-apm.service"
echo "Restart scheduler: sudo systemctl restart laravel-scheduler.service"
echo "View queue logs: sudo journalctl -u laravel-queue-apm.service -f"
echo "View scheduler logs: sudo journalctl -u laravel-scheduler.service -f"

echo ""
echo "=== Fix Complete ==="
echo "Both services should now be running properly."
