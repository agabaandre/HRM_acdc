<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Settings\Services\CbpModulesAdminService;

return new class extends Migration
{
    public function up(): void
    {
        $modules = app(CbpModulesAdminService::class);
        $modules->ensureShowForAdminColumn();
        $modules->ensurePlaceholderModules();
    }

    public function down(): void
    {
        // Keep seeded module rows. The flag can stay; dropping it would hide the admin override.
    }
};
