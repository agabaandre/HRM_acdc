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
        Schema::table('divisions', function (Blueprint $table) {
            // $table->json('staff_ids')->nullable();
            // $table->boolean('is_external')->default(false);
            // $table->boolean('is_active')->default(true);
            // $table->foreignId('directorate_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->dropColumn(['staff_ids', 'is_external', 'is_active', 'directorate_id']);
        });
        Schema::dropIfExists('divisions');
    }
};
