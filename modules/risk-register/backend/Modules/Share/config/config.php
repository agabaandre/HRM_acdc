<?php

return [
    'name' => 'Share',

    /**
     * Legacy path/query token APM appends: /share/get_current_staff/{token}
     * Same value as APM STAFF_API_TOKEN.
     */
    // Prefer ?: so blank STAFF_API_TOKEN= still uses the Share default (same as APM / Helpdesk).
    'api_token' => env('STAFF_API_TOKEN') ?: env('SHARE_API_TOKEN') ?: 'YWZyY2FjZGNzdGFmZnRyYWNrZXI',

    /** JWT lifetime for POST /share/token (seconds). */
    'jwt_ttl' => (int) env('SHARE_JWT_TTL', 3600),

    /** Audience claim for Share API JWTs. */
    'jwt_audience' => env('SHARE_JWT_AUDIENCE', 'share-api'),
];
