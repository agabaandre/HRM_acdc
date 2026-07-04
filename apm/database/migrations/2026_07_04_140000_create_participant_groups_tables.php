<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('division_id');
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('created_by');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['division_id', 'name']);
            $table->index('division_id');
            $table->index('created_by');
        });

        Schema::create('participant_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_group_id')->constrained('participant_groups')->cascadeOnDelete();
            $table->unsignedInteger('staff_id');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['participant_group_id', 'staff_id']);
            $table->index('staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_group_members');
        Schema::dropIfExists('participant_groups');
    }
};
