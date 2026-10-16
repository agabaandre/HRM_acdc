<?php

return [
    'base_url' => rtrim((string) env('APM_BASE_URL', ''), '/'),
    'api_prefix' => env('APM_API_PREFIX', '/api/apm/v1'),
    'email' => env('APM_API_EMAIL'),
    'password' => env('APM_API_PASSWORD'),
    'timeout' => (int) env('APM_API_TIMEOUT', 60),
];
