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
        Schema::create('workflow_definition', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('role', 100);
            $table->integer('workflow_id');
            $table->integer('approval_order');
            $table->integer('is_enabled')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_definition');
    }
};
