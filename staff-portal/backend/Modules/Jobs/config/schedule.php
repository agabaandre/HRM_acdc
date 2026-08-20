<?php

return [
    'send_instant_mails' => true,
    'send_mails_interval_minutes' => 15,
    'performance_notifications' => ['hour' => 7, 'minute' => 0],
    'performance_approval_reminder' => ['hour' => 10, 'minute' => 0],
    'mark_due_contracts' => ['hour' => 23, 'minute' => 0],
    'audit_extended_contracts' => ['hour' => 23, 'minute' => 5],
    'staff_birthday' => ['hour' => 3, 'minute' => 0],
    // Off by default — enable via Settings → Staff jobs (shared JSON).
    'staff_profile_completion_reminder' => false,
    'manage_accounts_hourly_minute' => 0,
    'user_logs_prune_get_access' => ['hour' => 0, 'minute' => 0, 'weekday' => 2],
    'sync_pra_workplan' => ['hour' => 0, 'minute' => 5],

    /** Public logo used in HTML emails */
    'mail_logo_url' => env('JOBS_MAIL_LOGO_URL', 'https://cbp.africacdc.org/staff/assets/images/AU_CDC_Logo-800.png'),

    /** Base URL for deep links in reminder emails (SPA or CI performance routes). */
    'portal_base_url' => rtrim((string) env('JOBS_PORTAL_BASE_URL', env('STAFF_PORTAL_SPA_URL', env('APP_URL'))), '/').'/',

    /** System inbox appended to many reminder recipients (semicolon-separated OK). */
    'system_email' => env('JOBS_SYSTEM_EMAIL', env('MAIL_FROM_ADDRESS', 'registry@africacdc.org')),

    /** Extra recipients on expired-contract notices. */
    'contracts_status_copied_emails' => env('JOBS_CONTRACTS_COPIED_EMAILS', ''),
];
