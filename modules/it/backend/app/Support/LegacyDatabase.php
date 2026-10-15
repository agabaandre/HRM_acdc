<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Shared staff DB: CI3 table names must not be overwritten by Laravel defaults.
 */
final class LegacyDatabase
{
    public static function usesSharedStaffDatabase(): bool
    {
        return (bool) env('STAFF_LEGACY_SCHEMA_SKIP', true);
    }

    /** CI3 job positions table (job_id, job_name) — not Laravel queue jobs. */
    public static function hasLegacyJobsTable(): bool
    {
        return Schema::hasTable('jobs') && Schema::hasColumn('jobs', 'job_id');
    }
}
