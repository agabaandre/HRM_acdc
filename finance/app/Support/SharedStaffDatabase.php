<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Shared MySQL `staff` database: CI3 table names must not be overwritten by Laravel defaults.
 */
final class SharedStaffDatabase
{
    public static function hasLegacyUserTable(): bool
    {
        return Schema::hasTable('user');
    }

    public static function hasLegacyJobsTable(): bool
    {
        return Schema::hasTable('jobs') && Schema::hasColumn('jobs', 'job_id');
    }
}
