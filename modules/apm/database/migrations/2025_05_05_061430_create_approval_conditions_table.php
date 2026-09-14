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
        Schema::create('approval_conditions', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('workflow_id');
            $table->string('column_name', 100);
            $table->string('operator', 10);
            $table->string('value', 100);
            $table->integer('workflow_definition_id');
            $table->enum('flow_type', ['forward', 'reverse'])->default('forward');
            $table->boolean('is_enabled')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_conditions');
    }
};