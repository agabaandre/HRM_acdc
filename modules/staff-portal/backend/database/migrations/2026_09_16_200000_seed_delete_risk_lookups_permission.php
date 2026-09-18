<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Settings\Services\RiskRegisterPermissionSeeder;

/**
 * Seed delete_risk_lookups (122) — Admin group only.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RiskRegisterPermissionSeeder)->seed();
    }

    public function down(): void
    {
        // Keep permission; assignment is managed in Staff Portal.
    }
};
