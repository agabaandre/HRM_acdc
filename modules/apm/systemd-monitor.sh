#!/bin/bash

# Systemd Service Monitor for Africa CDC APM
# This script monitors the systemd services

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== Africa CDC APM Service Status ===${NC}"
echo ""

# Check queue worker service
echo -e "${BLUE}Queue Worker Service:${NC}"
if systemctl is-active --quiet laravel-queue-worker; then
    echo -e "${GREEN}✓${NC} laravel-queue-worker is running"
else
    echo -e "${RED}✗${NC} laravel-queue-worker is not running"
fi

# Check scheduler service
echo -e "${BLUE}Scheduler Service:${NC}"
if systemctl is-active --quiet laravel-scheduler; then
    echo -e "${GREEN}✓${NC} laravel-scheduler is running"
else
    echo -e "${RED}✗${NC} laravel-scheduler is not running"
fi

echo ""

# Check failed jobs
echo -e "${BLUE}Failed Jobs:${NC}"
cd /opt/homebrew/var/www/staff/apm
failed_count=$(php artisan queue:failed 2>/dev/null | grep -c "database@default" || echo "0")
if [ $failed_count -gt 0 ]; then
    echo -e "${YELLOW}⚠${NC} $failed_count failed jobs found"
else
    echo -e "${GREEN}✓${NC} No failed jobs"
fi

echo ""

# Check queue size
echo -e "${BLUE}Queue Size:${NC}"
queue_size=$(php artisan tinker --execute="echo \\Illuminate\\Support\\Facades\\DB::table('jobs')->count();" 2>/dev/null | tail -1 || echo "0")
if [ "$queue_size" -gt 0 ]; then
    echo -e "${YELLOW}⚠${NC} $queue_size jobs in queue"
else
    echo -e "${GREEN}✓${NC} Queue is empty"
fi

echo ""

# Show recent logs
echo -e "${BLUE}Recent Queue Worker Logs:${NC}"
sudo journalctl -u laravel-queue-worker --since "5 minutes ago" --no-pager | tail -5

echo ""
echo -e "${BLUE}Recent Scheduler Logs:${NC}"
sudo journalctl -u laravel-scheduler --since "5 minutes ago" --no-pager | tail -5
