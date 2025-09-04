<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Logger Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration options for the Laravel Audit Logger
    | package. You can customize various aspects of the audit logging behavior.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Enable/Disable Audit Logging
    |--------------------------------------------------------------------------
    |
    | Set to false to completely disable audit logging across the application.
    | This can be useful for testing or maintenance scenarios.
    |
    */
    'enabled' => env('AUDIT_LOGGER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Auto Migration
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will automatically create audit tables for
    | models when they are first used. Set to false if you prefer to manage
    | migrations manually.
    |
    */
    'auto_migration' => env('AUDIT_LOGGER_AUTO_MIGRATION', true),

    /*
    |--------------------------------------------------------------------------
    | Queue Processing
    |--------------------------------------------------------------------------
    |
    | Enable queue processing for audit logs to improve performance in
    | high-traffic applications. When enabled, audit logs will be processed
    | asynchronously.
    |
    */
    'queue' => [
        'enabled' => env('AUDIT_LOGGER_QUEUE_ENABLED', false),
        'connection' => env('AUDIT_LOGGER_QUEUE_CONNECTION', 'default'),
        'queue' => env('AUDIT_LOGGER_QUEUE_NAME', 'audit'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Causer Resolution
    |--------------------------------------------------------------------------
    |
    | Configuration for resolving the user who performed the action.
    | The package will attempt to resolve the current user from the
    | authentication system.
    |
    */
    'causer' => [
        'enabled' => env('AUDIT_LOGGER_CAUSER_ENABLED', true),
        'guard' => env('AUDIT_LOGGER_CAUSER_GUARD', 'web'),
        'resolver' => \App\Services\CustomCauserResolver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Source Tracking
    |--------------------------------------------------------------------------
    |
    | Enable source tracking to record where the action originated from
    | (e.g., web request, console command, API call).
    |
    */
    'source_tracking' => [
        'enabled' => env('AUDIT_LOGGER_SOURCE_TRACKING', true),
        'include_route' => env('AUDIT_LOGGER_INCLUDE_ROUTE', true),
        'include_url' => env('AUDIT_LOGGER_INCLUDE_URL', true),
        'include_ip' => env('AUDIT_LOGGER_INCLUDE_IP', true),
        'include_user_agent' => env('AUDIT_LOGGER_INCLUDE_USER_AGENT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Management
    |--------------------------------------------------------------------------
    |
    | Configure which fields should be included or excluded from audit logs.
    | You can set global exclusions or model-specific exclusions.
    |
    */
    'fields' => [
        'exclude_globally' => [
            'password',
            'password_confirmation',
            'remember_token',
            'api_token',
            'secret',
            'private_key',
            'access_token',
            'refresh_token',
        ],
        'exclude_by_model' => [
            // 'App\Models\User' => ['password', 'remember_token'],
            // 'App\Models\Activity' => ['internal_notes'],
        ],
        'include_only' => [
            // 'App\Models\User' => ['name', 'email', 'status'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention Policy
    |--------------------------------------------------------------------------
    |
    | Configure how long audit logs should be retained. This helps with
    | compliance and storage management.
    |
    */
    'retention' => [
        'enabled' => env('AUDIT_LOGGER_RETENTION_ENABLED', false),
        'days' => env('LOGS_RETENTION_PERIOD', 365),
        'strategy' => env('AUDIT_LOGGER_RETENTION_STRATEGY', 'delete'), // 'delete' or 'anonymize'
        'batch_size' => env('AUDIT_LOGGER_RETENTION_BATCH_SIZE', 1000),
        'anonymize_after_days' => env('AUDIT_LOGGER_ANONYMIZE_AFTER_DAYS', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the database connection and table naming for audit logs.
    |
    */
    'database' => [
        'connection' => env('AUDIT_LOGGER_DB_CONNECTION', null),
        'table_prefix' => env('AUDIT_LOGGER_TABLE_PREFIX', 'audit_'),
        'table_suffix' => env('AUDIT_LOGGER_TABLE_SUFFIX', '_logs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which Eloquent events should trigger audit logs.
    |
    */
    'events' => [
        'created' => true,
        'updated' => true,
        'deleted' => true,
        'restored' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | Enable debug mode for troubleshooting. This will log additional
    | information about audit processing.
    |
    */
    'debug' => env('AUDIT_LOGGER_DEBUG', true),
];
