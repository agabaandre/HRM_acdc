<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Settings\Services\CbpModulesAdminService;

return new class extends Migration
{
    public function up(): void
    {
        $modules = app(CbpModulesAdminService::class);
        $modules->ensurePrimaryTabColumn();
        $modules->backfillPrimaryTabs();
    }

    public function down(): void
    {
        // Keep the column and saved tab choices.
    }
};
