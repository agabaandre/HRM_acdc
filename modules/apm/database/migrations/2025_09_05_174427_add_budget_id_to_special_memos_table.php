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
        Schema::table('special_memos', function (Blueprint $table) {
            $table->json('budget_id')->nullable()->after('fund_type_id')->comment('Selected budget code IDs from fund_codes table');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('special_memos', function (Blueprint $table) {
            $table->dropColumn('budget_id');
        });
    }
};
