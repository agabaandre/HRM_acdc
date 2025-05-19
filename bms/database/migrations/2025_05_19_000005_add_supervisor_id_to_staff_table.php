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
        Schema::table('staff', function (Blueprint $table) {
            $table->foreignId('supervisor_id')->nullable()->after('duty_station_id');
            
            // If the column doesn't already exist, add it
            if (!Schema::hasColumn('staff', 'active')) {
                $table->boolean('active')->default(true)->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('supervisor_id');
            
            // Only drop the active column if we created it
            if (Schema::hasColumn('staff', 'active')) {
                $table->dropColumn('active');
            }
        });
    }
};
