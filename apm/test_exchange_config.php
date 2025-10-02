<?php

/**
 * Test script to verify Exchange email configuration
 * Run this on the server: php test_exchange_config.php
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

echo "=== Exchange Email Configuration Test ===\n\n";

try {
    // Test 1: Check mail configuration
    echo "Test 1: Mail Configuration\n";
    $defaultMailer = Config::get('mail.default');
    echo "  - Default Mailer: {$defaultMailer}\n";
    
    $exchangeConfig = Config::get('mail.mailers.exchange');
    if ($exchangeConfig) {
        echo "  - Exchange Mailer: Configured\n";
        echo "  - Exchange Service: {$exchangeConfig['service']}\n";
    } else {
        echo "  - Exchange Mailer: NOT CONFIGURED\n";
    }
    
    echo "\n";
    
    // Test 2: Check Exchange configuration
    echo "Test 2: Exchange Configuration\n";
    $exchangeEmailConfig = Config::get('exchange-email');
    if ($exchangeEmailConfig) {
        echo "  - Tenant ID: " . (empty($exchangeEmailConfig['tenant_id']) ? 'NOT SET' : 'SET') . "\n";
        echo "  - Client ID: " . (empty($exchangeEmailConfig['client_id']) ? 'NOT SET' : 'SET') . "\n";
        echo "  - Client Secret: " . (empty($exchangeEmailConfig['client_secret']) ? 'NOT SET' : 'SET') . "\n";
        echo "  - Auth Method: {$exchangeEmailConfig['auth_method']}\n";
        echo "  - From Email: {$exchangeEmailConfig['from_email']}\n";
        echo "  - From Name: {$exchangeEmailConfig['from_name']}\n";
    } else {
        echo "  - Exchange Email Config: NOT FOUND\n";
    }
    
    echo "\n";
    
    // Test 3: Check environment variables
    echo "Test 3: Environment Variables\n";
    echo "  - MAIL_MAILER: " . env('MAIL_MAILER', 'NOT SET') . "\n";
    echo "  - USE_EXCHANGE_EMAIL: " . env('USE_EXCHANGE_EMAIL', 'NOT SET') . "\n";
    echo "  - EXCHANGE_TENANT_ID: " . (env('EXCHANGE_TENANT_ID') ? 'SET' : 'NOT SET') . "\n";
    echo "  - EXCHANGE_CLIENT_ID: " . (env('EXCHANGE_CLIENT_ID') ? 'SET' : 'NOT SET') . "\n";
    echo "  - EXCHANGE_CLIENT_SECRET: " . (env('EXCHANGE_CLIENT_SECRET') ? 'SET' : 'NOT SET') . "\n";
    
    echo "\n";
    
    // Test 4: Verify Exchange is the default
    if ($defaultMailer === 'exchange') {
        echo "✅ Exchange is set as the default mailer\n";
    } else {
        echo "❌ Exchange is NOT the default mailer (current: {$defaultMailer})\n";
    }
    
    echo "\n";
    
    // Test 5: Check if EmailService exists
    echo "Test 5: EmailService Class\n";
    if (class_exists('App\Services\EmailService')) {
        echo "  ✅ EmailService class exists\n";
    } else {
        echo "  ❌ EmailService class NOT FOUND\n";
    }
    
    echo "\n=== Test Summary ===\n";
    if ($defaultMailer === 'exchange' && !empty($exchangeEmailConfig['tenant_id'])) {
        echo "✅ Exchange email is properly configured as default\n";
        echo "✅ Ready to send emails via Exchange\n";
    } else {
        echo "⚠️  Exchange email configuration needs attention\n";
    }
    
    echo "\n=== Test completed ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
