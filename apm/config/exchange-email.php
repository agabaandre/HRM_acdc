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
    'tenant_id' => getenv('EXCHANGE_TENANT_ID') ?: '',
    'client_id' => getenv('EXCHANGE_CLIENT_ID') ?: '',
    'client_secret' => getenv('EXCHANGE_CLIENT_SECRET') ?: '',
    'redirect_uri' => getenv('EXCHANGE_REDIRECT_URI') ?: 'http://localhost:8000/oauth/callback',
    'scope' => getenv('EXCHANGE_SCOPE') ?: 'https://graph.microsoft.com/.default',

    // Authentication Method
    // Options: 'authorization_code', 'client_credentials'
    'auth_method' => getenv('EXCHANGE_AUTH_METHOD') ?: 'client_credentials',

    // Email Configuration
    'from_email' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@example.com',
    'from_name' => getenv('MAIL_FROM_NAME') ?: 'Exchange Email Service',

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
