<?php

use Database\Seeders\HelpdeskSupportGroupSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('helpdesk_support_groups')) {
            return;
        }

        if (\App\Models\HelpdeskSupportGroup::query()->exists()) {
            return;
        }

        (new HelpdeskSupportGroupSeeder)->run();
    }

    public function down(): void
    {
        // Seeded rows are left in place on rollback.
    }
};
