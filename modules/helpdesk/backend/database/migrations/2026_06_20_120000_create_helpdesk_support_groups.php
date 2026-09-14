<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_support_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('helpdesk_support_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('helpdesk_support_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['group_id', 'user_id']);
        });

        Schema::create('helpdesk_support_group_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('helpdesk_support_groups')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('helpdesk_categories')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['group_id', 'category_id']);
        });

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->foreignId('assigned_group_id')
                ->nullable()
                ->after('assigned_user_id')
                ->constrained('helpdesk_support_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_group_id');
        });
        Schema::dropIfExists('helpdesk_support_group_categories');
        Schema::dropIfExists('helpdesk_support_group_members');
        Schema::dropIfExists('helpdesk_support_groups');
    }
};
