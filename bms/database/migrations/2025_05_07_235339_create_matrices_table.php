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
        Schema::disableForeignKeyConstraints();

        Schema::create('matrices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('focal_person_id')->constrained();
            $table->foreignId('division_id')->constrained();
            $table->year('year');
            $table->enum('quarter', ["Q1","Q2","Q3","Q4"]);
            $table->json('key_result_area');
            $table->foreignId('staff_id');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matrices');
    }
};
