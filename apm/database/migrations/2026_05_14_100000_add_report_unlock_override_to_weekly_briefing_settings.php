<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('weekly_briefing_settings', 'report_unlock_override_enabled')) {
            Schema::table('weekly_briefing_settings', function (Blueprint $table) {
                $table->boolean('report_unlock_override_enabled')->default(false)->after('division_directors_can_access_module');
                $table->timestamp('report_unlock_override_until')->nullable();
                $table->string('report_unlock_override_scope', 16)->default('all')->comment('all | division');
                $table->unsignedBigInteger('report_unlock_override_division_id')->nullable()->comment('divisions.id when scope=division');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('weekly_briefing_settings', 'report_unlock_override_enabled')) {
            Schema::table('weekly_briefing_settings', function (Blueprint $table) {
                $table->dropColumn([
                    'report_unlock_override_enabled',
                    'report_unlock_override_until',
                    'report_unlock_override_scope',
                    'report_unlock_override_division_id',
                ]);
            });
        }
    }
};
