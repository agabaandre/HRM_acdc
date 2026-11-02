<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Settings\Services\CbpModulesAdminService;
use Modules\Settings\Services\DevTrackPermissionSeeder;

return new class extends Migration
{
    public function up(): void
    {
        (new DevTrackPermissionSeeder)->seed();
        app(CbpModulesAdminService::class)->ensureCoreModules();
    }

    public function down(): void
    {
        // Keep seeded permissions and CBP module rows.
    }
};
