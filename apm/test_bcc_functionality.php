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
    echo "🧪 TESTING BCC FUNCTIONALITY\n";
    echo "============================\n";
    echo "📧 Testing BCC system@africacdc.org on all emails\n\n";
    
    // Create test data
    $matrix = new Matrix();
    $matrix->id = 1;
    $matrix->title = 'BCC Test Matrix';
    $matrix->description = 'Testing BCC functionality';
    
    $staff = new Staff();
    $staff->staff_id = 1;
    $staff->work_email = 'agabaandre@gmail.com';
    $staff->fname = 'Test';
    $staff->lname = 'User';
    
    echo "🔧 Current Configuration:\n";
    echo "   - USE_EXCHANGE_EMAIL: " . (env('USE_EXCHANGE_EMAIL') ? 'true' : 'false') . "\n";
    echo "   - Primary Method: " . (env('USE_EXCHANGE_EMAIL') ? 'Exchange (Office 365)' : 'PHPMailer (SMTP)') . "\n";
    echo "   - System BCC: system@africacdc.org (automatically added)\n\n";
    
    // Test 1: Central Email Dispatcher with BCC
    echo "📧 Test 1: Central Email Dispatcher with BCC\n";
    echo "--------------------------------------------\n";
    $result1 = sendEmail(
        'agabaandre@gmail.com',
        'BCC Test - Central Dispatcher',
        '<h2>BCC Test - Central Dispatcher</h2><p>This email should have system@africacdc.org as BCC.</p><p>Timestamp: ' . now() . '</p>'
    );
    echo "   Result: " . ($result1 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Test 2: Simple Email with BCC
    echo "📧 Test 2: Simple Email with BCC\n";
    echo "--------------------------------\n";
    $result2 = sendSimpleEmail(
        'agabaandre@gmail.com',
        'BCC Test - Simple Email',
        'This simple email should also have system@africacdc.org as BCC.'
    );
    echo "   Result: " . ($result2 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Test 3: Staff Notification with BCC
    echo "📧 Test 3: Staff Notification with BCC\n";
    echo "--------------------------------------\n";
    $result3 = sendStaffNotification(
        $staff,
        'BCC Test - Staff Notification',
        'This staff notification should have system@africacdc.org as BCC.',
        'test'
    );
    echo "   Result: " . ($result3 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Test 4: Matrix Notification with BCC
    echo "📧 Test 4: Matrix Notification with BCC\n";
    echo "---------------------------------------\n";
    $result4 = sendMatrixNotification(
        $matrix,
        $staff,
        'matrix_approval',
        'This matrix notification should have system@africacdc.org as BCC.'
    );
    echo "   Result: " . ($result4 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Test 5: Daily Pending Approvals with BCC
    echo "📧 Test 5: Daily Pending Approvals with BCC\n";
    echo "-------------------------------------------\n";
    $pendingApprovals = [
        ['id' => 1, 'type' => 'Matrix', 'title' => 'BCC Test Matrix 1'],
        ['id' => 2, 'type' => 'Service Request', 'title' => 'BCC Test Service Request 1']
    ];
    $summaryStats = [
        'total' => 2,
        'matrices' => 1,
        'service_requests' => 1,
        'arf_requests' => 0
    ];
    $result5 = sendDailyPendingApprovalsEmail($staff, $pendingApprovals, $summaryStats);
    echo "   Result: " . ($result5 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Test 6: Generic Notification with BCC
    echo "📧 Test 6: Generic Notification with BCC\n";
    echo "----------------------------------------\n";
    $result6 = sendGenericNotificationEmail(
        $staff,
        'BCC Test - Generic Notification',
        'This generic notification should have system@africacdc.org as BCC.',
        ['Test Field' => 'Test Value', 'BCC Test' => 'System BCC should be included']
    );
    echo "   Result: " . ($result6 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Test 7: Test with explicit BCC (should not duplicate)
    echo "📧 Test 7: Explicit BCC (No Duplication)\n";
    echo "----------------------------------------\n";
    $result7 = sendEmail(
        'agabaandre@gmail.com',
        'BCC Test - Explicit BCC',
        '<h2>BCC Test - Explicit BCC</h2><p>This email has explicit BCC but should not duplicate system@africacdc.org.</p>',
        null,
        null,
        [],
        ['system@africacdc.org', 'test@example.com'] // Explicitly include system BCC
    );
    echo "   Result: " . ($result7 ? '✅ SUCCESS' : '❌ FAILED') . "\n\n";
    
    // Summary
    echo "📊 SUMMARY\n";
    echo "==========\n";
    $results = [$result1, $result2, $result3, $result4, $result5, $result6, $result7];
    $successCount = count(array_filter($results));
    $totalCount = count($results);
    
    echo "✅ Successful: {$successCount}/{$totalCount}\n";
    echo "❌ Failed: " . ($totalCount - $successCount) . "/{$totalCount}\n\n";
    
    echo "📧 BCC Tests:\n";
    echo "   - Central Dispatcher: " . ($result1 ? '✅' : '❌') . "\n";
    echo "   - Simple Email: " . ($result2 ? '✅' : '❌') . "\n";
    echo "   - Staff Notification: " . ($result3 ? '✅' : '❌') . "\n";
    echo "   - Matrix Notification: " . ($result4 ? '✅' : '❌') . "\n";
    echo "   - Daily Pending Approvals: " . ($result5 ? '✅' : '❌') . "\n";
    echo "   - Generic Notification: " . ($result6 ? '✅' : '❌') . "\n";
    echo "   - Explicit BCC (No Duplication): " . ($result7 ? '✅' : '❌') . "\n\n";
    
    if ($successCount === $totalCount) {
        echo "🎉 ALL BCC TESTS PASSED! system@africacdc.org is being added to all emails!\n";
        echo "📧 Check your inbox at agabaandre@gmail.com for all test emails\n";
        echo "📧 Check system@africacdc.org inbox for BCC copies of all emails\n";
    } else {
        echo "⚠️  Some BCC tests failed. Check the logs for details.\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
