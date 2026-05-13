<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_briefing_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('filing_iso_week_offset')->default(0)->after('submission_weekday');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_briefing_settings', function (Blueprint $table) {
            $table->dropColumn('filing_iso_week_offset');
        });
    }
};
