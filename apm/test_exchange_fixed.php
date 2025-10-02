<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use AgabaandreOffice365\ExchangeEmailService\ExchangeEmailService;

try {
    echo "🔧 Testing Office 365 Exchange Email Service (Fixed)...\n";
    echo "📧 Sending test email to: agabaandre@gmail.com\n";
    
    // Create a minimal configuration
    $config = [
        'tenant_id' => env('EXCHANGE_TENANT_ID'),
        'client_id' => env('EXCHANGE_CLIENT_ID'),
        'client_secret' => env('EXCHANGE_CLIENT_SECRET'),
        'scope' => 'https://graph.microsoft.com/.default',
        'auth_method' => 'client_credentials',
        'from_email' => env('MAIL_FROM_ADDRESS'),
        'from_name' => env('MAIL_FROM_NAME'),
    ];
    
    // Initialize Exchange service
    $emailService = new ExchangeEmailService($config);
    
    // Check if service is configured
    if (!$emailService->isConfigured()) {
        echo "❌ Exchange service is not properly configured.\n";
        exit(1);
    }
    
    echo "✅ Exchange service is configured and ready!\n";
    
    // Try to send email using the sendHtmlEmail method instead
    $result = $emailService->sendHtmlEmail(
        'agabaandre@gmail.com',
        'Test Email from APM System - ' . date('Y-m-d H:i:s'),
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
        <p>If you received this email, your Office 365 Exchange configuration is working correctly! 🎉</p>
        <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">
        <p style="color: #666; font-size: 12px;">
            This is an automated test email from ' . config('app.name') . ' using Microsoft Graph API.
        </p>'
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
