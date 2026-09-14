<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('weekly_briefing_contributors')) {
            return;
        }

        Schema::table('weekly_briefing_contributors', function (Blueprint $table) {
            if (! Schema::hasColumn('weekly_briefing_contributors', 'delegate_staff_id')) {
                $table->unsignedBigInteger('delegate_staff_id')->nullable()->after('staff_id');
                $table->index(
                    ['weekly_briefing_setting_id', 'delegate_staff_id'],
                    'wb_contrib_setting_delegate_idx'
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('weekly_briefing_contributors')) {
            return;
        }

        Schema::table('weekly_briefing_contributors', function (Blueprint $table) {
            if (Schema::hasColumn('weekly_briefing_contributors', 'delegate_staff_id')) {
                $table->dropIndex('wb_contrib_setting_delegate_idx');
                $table->dropColumn('delegate_staff_id');
            }
        });
    }
};
