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
    echo "🧪 TESTING MAIN DISPATCHER FUNCTION\n";
    echo "===================================\n";
    echo "📧 Sending test email to: agabaandre@gmail.com\n";
    echo "🔧 Using main sendMatrixNotification function (chooses Exchange or PHPMailer)\n\n";
    
    // Create a test matrix and staff member
    $matrix = new Matrix();
    $matrix->id = 1;
    $matrix->title = 'Main Dispatcher Test Matrix';
    $matrix->description = 'Testing the main dispatcher function that chooses between Exchange and PHPMailer';
    
    $staff = new Staff();
    $staff->work_email = 'agabaandre@gmail.com';
    $staff->first_name = 'Test';
    $staff->last_name = 'User';
    
    echo "📋 Test Details:\n";
    echo "   - Matrix: {$matrix->title}\n";
    echo "   - Recipient: {$staff->work_email}\n";
    echo "   - Type: matrix_approval\n";
    echo "   - Implementation: Main dispatcher (Exchange preferred, PHPMailer fallback)\n\n";
    
    // Test the main dispatcher function
    echo "🚀 Sending email via main dispatcher...\n";
    $result = sendMatrixNotification(
        $matrix,
        $staff,
        'matrix_approval',
        'This is a test of the main dispatcher function that automatically chooses between Exchange and PHPMailer based on configuration.'
    );
    
    if ($result) {
        echo "✅ SUCCESS! Main dispatcher email sent successfully!\n";
        echo "🎉 Email system is working perfectly!\n";
        echo "📧 Check your inbox at agabaandre@gmail.com\n";
    } else {
        echo "❌ FAILED! Could not send email via main dispatcher.\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}