<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 32)->nullable();
            $table->boolean('connected')->default(false);
            $table->boolean('registered')->default(false);
            $table->string('pairing_code', 16)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_groups', function (Blueprint $table) {
            $table->string('jid', 120)->primary();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_bot_on')->default(false);
            $table->boolean('is_chat_bot_on')->default(false);
            $table->boolean('is_img_on')->default(false);
            $table->boolean('is_91_only')->default(false);
            $table->boolean('is_auto_sticker_on')->default(false);
            $table->boolean('is_rank_notif_on')->default(false);
            $table->unsignedInteger('total_msg_count')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('is_bot_on');
            $table->index('name');
        });

        Schema::create('whatsapp_group_members', function (Blueprint $table) {
            $table->id();
            $table->string('group_jid', 120);
            $table->string('member_jid', 120);
            $table->string('username')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();

            $table->unique(['group_jid', 'member_jid']);
            $table->index('member_jid');
            $table->foreign('group_jid')->references('jid')->on('whatsapp_groups')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_group_members');
        Schema::dropIfExists('whatsapp_groups');
        Schema::dropIfExists('whatsapp_sessions');
    }
};
