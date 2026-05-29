<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Staff portal theme assets (shared with APM)
    |--------------------------------------------------------------------------
    |
    | Bootstrap / app.css used by the APM shell. Symlink public/assets to
    | ../../../apm/public/assets or set an absolute URL.
    |
    */
    'assets_base_url' => rtrim((string) env('FINANCE_ASSETS_BASE_URL', ''), '/'),

    'sso_permission_id' => (int) env('FINANCE_SSO_PERMISSION_ID', 92),

];
