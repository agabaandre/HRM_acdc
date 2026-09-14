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
        Schema::create('matrix_approval_trails', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('matrix_id');
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('oic_staff_id')->nullable();

            $table->string('action');
            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matrix_approval_trails');
    }
};
