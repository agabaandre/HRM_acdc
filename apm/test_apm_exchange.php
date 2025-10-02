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
    echo "🔧 Testing APM Exchange Email Integration...\n";
    echo "📧 Sending test email to: agabaandre@gmail.com\n";
    
    // Create a test matrix and staff member
    $matrix = new Matrix();
    $matrix->id = 1;
    $matrix->title = 'Test Matrix';
    $matrix->description = 'Test Matrix Description';
    
    $staff = new Staff();
    $staff->work_email = 'agabaandre@gmail.com';
    $staff->first_name = 'Test';
    $staff->last_name = 'User';
    
    // Test the Exchange integration
    $result = sendMatrixNotificationWithExchange(
        $matrix,
        $staff,
        'matrix_approval',
        'This is a test notification from APM system using Exchange email service.'
    );
    
    if ($result) {
        echo "✅ APM Exchange email sent successfully!\n";
    } else {
        echo "❌ Failed to send APM Exchange email.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
