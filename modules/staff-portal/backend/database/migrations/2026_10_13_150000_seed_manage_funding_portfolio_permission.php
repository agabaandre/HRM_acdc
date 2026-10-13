<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Settings\Services\FundingPortfolioPermissionSeeder;

return new class extends Migration
{
    public function up(): void
    {
        (new FundingPortfolioPermissionSeeder)->seed();
    }

    public function down(): void
    {
        // Keep seeded permission — finance officers may already rely on it.
    }
};
