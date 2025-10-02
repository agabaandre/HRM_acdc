<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Mail;

try {
    echo "🔧 Testing Laravel Mail...\n";
    echo "📧 Sending test email to: agabaandre@gmail.com\n";
    
    // Set mail to use SMTP
    config(['mail.default' => 'smtp']);
    
    $result = Mail::raw('Hello! This is a test email from the APM system.', function ($message) {
        $message->to('agabaandre@gmail.com')
                ->subject('Test Email from APM System - ' . date('Y-m-d H:i:s'));
    });
    
    echo "✅ Test email sent successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
