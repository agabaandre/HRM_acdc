<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('duty_stations', function (Blueprint $table) {
            $table->boolean('status')->default(true)->after('is_active');
        });

        // Copy values from is_active to status to ensure consistency
        DB::statement('UPDATE duty_stations SET status = is_active');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('duty_stations', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
