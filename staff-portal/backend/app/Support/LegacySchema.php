<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class LegacySchema
{
    /** @var array<string, bool> */
    private static array $tableExists = [];

    public static function has(string $table): bool
    {
        if (array_key_exists($table, self::$tableExists)) {
            return self::$tableExists[$table];
        }

        // Schema::hasTable hits information_schema (~50–100ms); cache across requests.
        // Database cache is unavailable until create_cache_table migrates — fall back.
        try {
            $cached = Cache::remember(
                'legacy_schema_has:'.$table,
                now()->addHours(6),
                static fn (): bool => Schema::hasTable($table),
            );
        } catch (Throwable) {
            $cached = Schema::hasTable($table);
        }

        return self::$tableExists[$table] = (bool) $cached;
    }
}
