<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Microsoft Entra ID (Azure AD) — same app as CI3 staff auth / APM mobile SSO.
    */
    'microsoft' => [
        // Canonical credentials: EXCHANGE_* from /staff/.env (shared/load-staff-root-env.php).
        'tenant_id' => env('EXCHANGE_TENANT_ID'),
        'client_id' => env('EXCHANGE_CLIENT_ID'),
        'client_secret' => env('EXCHANGE_CLIENT_SECRET'),
        'redirect_uri' => env('MICROSOFT_REDIRECT_URI', env('EXCHANGE_REDIRECT_URI')),
    ],

    'staff_api' => [
        'base_url' => env(
            'STAFF_API_INTERNAL_BASE_URL',
            env('STAFF_API_BASE_URL', env('BASE_URL', 'http://127.0.0.1/staff/backend'))
        ),
        'token' => env('STAFF_API_TOKEN'),
        'username' => env('STAFF_API_USERNAME'),
        'password' => env('STAFF_API_PASSWORD'),
    ],

];
