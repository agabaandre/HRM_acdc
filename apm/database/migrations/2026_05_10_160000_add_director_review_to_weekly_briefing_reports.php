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
            if (! Schema::hasColumn('weekly_briefing_reports', 'director_reviewed_at')) {
                $table->timestamp('director_reviewed_at')->nullable()->after('submitted_by_staff_id');
            }
            if (! Schema::hasColumn('weekly_briefing_reports', 'director_reviewed_by_staff_id')) {
                $table->unsignedBigInteger('director_reviewed_by_staff_id')->nullable()->after('director_reviewed_at');
            }
            if (! Schema::hasColumn('weekly_briefing_reports', 'director_review_trail')) {
                $table->json('director_review_trail')->nullable()->after('director_reviewed_by_staff_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('weekly_briefing_reports')) {
            return;
        }
        Schema::table('weekly_briefing_reports', function (Blueprint $table) {
            if (Schema::hasColumn('weekly_briefing_reports', 'director_review_trail')) {
                $table->dropColumn('director_review_trail');
            }
            if (Schema::hasColumn('weekly_briefing_reports', 'director_reviewed_by_staff_id')) {
                $table->dropColumn('director_reviewed_by_staff_id');
            }
            if (Schema::hasColumn('weekly_briefing_reports', 'director_reviewed_at')) {
                $table->dropColumn('director_reviewed_at');
            }
        });
    }
};
