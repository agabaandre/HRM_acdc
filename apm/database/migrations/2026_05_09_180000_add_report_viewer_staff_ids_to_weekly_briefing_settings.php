<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('weekly_briefing_settings')) {
            return;
        }
        if (Schema::hasColumn('weekly_briefing_settings', 'report_viewer_staff_ids')) {
            return;
        }
        Schema::table('weekly_briefing_settings', function (Blueprint $table) {
            $table->json('report_viewer_staff_ids')->nullable()->after('reminders_enabled');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('weekly_briefing_settings')) {
            return;
        }
        if (! Schema::hasColumn('weekly_briefing_settings', 'report_viewer_staff_ids')) {
            return;
        }
        Schema::table('weekly_briefing_settings', function (Blueprint $table) {
            $table->dropColumn('report_viewer_staff_ids');
        });
    }
};
