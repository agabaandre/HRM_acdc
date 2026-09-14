<?php

return [

    /*
    |--------------------------------------------------------------------------
    | APM page read cache (Redis recommended in production)
    |--------------------------------------------------------------------------
    |
    | Version keys are bumped on document / approval writes so list and report
    | pages stay fresh without cache tags. Short TTL is a safety net.
    |
    */

    'page_cache_enabled' => env('APM_PAGE_CACHE_ENABLED', true),

    'page_cache_store' => env('APM_PAGE_CACHE_STORE', env('CACHE_STORE')),

    'page_cache_ttl' => (int) env('APM_PAGE_CACHE_TTL', 120),

    'page_cache_ttl_by_scope' => [
        'approver_dashboard' => (int) env('APM_PAGE_CACHE_TTL_APPROVER_DASHBOARD', 120),
        'reports' => (int) env('APM_PAGE_CACHE_TTL_REPORTS', 300),
        'budget_execution' => (int) env('APM_PAGE_CACHE_TTL_BUDGET_EXECUTION', 300),
        'weekly_briefing' => (int) env('APM_PAGE_CACHE_TTL_WEEKLY_BRIEFING', 120),
        'matrices' => (int) env('APM_PAGE_CACHE_TTL_MATRICES', 120),
        'activities' => (int) env('APM_PAGE_CACHE_TTL_ACTIVITIES', 120),
        'change_requests' => (int) env('APM_PAGE_CACHE_TTL_CHANGE_REQUESTS', 120),
        'lookups' => (int) env('APM_PAGE_CACHE_TTL_LOOKUPS', 600),
    ],

];
