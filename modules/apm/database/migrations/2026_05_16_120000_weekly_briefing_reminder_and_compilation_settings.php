<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_briefing_settings', function (Blueprint $table) {
            $table->json('hod_reminder_days_before_deadline')->nullable()->after('hod_reminder_time');
            $table->string('hod_reminder_clock', 40)->default('submission_close_time')->after('hod_reminder_days_before_deadline');
            $table->json('director_review_reminder_days_before_deadline')->nullable()->after('hod_reminder_clock');
            $table->string('director_review_reminder_clock', 40)->default('submission_close_time')->after('director_review_reminder_days_before_deadline');
            $table->boolean('compiled_exclude_unreviewed_director_divisions')->default(false)->after('director_review_reminder_clock');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_briefing_settings', function (Blueprint $table) {
            $table->dropColumn([
                'hod_reminder_days_before_deadline',
                'hod_reminder_clock',
                'director_review_reminder_days_before_deadline',
                'director_review_reminder_clock',
                'compiled_exclude_unreviewed_director_divisions',
            ]);
        });
    }
};
