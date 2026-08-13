<?php

use Staff\Shared\StaffStorage;

return [
    'base_url' => env('STAFF_PORTAL_BASE_URL', env('BASE_URL', 'http://localhost/staff/staff-portal/backend/')),

    /** CI3 staff app (PPA editor, prints) until fully ported to Laravel */
    'legacy_base_url' => env('STAFF_LEGACY_BASE_URL', 'http://localhost/staff/'),

    /**
     * Shared CI uploads root (staff photos, signatures, contracts).
     * Set STAFF_DATA_ROOT or STAFF_PORTAL_UPLOADS_ROOT in production — see docs/STORAGE.md.
     */
    'uploads_root' => StaffStorage::ciUploadsRoot(dirname(base_path(), 2)),

    'apm_base_url' => env(
        'APM_BASE_URL',
        rtrim((string) env('BASE_URL', 'http://localhost/staff/'), '/').'/apm'
    ),

    'legacy_schema_skip' => env('STAFF_LEGACY_SCHEMA_SKIP', true),

    'audit' => [
        'integrity_chain' => env('STAFF_AUDIT_INTEGRITY_CHAIN', true),
        'log_repository_access' => env('STAFF_AUDIT_LOG_REPOSITORY', true),
        'retention_days' => (int) env('STAFF_AUDIT_RETENTION_DAYS', 365),
        'revert_tables' => [
            'user' => [
                'pk' => 'user_id',
                'columns' => ['name', 'role', 'status', 'allow_email_login'],
            ],
        ],
    ],

    'sso' => [
        'token_ttl' => (int) env('STAFF_SSO_TOKEN_TTL', 7200),
    ],

    /** Vue SPA (same pattern as helpdesk/frontend). Laravel root = helpdesk/backend equivalent. */
    'spa_enabled' => env('STAFF_PORTAL_SPA_ENABLED', false),
    'spa_url' => env('STAFF_PORTAL_SPA_URL', '/staff/staff-portal/'),

    'sanctum' => [
        'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'cbp_staff_'),
    ],

    /*
    | Queue table names — never use `jobs` (CI3 staff positions table).
    */
    'queue' => [
        'jobs_table' => env('DB_QUEUE_TABLE', 'sp_queue_jobs'),
        'batches_table' => env('DB_QUEUE_BATCHES_TABLE', 'sp_job_batches'),
        'failed_table' => env('DB_QUEUE_FAILED_TABLE', 'sp_failed_jobs'),
    ],

    /*
    | Reference / dropdown cache (Redis via CACHE_STORE) — same idea as helpdesk
    | HELPDESK_REFERENCE_CACHE_TTL + TicketReadCache.
    */
    'reference_data_cache_ttl' => (int) env('STAFF_PORTAL_REFERENCE_CACHE_TTL', 300),
    'read_cache_enabled' => filter_var(env('STAFF_PORTAL_READ_CACHE_ENABLED', true), FILTER_VALIDATE_BOOL),
    'read_cache_ttl' => (int) env('STAFF_PORTAL_READ_CACHE_TTL', 60),
    'read_cache_store' => env('STAFF_PORTAL_READ_CACHE_STORE'),
];
