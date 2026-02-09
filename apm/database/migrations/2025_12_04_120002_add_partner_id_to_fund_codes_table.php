<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('fund_codes', 'partner_id')) {
            Schema::table('fund_codes', function (Blueprint $table) {
                $table->unsignedBigInteger('partner_id')->nullable()->after('funder_id');
            });
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
