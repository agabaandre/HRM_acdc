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
    echo "🧪 TESTING PHPMailer with SMTP Configuration\n";
    echo "============================================\n";
    echo "📧 Sending test email to: agabaandre@gmail.com\n";
    echo "🔧 Using PHPMailer with separate SMTP environment variables\n\n";
    
    // Create a test matrix and staff member
    $matrix = new Matrix();
    $matrix->id = 1;
    $matrix->title = 'PHPMailer SMTP Test Matrix';
    $matrix->description = 'Testing PHPMailer with dedicated SMTP configuration';
    
    $staff = new Staff();
    $staff->work_email = 'agabaandre@gmail.com';
    $staff->first_name = 'Test';
    $staff->last_name = 'User';
    
    echo "📋 Test Details:\n";
    echo "   - Matrix: {$matrix->title}\n";
    echo "   - Recipient: {$staff->work_email}\n";
    echo "   - Type: matrix_approval\n";
    echo "   - Implementation: PHPMailer with SMTP\n\n";
    
    echo "🔧 Environment Variables Check:\n";
    echo "   - PHPMailer_HOST: " . (env('PHPMailer_HOST') ?: 'Not set (will use MAIL_HOST)') . "\n";
    echo "   - PHPMailer_USERNAME: " . (env('PHPMailer_USERNAME') ?: 'Not set (will use MAIL_USERNAME)') . "\n";
    echo "   - PHPMailer_PASSWORD: " . (env('PHPMailer_PASSWORD') ? 'Set' : 'Not set (will use MAIL_PASSWORD)') . "\n";
    echo "   - PHPMailer_PORT: " . (env('PHPMailer_PORT') ?: 'Not set (will use MAIL_PORT)') . "\n";
    echo "   - PHPMailer_FROM_ADDRESS: " . (env('PHPMailer_FROM_ADDRESS') ?: 'Not set (will use MAIL_FROM_ADDRESS)') . "\n\n";
    
    // Test the PHPMailer integration directly
    echo "🚀 Sending email via PHPMailer SMTP...\n";
    $result = sendMatrixNotificationWithPHPMailer(
        $matrix,
        $staff,
        'matrix_approval',
        'This is a test of PHPMailer with dedicated SMTP configuration and separate environment variables.'
    );
    
    if ($result) {
        echo "✅ SUCCESS! PHPMailer SMTP email sent successfully!\n";
        echo "🎉 PHPMailer with separate SMTP config is working perfectly!\n";
        echo "📧 Check your inbox at agabaandre@gmail.com\n";
    } else {
        echo "❌ FAILED! Could not send PHPMailer SMTP email.\n";
        echo "💡 Check your SMTP configuration in .env file\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
