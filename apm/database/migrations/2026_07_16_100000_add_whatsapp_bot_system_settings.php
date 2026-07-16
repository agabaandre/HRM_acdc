<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $settings = [
            [
                'key' => 'whatsapp_bot_enabled',
                'value' => '0',
                'group' => 'whatsapp',
                'type' => 'boolean',
            ],
            [
                'key' => 'whatsapp_bot_api_url',
                'value' => 'http://127.0.0.1:8000',
                'group' => 'whatsapp',
                'type' => 'text',
            ],
            [
                'key' => 'whatsapp_bot_number',
                'value' => '',
                'group' => 'whatsapp',
                'type' => 'text',
            ],
            [
                'key' => 'whatsapp_bot_admin_password',
                'value' => null,
                'group' => 'whatsapp',
                'type' => 'password',
            ],
            [
                'key' => 'whatsapp_primary_group_jid',
                'value' => '',
                'group' => 'whatsapp',
                'type' => 'text',
            ],
            [
                'key' => 'whatsapp_primary_group_name',
                'value' => '',
                'group' => 'whatsapp',
                'type' => 'text',
            ],
        ];

        foreach ($settings as $row) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $row['key']],
                array_merge($row, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', [
            'whatsapp_bot_enabled',
            'whatsapp_bot_api_url',
            'whatsapp_bot_number',
            'whatsapp_bot_admin_password',
            'whatsapp_primary_group_jid',
            'whatsapp_primary_group_name',
        ])->delete();
    }
};
