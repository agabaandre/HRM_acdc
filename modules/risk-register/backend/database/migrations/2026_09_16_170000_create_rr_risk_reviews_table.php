<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rr_risk_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_id')->constrained('rr_risks')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('quarter'); // 1-4
            $table->unsignedTinyInteger('likelihood');
            $table->unsignedTinyInteger('impact');
            $table->unsignedTinyInteger('inherent_score');
            $table->string('inherent_rating', 32)->nullable();
            $table->text('mitigation_strategy')->nullable();
            $table->string('timeline', 255)->nullable();
            $table->unsignedInteger('author_staff_id')->nullable();
            $table->timestamps();
            $table->unique(['risk_id', 'year', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rr_risk_reviews');
    }
};
