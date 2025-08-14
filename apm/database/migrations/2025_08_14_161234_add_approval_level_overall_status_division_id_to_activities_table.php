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
        Schema::table('activities', function (Blueprint $table) {
            $table->string('approval_level')->nullable();
            $table->string('overall_status')->nullable();
            $table->unsignedBigInteger('division_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
            $table->dropColumn(['approval_level', 'overall_status', 'division_id']);
        });
    }
};
