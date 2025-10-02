<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use AgabaandreOffice365\ExchangeEmailService\ExchangeEmailService;

try {
    echo "🔧 Sending Simple Test Email...\n";
    
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
    
    echo "📧 Sending test email to: agabaandre@gmail.com\n";
    
    // Send a very simple email
    $result = $emailService->sendEmail(
        'agabaandre@gmail.com',
        'Simple Test Email',
        'This is a simple test email from the APM system.',
        false // Plain text email
    );
    
    if ($result) {
        echo "✅ Test email sent successfully!\n";
    } else {
        echo "❌ Failed to send test email.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
