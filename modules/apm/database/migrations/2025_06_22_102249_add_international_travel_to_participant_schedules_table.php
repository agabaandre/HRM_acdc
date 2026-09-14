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
        Schema::table('participant_schedules', function (Blueprint $table) {
            $table->boolean('international_travel')->default(1)->after('participant_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('participant_schedules', function (Blueprint $table) {
            $table->dropColumn('international_travel');
        });
    }
};
