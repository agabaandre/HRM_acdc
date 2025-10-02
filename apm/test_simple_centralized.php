<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Include the helper functions
require_once 'app/Helpers/MailingHelper.php';

try {
    echo "🧪 TESTING CENTRALIZED EMAIL SYSTEM (Simple)\n";
    echo "============================================\n";
    
    echo "🔧 Environment Check:\n";
    echo "   - USE_EXCHANGE_EMAIL: " . (env('USE_EXCHANGE_EMAIL') ? 'true' : 'false') . "\n";
    echo "   - Primary Method: " . (env('USE_EXCHANGE_EMAIL') ? 'Exchange (Office 365)' : 'PHPMailer (SMTP)') . "\n\n";
    
    // Test central email dispatcher
    echo "📧 Testing Central Email Dispatcher...\n";
    $result = sendEmail(
        'agabaandre@gmail.com',
        'Central Email Test',
        '<h2>Central Email Test</h2><p>This email was sent using the centralized email dispatcher.</p>'
    );
    
    if ($result) {
        echo "✅ SUCCESS! Centralized email system is working!\n";
        echo "📧 Check your inbox at agabaandre@gmail.com\n";
    } else {
        echo "❌ FAILED! Check the logs for details.\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
