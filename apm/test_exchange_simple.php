<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use AgabaandreOffice365\ExchangeEmailService\ExchangeEmailService;

try {
    echo "🔧 Testing Exchange Email Service...\n";
    echo "📧 Sending test email to: agabaandre@gmail.com\n";
    
    // Get configuration
    $config = config('exchange-email');
    $emailService = new ExchangeEmailService($config);
    
    // Check if configured
    if (!$emailService->isConfigured()) {
        echo "❌ Exchange service not configured\n";
        exit(1);
    }
    
    echo "✅ Exchange service is configured\n";
    
    // Send test email
    $result = $emailService->sendEmail(
        'agabaandre@gmail.com',
        'Test Email from APM - ' . date('Y-m-d H:i:s'),
        '<h1>🧪 Test Email from APM System</h1>
        <p>This is a test email sent via Microsoft Graph API (Office 365 Exchange).</p>
        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <h3>Configuration Details:</h3>
            <ul>
                <li><strong>Service:</strong> Office 365 Exchange (Microsoft Graph API)</li>
                <li><strong>App Name:</strong> ' . config('app.name') . '</li>
                <li><strong>Environment:</strong> ' . config('app.env') . '</li>
                <li><strong>Timestamp:</strong> ' . date('Y-m-d H:i:s') . '</li>
                <li><strong>From:</strong> ' . $config['from_email'] . '</li>
            </ul>
        </div>
        <p>If you received this email, your Office 365 Exchange configuration is working correctly! 🎉</p>',
        true // HTML email
    );
    
    if ($result) {
        echo "✅ Test email sent successfully via Office 365 Exchange!\n";
    } else {
        echo "❌ Failed to send test email via Office 365 Exchange.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
