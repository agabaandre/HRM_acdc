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
    | Email attachments accepted by integration + admin send APIs (base64 JSON).
    */
    'mail_attachments' => [
        'max_count' => (int) env('MAIL_ATTACHMENT_MAX_COUNT', 10),
        'max_bytes_per_file' => (int) env('MAIL_ATTACHMENT_MAX_BYTES', 5 * 1024 * 1024),
        'max_bytes_total' => (int) env('MAIL_ATTACHMENT_MAX_TOTAL_BYTES', 15 * 1024 * 1024),
    ],

    /*
    | Suspicious audit-log heuristics (auth brute-force bursts, etc.).
    */
    'audit_suspicious' => [
        'window_minutes' => (int) env('AUDIT_SUSPICIOUS_WINDOW_MINUTES', 15),
        'auth_fail_ip_threshold' => (int) env('AUDIT_AUTH_FAIL_IP_THRESHOLD', 3),
        'auth_fail_email_threshold' => (int) env('AUDIT_AUTH_FAIL_EMAIL_THRESHOLD', 3),
        'password_reset_threshold' => (int) env('AUDIT_PASSWORD_RESET_THRESHOLD', 5),
    ],

    /*
    | Africa CDC Staff Share API (CBP) — SSO login + staff sync into users.
    | Preferred auth: POST {base}/share/login  JSON { username, password }
    | Docs: https://cbp.africacdc.org/staff/backend/share/docs
    */
    'staff_share' => [
        'enabled' => (bool) env('STAFF_SHARE_ENABLED', true),
        'base_url' => rtrim((string) env('STAFF_SHARE_BASE_URL', 'https://cbp.africacdc.org/staff/backend'), '/'),
        'api_token' => env('STAFF_SHARE_API_TOKEN'),
        'username' => env('STAFF_SHARE_USERNAME'),
        'password' => env('STAFF_SHARE_PASSWORD'),
        'timeout' => (int) env('STAFF_SHARE_TIMEOUT', 30),
        'page_size' => (int) env('STAFF_SHARE_PAGE_SIZE', 200),
        'default_is_admin' => (bool) env('STAFF_SHARE_DEFAULT_IS_ADMIN', false),
    ],

    /*
    | Microsoft 365 / Entra ID OAuth for admin panel login.
    | Redirect URI must match the Azure app registration exactly.
    */
    'microsoft' => [
        'enabled' => (bool) env('MICROSOFT_SSO_ENABLED', true),
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'tenant_id' => env('MICROSOFT_TENANT_ID'),
        // Production example: https://tools.africacdc.org/login/redirect
        // When empty, MicrosoftAuthService falls back to APP_URL + /login/redirect.
        'redirect_uri' => env('MICROSOFT_REDIRECT_URI'),
        'default_is_admin' => (bool) env('MICROSOFT_DEFAULT_IS_ADMIN', false),
    ],

];
