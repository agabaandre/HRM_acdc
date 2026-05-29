<?php

use Database\Seeders\HelpdeskCategorySeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('helpdesk_categories')->exists() && DB::table('helpdesk_categories')->count() > 0) {
            return;
        }

        (new HelpdeskCategorySeeder)->run();
    }

    public function down(): void
    {
        // Reference data — keep categories on rollback.
    }
};
