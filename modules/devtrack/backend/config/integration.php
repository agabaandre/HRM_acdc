<?php

return [
    // Dedicated signing key — never fall back to APP_KEY.
    'jwt_secret' => env('JWT_SECRET'),

    // Token lifetime in minutes (default 1 hour).
    'jwt_ttl' => (int) env('JWT_TTL', 60),
];
