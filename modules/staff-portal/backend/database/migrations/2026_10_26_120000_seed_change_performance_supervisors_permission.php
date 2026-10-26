<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Performance\Services\PerformancePermissionSeeder;

/**
 * Seed change_performance_supervisors (127) for System Administrator and HR roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new PerformancePermissionSeeder)->seed();
    }

    public function down(): void
    {
        // Keep seeded permission — HR/Admin may already rely on it.
    }
};
