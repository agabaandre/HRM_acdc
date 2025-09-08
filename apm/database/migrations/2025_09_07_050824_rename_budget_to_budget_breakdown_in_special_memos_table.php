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
            // Rename budget column to budget_breakdown
            $table->renameColumn('budget', 'budget_breakdown');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('special_memos', function (Blueprint $table) {
            // Rename budget_breakdown column back to budget
            $table->renameColumn('budget_breakdown', 'budget');
        });
    }
};