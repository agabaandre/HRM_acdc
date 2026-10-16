<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Portal data table defaults (x-core::data-table, config key core.portal-table)
    |--------------------------------------------------------------------------
    |
    | Use InteractsWithPortalTable on Livewire list components and
    | PortalTable::paginateDistinct() for server-side pagination.
    |
    */
    'default_per_page' => 20,
    'max_per_page' => 100,
    'search_debounce_ms' => 350,
];
