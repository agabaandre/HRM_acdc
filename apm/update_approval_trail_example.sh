#!/bin/bash

# Example script for updating activity approval trails
# This script demonstrates how to use the activity:update-approval-trail command

echo "=== Activity Approval Trail Update Examples ==="
echo ""

# Example 1: Approve multiple activities
echo "1. Approving multiple activities:"
php artisan activity:update-approval-trail "1,2,3" 1 599 approved --comments="Bulk approval for Q1 activities"

echo ""

# Example 2: Reject activities
echo "2. Rejecting activities:"
php artisan activity:update-approval-trail "4,5" 1 599 rejected --comments="Activities need more details"

echo ""

# Example 3: Return activities for revision
echo "3. Returning activities for revision:"
php artisan activity:update-approval-trail "6,7,8" 1 599 returned --comments="Please provide additional documentation"

echo ""

# Example 4: Pass activities (intermediate approval step)
echo "4. Passing activities (intermediate approval):"
php artisan activity:update-approval-trail "9,10" 1 599 passed --comments="Approved at this level, moving to next approver"

echo ""

# Example 5: Force update already approved activities
echo "5. Force updating already approved activities:"
php artisan activity:update-approval-trail "1,2" 1 599 approved --comments="Re-approval after changes" --force

echo ""
echo "=== Command Usage ==="
echo "php artisan activity:update-approval-trail <activities> <matrix_id> <approver_id> <action> [options]"
echo ""
echo "Arguments:"
echo "  activities    - Comma-separated list of activity IDs (e.g., '1,2,3,4')"
echo "  matrix_id     - Matrix ID for the activities"
echo "  approver_id   - Staff ID of the approver"
echo "  action        - Action taken: approved, rejected, returned, passed"
echo ""
echo "Options:"
echo "  --comments    - Optional comments for the approval trail"
echo "  --force       - Force update even if activity is already approved"
echo ""
echo "Examples:"
echo "  php artisan activity:update-approval-trail '1,2,3' 1 599 approved"
echo "  php artisan activity:update-approval-trail '4,5' 2 123 rejected --comments='Need more info'"
echo "  php artisan activity:update-approval-trail '6' 1 599 returned --force"
