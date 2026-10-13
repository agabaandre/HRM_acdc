<?php

use Database\Seeders\FinanceGrantsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_grants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->unsignedBigInteger('funder_id')->nullable()->index();
            $table->string('funder_name')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('total_budget', 18, 2)->nullable();
            $table->decimal('direct_cost_usd', 18, 2)->nullable();
            $table->decimal('indirect_cost', 18, 2)->nullable();
            $table->decimal('indirect_pct', 8, 4)->nullable();
            $table->string('budget_code', 64)->nullable();
            $table->string('status', 32)->default('active');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['name']);
        });

        // Ensure catalogue exists on migrate (production deploys).
        (new FinanceGrantsSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_grants');
    }
};
