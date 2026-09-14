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
        Schema::create('workflow_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_name')->unique(); // Matrix, Activity, NonTravelMemo, etc.
            $table->integer('workflow_id'); // Changed to match workflows table (signed int)
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->foreign('workflow_id')->references('id')->on('workflows')->onDelete('cascade');
            $table->index(['model_name', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_models');
    }
};
