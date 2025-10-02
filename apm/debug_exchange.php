<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use AgabaandreOffice365\ExchangeEmailService\ExchangeEmailService;

try {
    echo "🔧 Debugging Office 365 Exchange Email Service...\n";
    
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
    
    echo "Configuration:\n";
    foreach ($config as $key => $value) {
        echo "- $key: " . (is_string($value) ? $value : (is_array($value) ? 'Array' : 'Other')) . "\n";
    }
    
    // Initialize Exchange service
    $emailService = new ExchangeEmailService($config);
    
    // Check if service is configured
    if (!$emailService->isConfigured()) {
        echo "❌ Exchange service is not properly configured.\n";
        exit(1);
    }
    
    echo "✅ Exchange service is configured and ready!\n";
    
    // Try to get token info first
    try {
        $tokenInfo = $emailService->getTokenInfo();
        echo "Token info: " . json_encode($tokenInfo) . "\n";
    } catch (Exception $e) {
        echo "Token error: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
