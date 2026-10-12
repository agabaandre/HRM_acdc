<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ActivityReportPermission
{
    public const NAME = 'apm_reporting_finance_registry';

    public static function id(): ?int
    {
        $connection = self::permissionsConnection();
        if ($connection === false) {
            return null;
        }

        try {
            $db = $connection === null ? DB::connection() : DB::connection($connection);
            $id = $db->table('permissions')->where('name', self::NAME)->value('id');

            return $id !== null ? (int) $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function userCanProcess(?array $permissionIds = null): bool
    {
        $permId = self::id();
        if ($permId === null) {
            return false;
        }

        $ids = $permissionIds;
        if ($ids === null) {
            $ids = function_exists('user_session')
                ? (user_session('permissions', []) ?? [])
                : [];
        }

        return in_array($permId, array_map('intval', (array) $ids), true);
    }

    /**
     * @return null|string|false null = default; string = named; false = unavailable
     */
    private static function permissionsConnection(): null|string|false
    {
        try {
            if (Schema::hasTable('permissions')) {
                return null;
            }
        } catch (\Throwable) {
            // fall through
        }

        $staffDb = trim((string) config('database.connections.staff_app.database', ''));
        if ($staffDb === '') {
            return false;
        }

        try {
            if (Schema::connection('staff_app')->hasTable('permissions')) {
                return 'staff_app';
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
