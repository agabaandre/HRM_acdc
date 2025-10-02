<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Matrix;
use App\Models\Staff;

// Include the helper functions
require_once 'app/Helpers/MailingHelper.php';

try {
    echo "🧪 TESTING CENTRALIZED EMAIL SYSTEM\n";
    echo "===================================\n";
    echo "📧 Sending test emails to: agabaandre@gmail.com\n";
    echo "🔧 Using centralized email dispatcher with Exchange as primary\n\n";
    
    // Create test data
    $matrix = new Matrix();
    $matrix->id = 1;
    $matrix->title = 'Centralized Email Test Matrix';
    $matrix->description = 'Testing the centralized email system';
    
    $staff = new Staff();
    $staff->staff_id = 1;
    $staff->work_email = 'agabaandre@gmail.com';
    $staff->fname = 'Test';
    $staff->lname = 'User';
    
    echo "🔧 Environment Check:\n";
    echo "   - USE_EXCHANGE_EMAIL: " . (env('USE_EXCHANGE_EMAIL') ? 'true' : 'false') . "\n";
    echo "   - Primary Method: " . (env('USE_EXCHANGE_EMAIL') ? 'Exchange (Office 365)' : 'PHPMailer (SMTP)') . "\n\n";
    
    // Test 1: Central email dispatcher
    echo "📧 Test 1: Central Email Dispatcher\n";
    echo "-----------------------------------\n";
    $result1 = sendEmail(
        'agabaandre@gmail.com',
        'Central Email Test',
        '<h2>Central Email Test</h2><p>This email was sent using the centralized email dispatcher.</p>'
    );
    echo "   Result: " . ($result1 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Test 2: Simple email function
    echo "📧 Test 2: Simple Email Function\n";
    echo "--------------------------------\n";
    $result2 = sendSimpleEmail(
        'agabaandre@gmail.com',
        'Simple Email Test',
        'This is a simple email test using the convenience function.'
    );
    echo "   Result: " . ($result2 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Test 3: Staff notification
    echo "📧 Test 3: Staff Notification\n";
    echo "-----------------------------\n";
    $result3 = sendStaffNotification(
        $staff,
        'Staff Notification Test',
        'This is a staff notification test.',
        'test'
    );
    echo "   Result: " . ($result3 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Test 4: Matrix notification
    echo "📧 Test 4: Matrix Notification\n";
    echo "-----------------------------\n";
    $result4 = sendMatrixNotification(
        $matrix,
        $staff,
        'matrix_approval',
        'This matrix requires your approval for testing purposes.'
    );
    echo "   Result: " . ($result4 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Test 5: Daily pending approvals
    echo "📧 Test 5: Daily Pending Approvals\n";
    echo "----------------------------------\n";
    $pendingApprovals = [
        ['id' => 1, 'type' => 'Matrix', 'title' => 'Test Matrix 1'],
        ['id' => 2, 'type' => 'Service Request', 'title' => 'Test Service Request 1']
    ];
    $summaryStats = [
        'total' => 2,
        'matrices' => 1,
        'service_requests' => 1,
        'arf_requests' => 0
    ];
    $result5 = sendDailyPendingApprovalsEmail($staff, $pendingApprovals, $summaryStats);
    echo "   Result: " . ($result5 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Test 6: Generic notification
    echo "📧 Test 6: Generic Notification\n";
    echo "------------------------------\n";
    $result6 = sendGenericNotificationEmail(
        $staff,
        'Generic Notification Test',
        'This is a generic notification test with additional data.',
        ['Test Field' => 'Test Value', 'Another Field' => 'Another Value']
    );
    echo "   Result: " . ($result6 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Summary
    echo "📊 SUMMARY\n";
    echo "==========\n";
    $results = [$result1, $result2, $result3, $result4, $result5, $result6];
    $successCount = count(array_filter($results));
    $totalCount = count($results);
    
    echo "✅ Successful: {$successCount}/{$totalCount}\n";
    echo "❌ Failed: " . ($totalCount - $successCount) . "/{$totalCount}\n";
    
    if ($successCount === $totalCount) {
        echo "🎉 ALL TESTS PASSED! Centralized email system is working perfectly!\n";
        echo "📧 Check your inbox at agabaandre@gmail.com for all test emails\n";
    } else {
        echo "⚠️  Some tests failed. Check the logs for details.\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
