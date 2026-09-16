<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Settings\Services\DivisionRiskFocalService;

return new class extends Migration
{
    public function up(): void
    {
        DivisionRiskFocalService::ensureColumnAndBackfill();
    }

    public function down(): void
    {
        // Keep column — dropping would lose OIO assignments.
    }
};
