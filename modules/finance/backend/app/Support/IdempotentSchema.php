<?php

namespace App\Support;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create tables only when missing (staff-DB copy / partial migrate safe).
 */
final class IdempotentSchema
{
    /**
     * @param  Closure(Blueprint): void  $callback
     */
    public static function create(string $table, Closure $callback, ?string $connection = null): void
    {
        if (self::tableExists($table, $connection)) {
            return;
        }

        try {
            self::schema($connection)->create($table, $callback);
        } catch (QueryException $e) {
            if (self::isTableAlreadyExists($e)) {
                return;
            }

            throw $e;
        }
    }

    public static function tableExists(string $table, ?string $connection = null): bool
    {
        try {
            if (self::schema($connection)->hasTable($table)) {
                return true;
            }
        } catch (\Throwable) {
            // Fall through to information_schema.
        }

        try {
            $db = self::db($connection);
            $database = $db->getDatabaseName();
            $row = $db->selectOne(
                'select 1 as ok from information_schema.tables
                 where table_schema = ? and table_name = ? limit 1',
                [$database, $table]
            );

            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function isTableAlreadyExists(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        $message = $e->getMessage();

        return $sqlState === '42S01'
            || $driverCode === 1050
            || str_contains($message, 'already exists');
    }

    public static function dropIfExists(string $table, ?string $connection = null): void
    {
        self::schema($connection)->dropIfExists($table);
    }

    /** @return \Illuminate\Database\Schema\Builder */
    private static function schema(?string $connection)
    {
        if ($connection !== null && $connection !== '') {
            return Schema::connection($connection);
        }

        return Schema::connection((string) config('database.default'));
    }

    /** @return \Illuminate\Database\Connection */
    private static function db(?string $connection)
    {
        if ($connection !== null && $connection !== '') {
            return DB::connection($connection);
        }

        return DB::connection();
    }
}
