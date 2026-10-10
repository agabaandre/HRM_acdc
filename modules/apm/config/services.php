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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'staff_api' => [
        // Laravel Share API (modules/staff-portal/backend). Legacy /staff is rewritten by StaffApiBaseUrl.
        'base_url' => env(
            'STAFF_API_INTERNAL_BASE_URL',
            env('STAFF_API_BASE_URL', env('BASE_URL', 'http://127.0.0.1/staff/backend'))
        ),
        'uploads_path' => env('STAFF_UPLOADS_PATH'), // optional; e.g. /var/www/staff/uploads for staff photo resolution
        // Prefer ?: so blank STAFF_API_TOKEN= still uses the Staff Share static token.
        'token' => env('STAFF_API_TOKEN') ?: 'YWZyY2FjZGNzdGFmZnRyYWNrZXI',
        'username' => env('STAFF_API_USERNAME'),
        'password' => env('STAFF_API_PASSWORD'),
        // Laravel Share at {base}/share/* — see https://cbp.africacdc.org/staff/backend/share/docs
        'endpoints' => [
            'staff' => '/share/get_current_staff',
            'divisions' => '/share/divisions',
            'directorates' => '/share/directorates',
            'users' => '/share/users',
            'cbp_modules' => '/share/cbp_modules',
            'signature' => '/share/get_signature',
            'photo' => '/share/get_photo',
        ],
    ],

    /*
    | Firebase Cloud Messaging (FCM) for push notifications to API/mobile users.
    | Service account JSON from Firebase Console → Project Settings → Service Accounts.
    */
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-credentials.json')),
    ],

    /*
    | Microsoft SSO (Azure AD / Entra) for ACDC staff - same app as CodeIgniter auth.
    | Used by mobile app: validate access_token or exchange code for token, then issue APM JWT.
    */
    'microsoft' => [
        // Canonical credentials: EXCHANGE_* from /staff/.env (shared/load-staff-root-env.php).
        'tenant_id' => env('EXCHANGE_TENANT_ID'),
        'client_id' => env('EXCHANGE_CLIENT_ID'),
        'client_secret' => env('EXCHANGE_CLIENT_SECRET'),
        'redirect_uri' => env('MICROSOFT_REDIRECT_URI', env('EXCHANGE_REDIRECT_URI')),
    ],

    /*
    | Africa CDC PRA public workplan API — used by Divisions → PRA integration tab.
    | APM-local (system_settings group `pra`); does not touch staff-portal Workplan / PRA.
    */
    'pra' => [
        'base_url' => rtrim((string) env(
            'PRA_API_URL',
            env('PRA_WORKPLAN_API_URL', 'https://pra.africacdc.org/api/public/workplan')
        ), '/'),
        'api_key' => (string) env('PRA_API_KEY', env('PRA_WORKPLAN_API_KEY', '')),
        'tiers' => (string) env('PRA_TIERS', env('PRA_WORKPLAN_TIERS', '3,4')),
        'fiscal_year' => env('PRA_FISCAL_YEAR'),
        'timeout' => (int) env('PRA_TIMEOUT', env('PRA_WORKPLAN_TIMEOUT', 60)),
        'division_aliases' => (string) env(
            'PRA_DIVISION_ALIASES',
            env('PRA_WORKPLAN_DIVISION_ALIASES', 'MIS:DHIS,CT:RD&CT,DIGITAL:DHIS,CARCC:CRCC,EARCC:ERCC,NARCC:NRCC,SARCC:SRCC,WARCC:WRCC,DIO:OIO,NPHI:PHIR,HEP:HEF,PHC:CPH,CHSISD:CHSHP,CLDS:LAB,HR:HRM,LEGAL:LADS,YOUTH:YD,ADMIN:DA,CPI:DCPI,FMG:DFIN')
        ),
    ],

];
