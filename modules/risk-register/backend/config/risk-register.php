<?php

return [
    'staff_api' => [
        'base_url' => env('STAFF_API_BASE_URL', env('RISK_STAFF_API_BASE_URL', '')),
        'token' => env('STAFF_API_TOKEN', env('RISK_STAFF_API_TOKEN', '')),
        'username' => env('STAFF_API_USERNAME', env('RISK_STAFF_API_USERNAME', '')),
        'password' => env('STAFF_API_PASSWORD', env('RISK_STAFF_API_PASSWORD', '')),
        'endpoints' => [
            'divisions' => env('STAFF_API_ENDPOINT_DIVISIONS', '/share/divisions'),
            'directorates' => env('STAFF_API_ENDPOINT_DIRECTORATES', '/share/directorates'),
        ],
    ],
    'excel_default_path' => env(
        'RISK_EXCEL_PATH',
        dirname(__DIR__, 2).'/Copy of Africa CDC Risk Register Tracker 2026 Categorised.xlsx'
    ),
];
