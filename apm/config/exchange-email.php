<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Exchange Email Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Exchange Email Service using Microsoft Graph API
    | with support for multiple authentication methods and file-based token storage
    |
    */

    // Microsoft Graph OAuth Configuration
    // Use env() helper for Laravel .env support, fallback to getenv() and $_ENV
    'tenant_id' => env('EXCHANGE_TENANT_ID') ?: ($_ENV['EXCHANGE_TENANT_ID'] ?? getenv('EXCHANGE_TENANT_ID') ?: ''),
    'client_id' => env('EXCHANGE_CLIENT_ID') ?: ($_ENV['EXCHANGE_CLIENT_ID'] ?? getenv('EXCHANGE_CLIENT_ID') ?: ''),
    'client_secret' => env('EXCHANGE_CLIENT_SECRET') ?: ($_ENV['EXCHANGE_CLIENT_SECRET'] ?? getenv('EXCHANGE_CLIENT_SECRET') ?: ''),
    'redirect_uri' => env('EXCHANGE_REDIRECT_URI') ?: ($_ENV['EXCHANGE_REDIRECT_URI'] ?? getenv('EXCHANGE_REDIRECT_URI') ?: 'http://localhost/staff/auth/message_callback'),
    'scope' => env('EXCHANGE_SCOPE') ?: ($_ENV['EXCHANGE_SCOPE'] ?? getenv('EXCHANGE_SCOPE') ?: 'https://graph.microsoft.com/.default'),

    // Authentication Method
    // Options: 'authorization_code', 'client_credentials'
    'auth_method' => env('EXCHANGE_AUTH_METHOD') ?: ($_ENV['EXCHANGE_AUTH_METHOD'] ?? getenv('EXCHANGE_AUTH_METHOD') ?: 'client_credentials'),

    // Email Configuration
    'from_email' => env('MAIL_FROM_ADDRESS') ?: ($_ENV['MAIL_FROM_ADDRESS'] ?? getenv('MAIL_FROM_ADDRESS') ?: 'noreply@example.com'),
    'from_name' => env('MAIL_FROM_NAME') ?: ($_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?: 'Exchange Email Service'),

    // Token Storage Configuration (file-based)
    'token_storage' => [
        'type' => 'file',
        'path' => 'tokens/oauth_tokens.json',
        'permissions' => 0644,
    ],

    // OAuth Configuration
    'oauth' => [
        'authorize_url' => 'https://login.microsoftonline.com/{tenant_id}/oauth2/v2.0/authorize',
        'token_url' => 'https://login.microsoftonline.com/{tenant_id}/oauth2/v2.0/token',
        'graph_url' => 'https://graph.microsoft.com/v1.0',
    ],

    // Default Settings
    'defaults' => [
        'is_html' => true,
        'timeout' => 30,
        'retry_attempts' => 3,
        'debug' => filter_var(getenv('EXCHANGE_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    ],

    // Error Handling
    'error_handling' => [
        'retry_on_failure' => filter_var(getenv('EXCHANGE_RETRY_ON_FAILURE') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'max_retries' => (int)(getenv('EXCHANGE_MAX_RETRIES') ?: '3'),
        'retry_delay' => (int)(getenv('EXCHANGE_RETRY_DELAY') ?: '5'), // seconds
        'fallback_method' => getenv('EXCHANGE_FALLBACK_METHOD') ?: 'smtp',
    ],

    // Logging Configuration
    'logging' => [
        'enabled' => filter_var(getenv('EXCHANGE_LOGGING') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'level' => getenv('EXCHANGE_LOG_LEVEL') ?: 'info',
        'log_file' => getenv('EXCHANGE_LOG_FILE') ?: 'logs/exchange-email.log',
    ],
];
