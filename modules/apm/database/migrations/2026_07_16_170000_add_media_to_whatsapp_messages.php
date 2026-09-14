<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('media_path', 255)->nullable()->after('body');
            $table->string('media_mime', 120)->nullable()->after('media_path');
            $table->unsignedInteger('media_size')->nullable()->after('media_mime');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn(['media_path', 'media_mime', 'media_size']);
        });
    }
};
