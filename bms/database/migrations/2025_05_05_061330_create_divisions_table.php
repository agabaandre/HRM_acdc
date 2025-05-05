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
        Schema::create('divisions', function (Blueprint $table) {
            $table->integer('division_id')->autoIncrement();
            $table->string('division_name', 150);
            $table->integer('division_head');
            $table->integer('focal_person');
            $table->integer('admin_assistant');
            $table->integer('finance_officer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};