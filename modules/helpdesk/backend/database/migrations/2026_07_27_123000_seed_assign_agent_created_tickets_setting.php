<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('helpdesk_settings')->updateOrInsert(
            ['key' => 'assign_agent_created_tickets_to_creator'],
            ['value' => '1', 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        DB::table('helpdesk_settings')->where('key', 'assign_agent_created_tickets_to_creator')->delete();
    }
};
