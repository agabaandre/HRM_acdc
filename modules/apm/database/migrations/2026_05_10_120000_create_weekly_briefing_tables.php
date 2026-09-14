<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('weekly_briefing_settings')) {
            Schema::create('weekly_briefing_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedTinyInteger('submission_weekday')->default(5)->comment('PHP: 0=Sun .. 5=Fri');
                $table->string('hod_reminder_time', 8)->default('09:00');
                $table->string('submission_close_time', 8)->default('14:00');
                $table->string('summary_send_time', 8)->default('14:10');
                $table->text('compiled_recipient_emails')->nullable()->comment('Comma-separated addresses');
                $table->boolean('cc_division_hod_on_compiled')->default(true);
                $table->boolean('reminders_enabled')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('weekly_briefing_reports')) {
            Schema::create('weekly_briefing_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('division_id');
                $table->unsignedBigInteger('directorate_id')->nullable();
                $table->unsignedSmallInteger('report_iso_week_year');
                $table->unsignedTinyInteger('report_iso_week');
                $table->date('period_start')->comment('Monday of ISO week');
                $table->string('status', 32)->default('draft')->comment('draft, submitted, locked');
                $table->json('section1_major_happenings')->nullable();
                $table->json('section2_bottlenecks')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('submitted_by_staff_id')->nullable();
                $table->timestamps();

                $table->unique(['division_id', 'report_iso_week_year', 'report_iso_week'], 'weekly_briefing_div_week_unique');
                $table->index(['report_iso_week_year', 'report_iso_week'], 'wb_reports_iso_week_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_briefing_reports');
        Schema::dropIfExists('weekly_briefing_settings');
    }
};
