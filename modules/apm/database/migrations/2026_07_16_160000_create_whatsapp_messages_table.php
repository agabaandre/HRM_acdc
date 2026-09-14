<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('group_jid', 120);
            $table->string('wa_message_id', 120);
            $table->string('sender_jid', 120)->nullable();
            $table->string('sender_phone', 32)->nullable();
            $table->string('sender_name', 191)->nullable();
            $table->boolean('from_me')->default(false);
            $table->string('message_type', 32)->default('text');
            $table->text('body')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['group_jid', 'wa_message_id']);
            $table->index(['group_jid', 'sent_at']);
            $table->index(['group_jid', 'id']);
            $table->foreign('group_jid')->references('jid')->on('whatsapp_groups')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
