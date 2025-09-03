<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Log Retention Period
    |--------------------------------------------------------------------------
    |
    | This value determines how many days audit logs should be retained.
    | Logs older than this period will be automatically cleaned up.
    | Default is 60 days (2 months).
    |
    */
    'retention_days' => env('AUDIT_LOG_RETENTION_DAYS', 60),

    /*
    |--------------------------------------------------------------------------
    | Audit Log Enabled
    |--------------------------------------------------------------------------
    |
    | This value determines if audit logging is enabled.
    | Set to false to disable audit logging completely.
    |
    */
    'enabled' => env('AUDIT_LOG_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Audit Log Middleware
    |--------------------------------------------------------------------------
    |
    | This value determines which middleware group the audit log middleware
    | should be applied to.
    |
    */
    'middleware_group' => env('AUDIT_LOG_MIDDLEWARE_GROUP', 'web'),

    /*
    |--------------------------------------------------------------------------
    | Excluded Routes
    |--------------------------------------------------------------------------
    |
    | These routes will be excluded from audit logging.
    |
    */
    'excluded_routes' => [
        'audit-logs.*',
        'telescope.*',
        'horizon.*',
        'log-viewer.*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Methods
    |--------------------------------------------------------------------------
    |
    | These HTTP methods will be excluded from audit logging.
    |
    */
    'excluded_methods' => [
        'GET',
        'HEAD',
        'OPTIONS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Fields
    |--------------------------------------------------------------------------
    |
    | These fields will be excluded from audit log data to protect sensitive information.
    |
    */
    'sensitive_fields' => [
        'password',
        'password_confirmation',
        'token',
        '_token',
        'api_token',
        'secret',
        'key',
        'private_key',
        'credit_card',
        'ssn',
        'social_security_number',
    ],
];
