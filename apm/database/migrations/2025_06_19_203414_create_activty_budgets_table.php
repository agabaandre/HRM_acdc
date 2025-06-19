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
        Schema::create('activity_budgets', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('fund_type_id')->nullable();
            $table->unsignedBigInteger('activity_id');
            $table->unsignedBigInteger('matrix_id');
            $table->string('fund_code')->nullable();
            $table->string('cost');
            $table->text('description');
            $table->decimal('unit_cost', 15, 2);
            $table->integer('units');
            $table->integer('days');
            $table->decimal('total', 15, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_budgets');
    }
};
