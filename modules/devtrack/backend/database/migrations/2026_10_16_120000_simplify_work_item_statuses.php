<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('work_items')->where('status', 'done')->update(['status' => 'completed']);

        DB::table('work_items')
            ->whereIn('status', ['blocked', 'in_review'])
            ->update([
                'status' => 'in_progress',
                'completed_at' => null,
            ]);

        DB::table('work_items')
            ->whereIn('status', ['backlog', 'ready', 'cancelled'])
            ->update([
                'status' => 'not_started',
                'completed_at' => null,
            ]);
    }

    public function down(): void
    {
        DB::table('work_items')->where('status', 'completed')->update(['status' => 'done']);
        DB::table('work_items')->where('status', 'not_started')->update(['status' => 'backlog']);
        // in_progress stays as in_progress
    }
};
