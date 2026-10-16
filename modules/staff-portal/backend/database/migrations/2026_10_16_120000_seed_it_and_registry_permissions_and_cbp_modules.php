<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Settings\Services\CbpModulesAdminService;
use Modules\Settings\Services\ItModulePermissionSeeder;
use Modules\Settings\Services\RegistryModulePermissionSeeder;

return new class extends Migration
{
    public function up(): void
    {
        (new ItModulePermissionSeeder)->seed();
        (new RegistryModulePermissionSeeder)->seed();
        app(CbpModulesAdminService::class)->ensureCoreModules();
    }

    public function down(): void
    {
        // Keep seeded permissions and CBP module rows.
    }
};
