<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Staff portal (CodeIgniter) base URL — used for redirects & deep links.
    |--------------------------------------------------------------------------
    */
    'staff_portal_url' => env('HELPDESK_STAFF_PORTAL_URL', 'http://localhost/staff'),

    /*
    |--------------------------------------------------------------------------
    | APM (Laravel) base URL — system colours / branding API consumer target.
    |--------------------------------------------------------------------------
    */
    'apm_base_url' => env('HELPDESK_APM_BASE_URL', 'http://localhost/staff/apm'),

    /*
    |--------------------------------------------------------------------------
    | Vue SPA URL (dev or production) for CORS and email links.
    |--------------------------------------------------------------------------
    */
    'frontend_url' => env('HELPDESK_FRONTEND_URL', 'http://localhost:5174'),

    /*
    |--------------------------------------------------------------------------
    | Staff portrait files (shared with CI / APM)
    |--------------------------------------------------------------------------
    | Same tree as APM `config('staff_portal.uploads_root')`: files live under
    | `{staff_uploads_root}/staff/{basename}`. Used by signed /api/v1/avatar/{user}.
    */
    'staff_uploads_root' => env(
        'HELPDESK_STAFF_UPLOADS_ROOT',
        env('STAFF_PORTAL_UPLOADS_ROOT', dirname(dirname(base_path())).DIRECTORY_SEPARATOR.'uploads')
    ),

    /*
    |--------------------------------------------------------------------------
    | Public API base for URLs loaded by the browser without Bearer auth (<img>)
    |--------------------------------------------------------------------------
    | Leave empty to emit same-origin paths like `/api/v1/avatar/...` (works with
    | Vite dev proxy). Set when the SPA is on a different host than the API.
    */
    'api_public_url' => env('HELPDESK_API_PUBLIC_URL', ''),

    'avatar_signed_ttl_seconds' => (int) env('HELPDESK_AVATAR_SIGNED_TTL', 604800),

    /** Optional; defaults to APP_KEY. */
    'avatar_signing_secret' => env('HELPDESK_AVATAR_SIGNING_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | HMAC secret for POST /api/v1/auth/exchange (CI/APM server-side only).
    | Message: "{staff_id}|{ts}|{lowercase(email)}"
    |--------------------------------------------------------------------------
    */
    'bridge_secret' => env('HELPDESK_BRIDGE_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Optional: JWT / session parity (APM & Finance)
    |--------------------------------------------------------------------------
    | JWT_SECRET: reserve for verifying tokens signed by other staff services.
    | SESSION_SECRET: parity with Node Finance; use if sharing signed payloads.
    */
    'jwt_secret' => env('JWT_SECRET'),
    'jwt_ttl_minutes' => (int) env('JWT_TTL', 1440),
    'session_secret' => env('SESSION_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Microsoft 365 / Exchange (Graph) — same names as APM (`apm/.env.example`)
    |--------------------------------------------------------------------------
    | Used when you add Graph-based mail or server-side OAuth helpers. Mail
    | transport order is controlled by MAIL_MAILER + MAIL_FAILOVER_MAILERS.
    */
    'exchange_tenant_id' => env('EXCHANGE_TENANT_ID'),
    'exchange_client_id' => env('EXCHANGE_CLIENT_ID'),
    'exchange_client_secret' => env('EXCHANGE_CLIENT_SECRET'),
    'exchange_redirect_uri' => env('EXCHANGE_REDIRECT_URI'),
    'exchange_scope' => env('EXCHANGE_SCOPE', 'https://graph.microsoft.com/.default'),
    'exchange_auth_method' => env('EXCHANGE_AUTH_METHOD', 'client_credentials'),

    /*
    |--------------------------------------------------------------------------
    | Staff portal SSO (JWT from home/index, same secret as CI / Finance)
    |--------------------------------------------------------------------------
    | Comma-separated permission codes from the Staff session payload. User
    | must have at least one: 85 (APM), 92 (Finance), 93 (Helpdesk-only card).
    */
    'sso_permission_codes' => env('HELPDESK_SSO_PERMISSION_CODES', '85,92,93'),

    /*
    |--------------------------------------------------------------------------
    | Map Staff portal user group IDs to Helpdesk admin on SSO
    |--------------------------------------------------------------------------
    | When the JWT has no helpdesk_role claim, users in these Staff `role`
    | (user_groups.id) values become Helpdesk admins so they can open Settings.
    | Default 10 matches the Staff portal "admin" group used across CI modules.
    */
    'sso_staff_role_ids_admin' => env('HELPDESK_SSO_STAFF_ROLE_IDS_ADMIN', '10'),

    /*
    |--------------------------------------------------------------------------
    | Default Helpdesk agent divisions (Staff portal division_id)
    |--------------------------------------------------------------------------
    | Comma-separated IDs (default 21). SSO maps matching users to role "agent"
    | unless they are portal admins (see sso_staff_role_ids_admin).
    */
    'default_agent_division_ids' => env('HELPDESK_DEFAULT_AGENT_DIVISION_IDS', '21'),

    /*
    |--------------------------------------------------------------------------
    | Staff portal Share API (same contract as APM `config('services.staff_api')`)
    |--------------------------------------------------------------------------
    | Used for agent requester picker: divisions, directorates, staff list.
    | URL pattern matches APM sync commands: {base_url}{endpoint}/{token} with HTTP Basic Auth.
    |
    | Env resolution mirrors APM `config('services.staff_api')`: copy `BASE_URL`, `STAFF_API_USERNAME`,
    | `STAFF_API_PASSWORD`, and optional `STAFF_API_TOKEN` from `apm/.env` into Helpdesk `.env`.
    | `HELPDESK_STAFF_API_*` overrides take precedence when set.
    */
    'staff_api' => [
        'base_url' => env(
            'HELPDESK_STAFF_API_BASE_URL',
            env('STAFF_API_BASE_URL', env('BASE_URL', env('HELPDESK_STAFF_PORTAL_URL', 'http://localhost/staff')))
        ),
        'token' => env('HELPDESK_STAFF_API_TOKEN', env('STAFF_API_TOKEN', 'YWZyY2FjZGNzdGFmZnRyYWNrZXI')),
        'username' => env('HELPDESK_STAFF_API_USERNAME', env('STAFF_API_USERNAME')),
        'password' => env('HELPDESK_STAFF_API_PASSWORD', env('STAFF_API_PASSWORD')),
        'endpoints' => [
            'staff' => env('HELPDESK_STAFF_API_ENDPOINT_STAFF', '/share/get_current_staff'),
            'divisions' => env('HELPDESK_STAFF_API_ENDPOINT_DIVISIONS', '/share/divisions'),
            'directorates' => env('HELPDESK_STAFF_API_ENDPOINT_DIRECTORATES', '/share/directorates'),
            'agents_in_divisions' => env('HELPDESK_STAFF_API_ENDPOINT_AGENTS_PREVIEW', '/share/helpdesk_agents_in_divisions'),
            'mark_agents' => env('HELPDESK_STAFF_API_ENDPOINT_MARK_AGENTS', '/share/mark_helpdesk_agents'),
        ],
        'staff_fetch_limit' => (int) env('HELPDESK_STAFF_API_STAFF_LIMIT', 5000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reference data cache (seconds) — mirrors light caching used around APM lookups
    |--------------------------------------------------------------------------
    */
    'reference_data_cache_ttl' => (int) env('HELPDESK_REFERENCE_CACHE_TTL', 300),
];
