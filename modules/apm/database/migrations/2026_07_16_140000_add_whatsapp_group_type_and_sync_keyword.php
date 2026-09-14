<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('whatsapp_groups', 'group_type')) {
            Schema::table('whatsapp_groups', function (Blueprint $table) {
                $table->string('group_type', 32)->default('standard')->after('description');
                $table->index('group_type');
            });
        }

        $now = now();
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'whatsapp_group_sync_keyword'],
            [
                'value' => 'Africa CDC',
                'group' => 'whatsapp',
                'type' => 'text',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasColumn('whatsapp_groups', 'group_type')) {
            Schema::table('whatsapp_groups', function (Blueprint $table) {
                $table->dropIndex(['group_type']);
                $table->dropColumn('group_type');
            });
        }

        DB::table('system_settings')->where('key', 'whatsapp_group_sync_keyword')->delete();
    }
};
