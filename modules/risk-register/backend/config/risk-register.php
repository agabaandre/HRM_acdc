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
            'staff' => env('STAFF_API_ENDPOINT_STAFF', '/share/get_current_staff'),
            'cbp_modules' => env('STAFF_API_ENDPOINT_CBP_MODULES', '/share/cbp_modules'),
            'branding' => env('STAFF_API_ENDPOINT_BRANDING', '/share/branding'),
        ],
    ],
    'excel_default_path' => env(
        'RISK_EXCEL_PATH',
        dirname(__DIR__, 2).'/Copy of Africa CDC Risk Register Tracker 2026 Categorised.xlsx'
    ),
];
