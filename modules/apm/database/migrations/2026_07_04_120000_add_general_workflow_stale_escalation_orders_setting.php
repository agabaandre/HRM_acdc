<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('system_settings')->where('key', 'general_workflow_stale_escalation_orders')->exists();
        if ($exists) {
            return;
        }

        DB::table('system_settings')->insert([
            'key' => 'general_workflow_stale_escalation_orders',
            'value' => '',
            'group' => 'approvals',
            'type' => 'text',
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'general_workflow_stale_escalation_orders')->delete();
    }
};
