<?php

return [
    'base_url' => env('STAFF_PORTAL_BASE_URL', env('BASE_URL', 'http://localhost/staff/staff-portal/')),

    /** CI3 staff app (PPA editor, prints) until fully ported to Laravel */
    'legacy_base_url' => env('STAFF_LEGACY_BASE_URL', 'http://localhost/staff/'),

    'apm_base_url' => env(
        'APM_BASE_URL',
        rtrim((string) env('BASE_URL', 'http://localhost/staff/'), '/').'/apm'
    ),

    'legacy_schema_skip' => env('STAFF_LEGACY_SCHEMA_SKIP', true),

    'audit' => [
        'integrity_chain' => env('STAFF_AUDIT_INTEGRITY_CHAIN', true),
        'log_repository_access' => env('STAFF_AUDIT_LOG_REPOSITORY', true),
        'retention_days' => (int) env('STAFF_AUDIT_RETENTION_DAYS', 365),
    ],

    'sso' => [
        'token_ttl' => (int) env('STAFF_SSO_TOKEN_TTL', 7200),
    ],

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
];
