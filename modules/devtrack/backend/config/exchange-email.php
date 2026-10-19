<?php

return [
    // Intentionally empty — Exchange credentials live encrypted on email_providers.
    // DynamicMailConfigService::applyProvider() fills this at send time from the DB.
    'tenant_id' => null,
    'client_id' => null,
    'client_secret' => null,
    'redirect_uri' => null,
    'scope' => 'https://graph.microsoft.com/.default',
    'auth_method' => 'client_credentials',
    'from_email' => null,
    'from_name' => null,
];
