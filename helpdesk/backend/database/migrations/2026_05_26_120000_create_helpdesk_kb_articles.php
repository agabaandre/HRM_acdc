<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_kb_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('helpdesk_categories')->cascadeOnDelete();
            $table->string('question', 255);
            $table->longText('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category_id', 'is_active', 'sort_order'], 'kb_articles_category_active_sort_idx');
        });

        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            // Granular per-agent permission: admins always pass; agents only when
            // explicitly granted by an admin from Settings → Agents.
            $table->boolean('can_manage_kb')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            $table->dropColumn('can_manage_kb');
        });
        Schema::dropIfExists('helpdesk_kb_articles');
    }
};
