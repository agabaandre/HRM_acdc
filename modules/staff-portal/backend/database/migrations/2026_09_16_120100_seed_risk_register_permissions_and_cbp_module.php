<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Settings\Services\CbpModulesAdminService;
use Modules\Settings\Services\RiskRegisterPermissionSeeder;

return new class extends Migration
{
    public function up(): void
    {
        (new RiskRegisterPermissionSeeder)->seed();
        app(CbpModulesAdminService::class)->ensureCoreModules();
    }

    public function down(): void
    {
        // Keep seeded permissions and CBP module row.
    }
};
