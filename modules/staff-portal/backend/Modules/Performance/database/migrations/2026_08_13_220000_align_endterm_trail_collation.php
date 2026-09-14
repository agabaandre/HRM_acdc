<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ppa_approval_trail_end_term was created with utf8mb4_general_ci while
 * ppa_entries uses utf8mb4_0900_ai_ci — joins on entry_id then fail with
 * "Illegal mix of collations".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ppa_approval_trail_end_term')) {
            return;
        }

        // Match ppa_entries / other trail tables on MySQL 8.
        DB::statement(
            'ALTER TABLE `ppa_approval_trail_end_term` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci'
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('ppa_approval_trail_end_term')) {
            return;
        }

        DB::statement(
            'ALTER TABLE `ppa_approval_trail_end_term` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'
        );
    }
};
