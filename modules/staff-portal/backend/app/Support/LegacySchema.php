<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class LegacySchema
{
    /** @var array<string, bool> */
    private static array $tableExists = [];

    /** @var array<string, bool> */
    private static array $columnExists = [];

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

    public static function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;
        if (array_key_exists($key, self::$columnExists)) {
            return self::$columnExists[$key];
        }

        try {
            $cached = Cache::remember(
                'legacy_schema_has_col:'.$key,
                now()->addHours(6),
                static fn (): bool => Schema::hasColumn($table, $column),
            );
        } catch (Throwable) {
            $cached = Schema::hasColumn($table, $column);
        }

        return self::$columnExists[$key] = (bool) $cached;
    }
}
