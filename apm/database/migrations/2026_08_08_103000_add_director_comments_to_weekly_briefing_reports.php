<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('weekly_briefing_reports')) {
            return;
        }

        Schema::table('weekly_briefing_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('weekly_briefing_reports', 'director_comments')) {
                $table->text('director_comments')->nullable()->after('director_review_trail');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('weekly_briefing_reports')) {
            return;
        }

        Schema::table('weekly_briefing_reports', function (Blueprint $table) {
            if (Schema::hasColumn('weekly_briefing_reports', 'director_comments')) {
                $table->dropColumn('director_comments');
            }
        });
    }
};
