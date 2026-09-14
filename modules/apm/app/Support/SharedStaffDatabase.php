<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Shared MySQL `staff` database: CI3 table names must not be overwritten by Laravel defaults.
 */
final class SharedStaffDatabase
{
    /** CI3 login table (`user_id`, …) — not Laravel `users`. */
    public static function hasLegacyUserTable(): bool
    {
        return Schema::hasTable('user');
    }

    /** CI3 job positions (`job_id`, `job_name`) — not Laravel queue `jobs`. */
    public static function hasLegacyJobsTable(): bool
    {
        return Schema::hasTable('jobs') && Schema::hasColumn('jobs', 'job_id');
    }
}
