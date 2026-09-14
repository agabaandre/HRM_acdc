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
        Schema::create('fund_code_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_code_id')->constrained('fund_codes');
            $table->decimal('amount', 10, 2);
            $table->string('description')->nullable();
            $table->foreignId('activity_id');
            $table->foreignId('matrix_id')->constrained('matrices');
            $table->foreignId('activity_budget_id')->constrained('activity_budgets');
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->boolean('is_reversal')->default(false);
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_code_transactions');
    }
};
