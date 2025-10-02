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
    echo "🧪 TESTING BOTH EMAIL METHODS\n";
    echo "=============================\n";
    echo "📧 Sending test emails to: agabaandre@gmail.com\n\n";
    
    // Create test data
    $matrix = new Matrix();
    $matrix->id = 1;
    $matrix->title = 'Email Method Test Matrix';
    $matrix->description = 'Testing both Exchange and PHPMailer methods';
    
    $staff = new Staff();
    $staff->staff_id = 1;
    $staff->work_email = 'agabaandre@gmail.com';
    $staff->fname = 'Test';
    $staff->lname = 'User';
    
    echo "🔧 Current Configuration:\n";
    echo "   - USE_EXCHANGE_EMAIL: " . (env('USE_EXCHANGE_EMAIL') ? 'true' : 'false') . "\n";
    echo "   - Primary Method: " . (env('USE_EXCHANGE_EMAIL') ? 'Exchange (Office 365)' : 'PHPMailer (SMTP)') . "\n\n";
    
    // Test 1: Central Email Dispatcher (uses configured primary method)
    echo "📧 Test 1: Central Email Dispatcher (Current Primary Method)\n";
    echo "------------------------------------------------------------\n";
    $result1 = sendEmail(
        'agabaandre@gmail.com',
        'Central Email Test - ' . (env('USE_EXCHANGE_EMAIL') ? 'Exchange' : 'PHPMailer'),
        '<h2>Central Email Test</h2><p>This email was sent using the <strong>' . (env('USE_EXCHANGE_EMAIL') ? 'Exchange (Office 365)' : 'PHPMailer (SMTP)') . '</strong> method.</p><p>Timestamp: ' . now() . '</p>'
    );
    echo "   Result: " . ($result1 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Test 2: Force Exchange Method
    echo "📧 Test 2: Force Exchange Method\n";
    echo "--------------------------------\n";
    $originalExchangeSetting = env('USE_EXCHANGE_EMAIL');
    putenv('USE_EXCHANGE_EMAIL=true');
    
    $result2 = sendEmail(
        'agabaandre@gmail.com',
        'Exchange Email Test',
        '<h2>Exchange Email Test</h2><p>This email was sent using the <strong>Exchange (Office 365)</strong> method.</p><p>Timestamp: ' . now() . '</p>'
    );
    echo "   Result: " . ($result2 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Test 3: Force PHPMailer Method
    echo "📧 Test 3: Force PHPMailer Method\n";
    echo "---------------------------------\n";
    putenv('USE_EXCHANGE_EMAIL=false');
    
    $result3 = sendEmail(
        'agabaandre@gmail.com',
        'PHPMailer Email Test',
        '<h2>PHPMailer Email Test</h2><p>This email was sent using the <strong>PHPMailer (SMTP)</strong> method.</p><p>Timestamp: ' . now() . '</p>'
    );
    echo "   Result: " . ($result3 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Test 4: Matrix Notification (using current method)
    echo "📧 Test 4: Matrix Notification\n";
    echo "------------------------------\n";
    putenv('USE_EXCHANGE_EMAIL=' . ($originalExchangeSetting ? 'true' : 'false'));
    
    $result4 = sendMatrixNotification(
        $matrix,
        $staff,
        'matrix_approval',
        'This matrix requires your approval for testing both email methods.'
    );
    echo "   Result: " . ($result4 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Test 5: Staff Notification
    echo "📧 Test 5: Staff Notification\n";
    echo "-----------------------------\n";
    $result5 = sendStaffNotification(
        $staff,
        'Staff Notification Test',
        'This is a staff notification test to verify email functionality.',
        'test'
    );
    echo "   Result: " . ($result5 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Test 6: Simple Email
    echo "📧 Test 6: Simple Email\n";
    echo "-----------------------\n";
    $result6 = sendSimpleEmail(
        'agabaandre@gmail.com',
        'Simple Email Test',
        'This is a simple email test to verify the convenience function works correctly.'
    );
    echo "   Result: " . ($result6 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Restore original setting
    putenv('USE_EXCHANGE_EMAIL=' . ($originalExchangeSetting ? 'true' : 'false'));
    
    // Summary
    echo "📊 SUMMARY\n";
    echo "==========\n";
    $results = [$result1, $result2, $result3, $result4, $result5, $result6];
    $successCount = count(array_filter($results));
    $totalCount = count($results);
    
    echo "✅ Successful: {$successCount}/{$totalCount}\n";
    echo "❌ Failed: " . ($totalCount - $successCount) . "/{$totalCount}\n\n";
    
    echo "📧 Email Methods Tested:\n";
    echo "   - Central Dispatcher (Primary): " . ($result1 ? '✅' : '❌') . "\n";
    echo "   - Exchange (Office 365): " . ($result2 ? '✅' : '❌') . "\n";
    echo "   - PHPMailer (SMTP): " . ($result3 ? '✅' : '❌') . "\n";
    echo "   - Matrix Notification: " . ($result4 ? '✅' : '❌') . "\n";
    echo "   - Staff Notification: " . ($result5 ? '✅' : '❌') . "\n";
    echo "   - Simple Email: " . ($result6 ? '✅' : '❌') . "\n\n";
    
    if ($successCount === $totalCount) {
        echo "🎉 ALL TESTS PASSED! Both email methods are working perfectly!\n";
        echo "📧 Check your inbox at agabaandre@gmail.com for all test emails\n";
    } else {
        echo "⚠️  Some tests failed. Check the logs for details.\n";
        echo "💡 Make sure your email configuration is correct in .env file\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
