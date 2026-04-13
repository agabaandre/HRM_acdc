<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memo_type_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('ref_prefix', 32)->nullable();
            $table->string('signature_style', 32)->default('top_right');
            $table->json('fields_schema');
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memo_type_definitions');
    }
};
