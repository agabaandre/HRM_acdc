#!/bin/bash

# Production Email System Test Script
# Run this on your production server to test the email system

echo "🚀 Africa CDC APM - Email System Test"
echo "======================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_DIR="/opt/homebrew/var/www/staff/apm"
TEST_EMAIL="test@example.com"  # Change this to your test email

echo -e "${BLUE}📧 Test Email: ${TEST_EMAIL}${NC}"
echo -e "${BLUE}📁 Project Directory: ${PROJECT_DIR}${NC}"
echo ""

# Check if we're in the right directory
if [ ! -f "${PROJECT_DIR}/artisan" ]; then
    echo -e "${RED}❌ Error: Laravel project not found at ${PROJECT_DIR}${NC}"
    echo "Please update the PROJECT_DIR variable in this script"
    exit 1
fi

cd "${PROJECT_DIR}"

echo -e "${YELLOW}🔍 Running comprehensive email system tests...${NC}"
echo ""

# Test 1: Basic Email Test
echo -e "${BLUE}1. Testing Basic Email Sending...${NC}"
php artisan test:email-system --test-type=basic --email="${TEST_EMAIL}"
echo ""

# Test 2: Matrix Notification Test
echo -e "${BLUE}2. Testing Matrix Notifications...${NC}"
php artisan test:email-system --test-type=matrix --email="${TEST_EMAIL}"
echo ""

# Test 3: Daily Notification Test
echo -e "${BLUE}3. Testing Daily Notifications...${NC}"
php artisan test:email-system --test-type=daily --detailed
echo ""

# Test 4: Queue System Test
echo -e "${BLUE}4. Testing Queue System...${NC}"
php artisan test:email-system --test-type=queue --detailed
echo ""

# Test 5: Process some jobs
echo -e "${BLUE}5. Processing Queue Jobs...${NC}"
echo "Processing 3 jobs from the queue..."
for i in {1..3}; do
    echo "  Processing job $i..."
    php artisan queue:work --once --timeout=30
    if [ $? -eq 0 ]; then
        echo -e "  ${GREEN}✅ Job $i processed successfully${NC}"
    else
        echo -e "  ${RED}❌ Job $i failed${NC}"
    fi
done
echo ""

# Test 6: Check system status
echo -e "${BLUE}6. System Status Check...${NC}"
echo "Queue Status:"
php artisan queue:failed | head -5
echo ""
echo "Queue Size:"
php artisan tinker --execute="echo 'Jobs in queue: ' . \Illuminate\Support\Facades\DB::table('jobs')->count();"
echo ""

# Test 7: Manual daily notification
echo -e "${BLUE}7. Manual Daily Notification Test...${NC}"
echo "Dispatching daily notification job..."
php artisan tinker --execute="\App\Jobs\SendDailyPendingApprovalsNotificationJob::dispatch(); echo 'Daily notification job dispatched successfully';"
echo ""

# Test 8: Check logs
echo -e "${BLUE}8. Recent Email Logs...${NC}"
echo "Checking recent email-related logs..."
tail -10 storage/logs/laravel-$(date +%Y-%m-%d).log | grep -i "mail\|email\|notification" || echo "No recent email logs found"
echo ""

echo -e "${GREEN}🎉 Email System Test Complete!${NC}"
echo ""
echo -e "${YELLOW}📋 Next Steps:${NC}"
echo "1. Check your email inbox for test emails"
echo "2. Monitor the queue: php artisan queue:work --once"
echo "3. Check logs: tail -f storage/logs/laravel-$(date +%Y-%m-%d).log"
echo "4. Use the Systemd Monitor in the web interface for ongoing management"
echo ""
echo -e "${BLUE}🔧 Useful Commands:${NC}"
echo "• View failed jobs: php artisan queue:failed"
echo "• Retry failed jobs: php artisan queue:retry all"
echo "• Clear failed jobs: php artisan queue:flush"
echo "• Process jobs: php artisan queue:work --once"
echo "• Check queue size: php artisan tinker --execute=\"echo \\Illuminate\\Support\\Facades\\DB::table('jobs')->count();\""
echo ""
echo -e "${GREEN}✅ Test completed successfully!${NC}"
