<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_agent_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('helpdesk_categories')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'category_id']);
        });

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->text('resolution_summary')->nullable()->after('description');
            $table->string('resolution_confirm_token', 64)->nullable()->unique();
            $table->timestamp('resolution_confirmed_at')->nullable()->after('resolved_at');
            $table->foreignId('resolution_submitted_by_user_id')->nullable()->after('assigned_user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by_user_id')->nullable()->after('resolution_submitted_by_user_id')->constrained('users')->nullOnDelete();
            $table->boolean('agent_logged_for_requester')->default(false)->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropForeign(['resolution_submitted_by_user_id']);
            $table->dropForeign(['resolved_by_user_id']);
            $table->dropColumn([
                'created_by_user_id',
                'resolution_summary',
                'resolution_confirm_token',
                'resolution_confirmed_at',
                'resolution_submitted_by_user_id',
                'resolved_by_user_id',
                'agent_logged_for_requester',
            ]);
        });

        Schema::dropIfExists('helpdesk_agent_categories');
    }
};
