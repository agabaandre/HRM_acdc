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
        Schema::table('matrices', function (Blueprint $table) {
            // Add unique constraint for division, year, and quarter combination
            $table->unique(['division_id', 'year', 'quarter'], 'matrices_division_year_quarter_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matrices', function (Blueprint $table) {
            $table->dropUnique('matrices_division_year_quarter_unique');
        });
    }
};
