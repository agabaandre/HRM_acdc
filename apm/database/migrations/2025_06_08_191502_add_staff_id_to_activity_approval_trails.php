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
        Schema::table('activity_approval_trails', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable();
            $table->foreignId('oic_staff_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_approval_trails', function (Blueprint $table) {
            $table->dropColumn('staff_id');
            $table->dropColumn('oic_staff_id');
        });
    }
};
