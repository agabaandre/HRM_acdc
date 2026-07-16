<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $settings = [
            [
                'key' => 'whatsapp_driver',
                'value' => 'native',
                'group' => 'whatsapp',
                'type' => 'text',
            ],
            [
                'key' => 'whatsapp_worker_url',
                'value' => 'http://127.0.0.1:8765',
                'group' => 'whatsapp',
                'type' => 'text',
            ],
            [
                'key' => 'whatsapp_worker_token',
                'value' => Str::random(48),
                'group' => 'whatsapp',
                'type' => 'password',
            ],
        ];

        foreach ($settings as $row) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $row['key']],
                array_merge($row, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        DB::table('system_settings')
            ->where('key', 'whatsapp_bot_api_url')
            ->update(['value' => 'http://127.0.0.1:8765', 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', [
            'whatsapp_driver',
            'whatsapp_worker_url',
            'whatsapp_worker_token',
        ])->delete();
    }
};
