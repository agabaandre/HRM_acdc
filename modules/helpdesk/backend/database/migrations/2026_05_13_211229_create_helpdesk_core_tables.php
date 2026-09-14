<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('helpdesk_sla_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('category_id')->nullable()->constrained('helpdesk_categories')->nullOnDelete();
            $table->unsignedInteger('response_minutes')->default(240);
            $table->unsignedInteger('resolution_minutes')->default(2880);
            $table->json('business_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('helpdesk_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('staff_id')->nullable()->unique();
            $table->string('role', 32)->default('user');
            $table->unsignedBigInteger('directorate_id')->nullable();
            $table->unsignedBigInteger('division_id')->nullable();
            $table->string('duty_station')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('helpdesk_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 40)->unique();
            $table->foreignId('category_id')->constrained('helpdesk_categories');
            $table->string('subject');
            $table->longText('description')->nullable();
            $table->string('priority', 24)->default('medium');
            $table->string('status', 32)->default('open');
            $table->string('source', 24)->default('web');
            $table->unsignedBigInteger('requester_staff_id')->nullable()->index();
            $table->string('requester_name')->nullable();
            $table->string('requester_email')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('directorate_id')->nullable()->index();
            $table->unsignedBigInteger('division_id')->nullable()->index();
            $table->unsignedBigInteger('country_id')->nullable()->index();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('sla_response_due_at')->nullable();
            $table->timestamp('sla_resolution_due_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('helpdesk_ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('helpdesk_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('author_staff_id')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->longText('body');
            $table->timestamps();
        });

        Schema::create('helpdesk_ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('helpdesk_tickets')->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path', 1024);
            $table->string('original_name', 512);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('mime_type', 191)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('helpdesk_ticket_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('helpdesk_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 64);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('helpdesk_ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver', 32);
            $table->string('api_base_url', 512)->nullable();
            $table->text('api_key_encrypted')->nullable();
            $table->string('default_model', 191)->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(false);
            $table->json('options')->nullable();
            $table->timestamps();
        });

        Schema::create('helpdesk_ai_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->nullable()->constrained('helpdesk_ai_providers')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('helpdesk_tickets')->nullOnDelete();
            $table->string('feature', 64);
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->json('request_meta')->nullable();
            $table->timestamps();
        });

        Schema::create('helpdesk_faq_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('helpdesk_faq_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('helpdesk_faq_categories')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body');
            $table->unsignedInteger('views')->default(0);
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('helpdesk_whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->nullable()->constrained('helpdesk_tickets')->nullOnDelete();
            $table->string('direction', 8);
            $table->string('external_id', 191)->nullable()->index();
            $table->json('payload');
            $table->timestamps();
        });

        Schema::create('helpdesk_teams_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->nullable()->constrained('helpdesk_tickets')->nullOnDelete();
            $table->string('direction', 8);
            $table->string('external_id', 191)->nullable()->index();
            $table->json('payload');
            $table->timestamps();
        });

        Schema::create('helpdesk_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('staff_id')->nullable()->index();
            $table->string('action', 128);
            $table->string('auditable_type', 191)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('helpdesk_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_notifications');
        Schema::dropIfExists('helpdesk_audit_logs');
        Schema::dropIfExists('helpdesk_teams_messages');
        Schema::dropIfExists('helpdesk_whatsapp_messages');
        Schema::dropIfExists('helpdesk_faq_articles');
        Schema::dropIfExists('helpdesk_faq_categories');
        Schema::dropIfExists('helpdesk_ai_logs');
        Schema::dropIfExists('helpdesk_ai_providers');
        Schema::dropIfExists('helpdesk_ticket_histories');
        Schema::dropIfExists('helpdesk_ticket_attachments');
        Schema::dropIfExists('helpdesk_ticket_comments');
        Schema::dropIfExists('helpdesk_tickets');
        Schema::dropIfExists('helpdesk_profiles');
        Schema::dropIfExists('helpdesk_sla_rules');
        Schema::dropIfExists('helpdesk_categories');
    }
};
