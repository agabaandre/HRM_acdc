<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Default threshold (days) after which approvers receive aging reminders for items at their level.
     */
    public function up(): void
    {
        if (!Schema::hasTable('system_settings')) {
            return;
        }

        $now = now();
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'approval_warning_days'],
            [
                'value' => '7',
                'group' => 'approvals',
                'type' => 'number',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')->where('key', 'approval_warning_days')->delete();
    }
};
