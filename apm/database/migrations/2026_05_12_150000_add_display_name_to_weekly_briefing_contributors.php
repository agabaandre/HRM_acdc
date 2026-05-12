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

        if (Schema::hasColumn('weekly_briefing_contributors', 'display_name')) {
            return;
        }

        Schema::table('weekly_briefing_contributors', function (Blueprint $table) {
            $table->string('display_name', 255)->nullable()->after('contribution_key')->comment('Shown on PDFs instead of system division/directorate name when set');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('weekly_briefing_contributors')) {
            return;
        }

        if (! Schema::hasColumn('weekly_briefing_contributors', 'display_name')) {
            return;
        }

        Schema::table('weekly_briefing_contributors', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
