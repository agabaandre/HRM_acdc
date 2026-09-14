<?php

$base = rtrim((string) env('BASE_URL', 'https://cbp.africacdc.org/staff/'), '/');

return [
    /*
    | Absolute URL for Africa CDC logo in HTML emails (must be publicly reachable).
    | Set APP_LOGO_URL or STAFF_MAIL_LOGO_URL in staff root / apm .env on production.
    */
    'mail_logo_url' => env('APP_LOGO_URL')
        ?: env('STAFF_MAIL_LOGO_URL')
        ?: ($base !== '' ? $base.'/assets/images/AU_CDC_Logo-800.png' : 'https://cbp.africacdc.org/staff/assets/images/AU_CDC_Logo-800.png'),
];
