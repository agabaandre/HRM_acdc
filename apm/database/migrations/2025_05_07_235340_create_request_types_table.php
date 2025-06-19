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

        Schema::create('request_types', function (Blueprint $table) {
            $table->id();
            $table->string('request_type');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('workflow_id')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();

            $table->foreign('workflow_id')->references('id')->on('workflows')->nullOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_types');
    }
};
