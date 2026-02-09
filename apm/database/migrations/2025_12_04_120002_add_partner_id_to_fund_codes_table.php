<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * partner_id is optional (NULL) and only applies to extramural fund codes.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('fund_codes', 'partner_id')) {
            Schema::table('fund_codes', function (Blueprint $table) {
                $table->unsignedBigInteger('partner_id')->nullable()->default(null)->after('funder_id');
            });
        } else {
            // Ensure existing column is nullable with default NULL (only applies to extramural codes)
            DB::statement('ALTER TABLE fund_codes MODIFY partner_id BIGINT UNSIGNED NULL DEFAULT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('fund_codes', 'partner_id')) {
            Schema::table('fund_codes', function (Blueprint $table) {
                $table->dropColumn('partner_id');
            });
        }
    }
};
