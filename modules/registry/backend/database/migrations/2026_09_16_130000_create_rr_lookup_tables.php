<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rr_likelihoods', function (Blueprint $table) {
            $table->id();
            $table->string('label', 64);
            $table->unsignedTinyInteger('score')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rr_impacts', function (Blueprint $table) {
            $table->id();
            $table->string('label', 64);
            $table->unsignedTinyInteger('score')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rr_risk_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rr_enterprise_themes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->nullable();
            $table->string('name', 255)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rr_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rr_mitigation_effectiveness', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->unsignedTinyInteger('likelihood_reduction')->default(0);
            $table->boolean('is_assessed')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rr_rating_bands', function (Blueprint $table) {
            $table->id();
            $table->string('rating', 32);
            $table->unsignedTinyInteger('min_score');
            $table->unsignedTinyInteger('max_score');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rr_rating_bands');
        Schema::dropIfExists('rr_mitigation_effectiveness');
        Schema::dropIfExists('rr_statuses');
        Schema::dropIfExists('rr_enterprise_themes');
        Schema::dropIfExists('rr_risk_types');
        Schema::dropIfExists('rr_impacts');
        Schema::dropIfExists('rr_likelihoods');
    }
};
