<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CodeIgniter Session Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration values to integrate with an existing
    | CodeIgniter application's session.
    |
    */

    // Database connection name from config/database.php to use for CI sessions
    'database_connection' => env('CI_DB_CONNECTION', 'mysql'),

    // Session table name (specific to your CodeIgniter app)
    'session_table' => env('CI_SESSION_TABLE', 'access_sessions'),

    // Session cookie name (specific to your CodeIgniter app)
    'session_cookie_name' => env('CI_SESSION_COOKIE_NAME', 'africacdc_attendance_session'),

    // Database columns for session storage
    'session_id_column' => env('CI_SESSION_ID_COLUMN', 'id'),
    'session_data_column' => env('CI_SESSION_DATA_COLUMN', 'data'),
    'session_timestamp_column' => env('CI_SESSION_TIMESTAMP_COLUMN', 'timestamp'),

    // Session expiration time in seconds
    'session_expiration' => env('CI_SESSION_EXPIRATION', 7200),

    // Whether to match IP when retrieving sessions
    'match_ip' => env('CI_MATCH_IP', false),

    // Path where the CodeIgniter session cookie is valid
    'cookie_path' => env('CI_COOKIE_PATH', '/'),

    // Domain where the CodeIgniter session cookie is valid
    'cookie_domain' => env('CI_COOKIE_DOMAIN', null),
    
    /*
    |--------------------------------------------------------------------------
    | Valet-specific Options
    |--------------------------------------------------------------------------
    |
    | These options help when both apps are running in Laravel Valet
    |
    */
    
    // The domain of your CodeIgniter application in Valet (e.g., 'africacdc.test')
    'ci_valet_domain' => env('CI_VALET_DOMAIN', null),
    
    // Whether to allow direct session ID input for testing (not recommended for production)
    'allow_session_id_param' => env('CI_ALLOW_SESSION_ID_PARAM', false),
];
