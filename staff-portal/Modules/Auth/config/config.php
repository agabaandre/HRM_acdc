<?php

return [
    'name' => 'Auth',

    'microsoft' => [
        'scopes' => env('MICROSOFT_SCOPES', 'openid profile email offline_access User.Read'),
    ],

    'allow_alternative_login' => filter_var(
        env('ALLOW_ALTERNATIVE_LOGIN', true),
        FILTER_VALIDATE_BOOLEAN,
        FILTER_NULL_ON_FAILURE
    ) ?? true,
];
