<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Use the working implementation from payments project
require_once '/opt/homebrew/var/www/staff/payments/src/ExchangeOAuth.php';

use AgabaandreOffice365\ExchangeEmailService\ExchangeOAuth;

try {
    echo "🔧 Testing Exchange Email Service (Working Implementation)...\n";
    echo "📧 Sending test email to: agabaandre@gmail.com\n";
    
    // Get configuration
    $config = config('exchange-email');
    
    // Create OAuth instance
    $oauth = new ExchangeOAuth(
        $config['tenant_id'],
        $config['client_id'],
        $config['client_secret'],
        $config['redirect_uri'] ?? 'http://localhost:8000/oauth/callback',
        $config['scope'] ?? 'https://graph.microsoft.com/.default',
        'client_credentials' // Force client credentials
    );
    
    // Check if configured
    if (!$oauth->isConfigured()) {
        echo "❌ Exchange service not configured\n";
        exit(1);
    }
    
    echo "✅ Exchange service is configured\n";
    
    // Get client credentials token
    echo "🔑 Getting client credentials token...\n";
    $oauth->getClientCredentialsToken();
    echo "✅ Token obtained successfully\n";
    
    // Send test email
    $result = $oauth->sendEmail(
        'agabaandre@gmail.com',
        'Test Email from APM - ' . date('Y-m-d H:i:s'),
        '<h1>🧪 Test Email from APM System</h1>
        <p>This is a test email sent via Microsoft Graph API (Office 365 Exchange) using the working implementation.</p>
        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <h3>Configuration Details:</h3>
            <ul>
                <li><strong>Service:</strong> Office 365 Exchange (Microsoft Graph API)</li>
                <li><strong>Auth Method:</strong> Client Credentials Flow</li>
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
