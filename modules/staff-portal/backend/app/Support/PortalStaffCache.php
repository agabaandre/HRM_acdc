<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Bust Redis / read caches that power staff directory, history exports, and HR dashboard.
 *
 * Call after staff create/edit, contract create/edit, or other mutations that change
 * what those reports show.
 */
final class PortalStaffCache
{
    public const FILTER_OPTIONS_KEY = 'staff_portal:staff_filter_options_v1';

    public static function bust(): void
    {
        // Directory list + filter counts (versioned) and dashboard KPI/maps share these scopes.
        PortalReadCache::bust(['staff', 'dashboard']);
        PortalReferenceCache::bustFormLookups();
        Cache::forget(self::FILTER_OPTIONS_KEY);
    }
}
