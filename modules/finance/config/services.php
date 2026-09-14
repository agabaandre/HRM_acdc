<?php

return [

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
        'base_url' => env('BASE_URL', 'http://localhost/staff/'),
        'token' => env('STAFF_API_TOKEN', 'YWZyY2FjZGNzdGFmZnRyYWNrZXI'),
        'username' => env('STAFF_API_USERNAME'),
        'password' => env('STAFF_API_PASSWORD'),
        'endpoints' => [
            'cbp_modules' => '/share/cbp_modules',
        ],
    ],

];
