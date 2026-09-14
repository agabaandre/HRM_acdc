<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_group_members', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_group_members', 'phone')) {
                $table->string('phone', 32)->nullable()->after('member_jid');
                $table->index('phone');
            }
            if (! Schema::hasColumn('whatsapp_group_members', 'lid')) {
                $table->string('lid', 120)->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_group_members', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_group_members', 'lid')) {
                $table->dropColumn('lid');
            }
            if (Schema::hasColumn('whatsapp_group_members', 'phone')) {
                $table->dropIndex(['phone']);
                $table->dropColumn('phone');
            }
        });
    }
};
