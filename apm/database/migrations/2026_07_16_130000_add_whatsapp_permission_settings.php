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
                'key' => 'whatsapp_module_grant_all',
                'value' => '1',
                'group' => 'whatsapp',
                'type' => 'boolean',
            ],
            [
                'key' => 'whatsapp_module_permission_ids',
                'value' => '',
                'group' => 'whatsapp',
                'type' => 'text',
            ],
            [
                'key' => 'whatsapp_config_admin_only',
                'value' => '1',
                'group' => 'whatsapp',
                'type' => 'boolean',
            ],
            [
                'key' => 'whatsapp_config_permission_ids',
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
            'whatsapp_module_grant_all',
            'whatsapp_module_permission_ids',
            'whatsapp_config_admin_only',
            'whatsapp_config_permission_ids',
        ])->delete();
    }
};
