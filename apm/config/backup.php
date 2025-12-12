<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Backup Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic database backups with retention policies
    | and optional OneDrive integration
    |
    */

    // Backup Storage Path
    'storage_path' => env('BACKUP_STORAGE_PATH', storage_path('app/backups')),

    // Retention Policies
    'retention' => [
        // Keep daily backups for the last N days
        'daily_days' => env('BACKUP_DAILY_DAYS', 5),
        
        // Keep monthly backups for the last N months
        'monthly_months' => env('BACKUP_MONTHLY_MONTHS', 5),
    ],

    // OneDrive Integration
    'onedrive' => [
        'enabled' => env('BACKUP_ONEDRIVE_ENABLED', false),
        'folder_name' => env('BACKUP_ONEDRIVE_FOLDER', 'Database Backups'),
        'tenant_id' => env('EXCHANGE_TENANT_ID'),
        'client_id' => env('EXCHANGE_CLIENT_ID'),
        'client_secret' => env('EXCHANGE_CLIENT_SECRET'),
    ],

    // Database Configuration
    'database' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', ''),
        'username' => env('DB_USERNAME', ''),
        'password' => env('DB_PASSWORD', ''),
    ],

    // Backup Schedule
    'schedule' => [
        // Run daily backup at this time (24-hour format)
        'daily_time' => env('BACKUP_DAILY_TIME', '02:00'),
        
        // Run monthly backup on this day of month
        'monthly_day' => env('BACKUP_MONTHLY_DAY', 1),
    ],

    // Compression
    'compression' => [
        'enabled' => env('BACKUP_COMPRESSION_ENABLED', true),
        'format' => env('BACKUP_COMPRESSION_FORMAT', 'gzip'), // gzip, zip
    ],

    // Notification
    'notification' => [
        'enabled' => env('BACKUP_NOTIFICATION_ENABLED', true),
        'email' => env('BACKUP_NOTIFICATION_EMAIL', ''),
    ],
];

