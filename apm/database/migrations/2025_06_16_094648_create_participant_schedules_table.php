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
        Schema::create('participant_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id');
            $table->foreignId('matrix_id');
            $table->foreignId('participant_id');
            $table->date('participant_start');
            $table->date('participant_end');
            $table->integer('participant_days');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('participant_schedules');
    }
};
