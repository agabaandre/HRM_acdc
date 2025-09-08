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
        Schema::table('fund_code_transactions', function (Blueprint $table) {
            // Make activity_budget_id nullable
            $table->unsignedBigInteger('activity_budget_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fund_code_transactions', function (Blueprint $table) {
            // Revert activity_budget_id to not nullable
            // Note: This might fail if there are NULL values in activity_budget_id
            $table->unsignedBigInteger('activity_budget_id')->nullable(false)->change();
        });
    }
};
