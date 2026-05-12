<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('weekly_briefing_settings', 'division_directors_can_access_module')) {
            Schema::table('weekly_briefing_settings', function (Blueprint $table) {
                $table->boolean('division_directors_can_access_module')
                    ->default(true)
                    ->after('reminders_enabled')
                    ->comment('When true, staff in divisions.director_id may open weekly briefing for configured units');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('weekly_briefing_settings', 'division_directors_can_access_module')) {
            Schema::table('weekly_briefing_settings', function (Blueprint $table) {
                $table->dropColumn('division_directors_can_access_module');
            });
        }
    }
};
