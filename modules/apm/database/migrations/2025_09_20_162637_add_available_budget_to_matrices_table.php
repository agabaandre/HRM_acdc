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
            $table->decimal('available_budget', 15, 2)->nullable()->after('overall_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matrices', function (Blueprint $table) {
            $table->dropColumn('available_budget');
        });
    }
};
