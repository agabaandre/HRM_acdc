<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('weekly_briefing_contributors')) {
            Schema::create('weekly_briefing_contributors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('weekly_briefing_setting_id');
                $table->unsignedBigInteger('staff_id');
                $table->unsignedBigInteger('apm_division_id')->comment('APM division context for this contributor');
                $table->string('contribution_key', 64)->comment('d-{division_id} or dr-{directorate_id}');
                $table->timestamps();

                $table->index(['weekly_briefing_setting_id', 'contribution_key'], 'wb_contrib_setting_key_idx');
                $table->index(['weekly_briefing_setting_id', 'staff_id'], 'wb_contrib_setting_staff_idx');
            });
        }

        if (! Schema::hasTable('weekly_briefing_reports')) {
            return;
        }

        if (Schema::hasColumn('weekly_briefing_reports', 'contribution_key')) {
            return;
        }

        Schema::table('weekly_briefing_reports', function (Blueprint $table) {
            $table->string('contribution_key', 64)->nullable()->after('division_id');
        });

        foreach (DB::table('weekly_briefing_reports')->cursor() as $row) {
            $key = 'd-'.$row->division_id;
            DB::table('weekly_briefing_reports')->where('id', $row->id)->update(['contribution_key' => $key]);
        }

        try {
            Schema::table('weekly_briefing_reports', function (Blueprint $table) {
                $table->dropUnique('weekly_briefing_div_week_unique');
            });
        } catch (\Throwable) {
            // Index may already have been dropped or never existed under this name.
        }

        try {
            Schema::table('weekly_briefing_reports', function (Blueprint $table) {
                $table->unique(['report_iso_week_year', 'report_iso_week', 'contribution_key'], 'weekly_briefing_week_contribution_unique');
            });
        } catch (\Throwable) {
            // Unique may already exist (re-run / partial migrate).
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('weekly_briefing_reports')) {
            Schema::dropIfExists('weekly_briefing_contributors');

            return;
        }

        if (Schema::hasColumn('weekly_briefing_reports', 'contribution_key')) {
            try {
                Schema::table('weekly_briefing_reports', function (Blueprint $table) {
                    $table->dropUnique('weekly_briefing_week_contribution_unique');
                });
            } catch (\Throwable) {
            }

            try {
                Schema::table('weekly_briefing_reports', function (Blueprint $table) {
                    $table->dropColumn('contribution_key');
                });
            } catch (\Throwable) {
            }

            try {
                Schema::table('weekly_briefing_reports', function (Blueprint $table) {
                    $table->unique(['division_id', 'report_iso_week_year', 'report_iso_week'], 'weekly_briefing_div_week_unique');
                });
            } catch (\Throwable) {
            }
        }

        Schema::dropIfExists('weekly_briefing_contributors');
    }
};
