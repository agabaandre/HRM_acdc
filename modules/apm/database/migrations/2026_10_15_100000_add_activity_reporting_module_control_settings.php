<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $now = now();
        $rows = [
            [
                'key' => 'module_activity_reporting_enabled',
                'value' => '0',
                'group' => 'modules',
                'type' => 'boolean',
            ],
            [
                'key' => 'module_activity_reporting_notifications_enabled',
                'value' => '0',
                'group' => 'modules',
                'type' => 'boolean',
            ],
        ];

        foreach ($rows as $row) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $row['key']],
                [
                    'value' => $row['value'],
                    'group' => $row['group'],
                    'type' => $row['type'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')->whereIn('key', [
            'module_activity_reporting_enabled',
            'module_activity_reporting_notifications_enabled',
        ])->delete();
    }
};
