<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        $map = [
            'draft' => 'not_started',
            'active' => 'in_progress',
            'on_hold' => 'under_review',
            'cancelled' => 'under_review',
            'completed' => 'completed',
        ];

        foreach ($map as $from => $to) {
            DB::table('projects')->where('status', $from)->update(['status' => $to]);
        }

        // Any unexpected legacy value → not started
        DB::table('projects')
            ->whereNotIn('status', ['not_started', 'in_progress', 'under_review', 'completed'])
            ->update(['status' => 'not_started']);

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE projects MODIFY status VARCHAR(255) NOT NULL DEFAULT 'not_started'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        $map = [
            'not_started' => 'draft',
            'in_progress' => 'active',
            'under_review' => 'on_hold',
            'completed' => 'completed',
        ];

        foreach ($map as $from => $to) {
            DB::table('projects')->where('status', $from)->update(['status' => $to]);
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE projects MODIFY status VARCHAR(255) NOT NULL DEFAULT 'active'");
        }
    }
};
