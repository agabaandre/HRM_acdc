<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_portfolio_entries', function (Blueprint $table) {
            $table->id();
            $table->string('section', 64);
            $table->unsignedSmallInteger('budget_year');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('data');
            $table->timestamps();
            $table->index(['section', 'budget_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_portfolio_entries');
    }
};
