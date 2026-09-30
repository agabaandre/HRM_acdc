<?php

return [
        // Canonical credentials: EXCHANGE_* from /staff/.env (shared/load-staff-root-env.php).
    'tenant_id' => env('EXCHANGE_TENANT_ID'),
    'client_id' => env('EXCHANGE_CLIENT_ID'),
    'client_secret' => env('EXCHANGE_CLIENT_SECRET'),
    'redirect_uri' => env('EXCHANGE_REDIRECT_URI', env('MICROSOFT_REDIRECT_URI')),
    'scope' => env('EXCHANGE_SCOPE', 'https://graph.microsoft.com/.default'),
    'auth_method' => env('EXCHANGE_AUTH_METHOD', 'client_credentials'),
    'from_email' => env('MAIL_FROM_ADDRESS'),
    'from_name' => env('MAIL_FROM_NAME', 'Africa CDC HelpDesk'),
];
