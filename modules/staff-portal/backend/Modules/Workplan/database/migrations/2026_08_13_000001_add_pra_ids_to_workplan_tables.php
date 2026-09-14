<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workplan_tasks') && ! Schema::hasColumn('workplan_tasks', 'pra_indicator_id')) {
            Schema::table('workplan_tasks', function (Blueprint $table): void {
                $table->unsignedBigInteger('pra_indicator_id')->nullable()->after('id');
                $table->string('pra_division_code', 32)->nullable()->after('pra_indicator_id');
                $table->unique('pra_indicator_id', 'workplan_tasks_pra_indicator_id_unique');
                $table->index('pra_division_code', 'workplan_tasks_pra_division_code_idx');
            });
        }

        if (Schema::hasTable('work_planner_tasks') && ! Schema::hasColumn('work_planner_tasks', 'pra_activity_id')) {
            Schema::table('work_planner_tasks', function (Blueprint $table): void {
                $table->unsignedBigInteger('pra_activity_id')->nullable()->after('activity_id');
                $table->unique('pra_activity_id', 'work_planner_tasks_pra_activity_id_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('workplan_tasks') && Schema::hasColumn('workplan_tasks', 'pra_indicator_id')) {
            Schema::table('workplan_tasks', function (Blueprint $table): void {
                $table->dropUnique('workplan_tasks_pra_indicator_id_unique');
                $table->dropIndex('workplan_tasks_pra_division_code_idx');
                $table->dropColumn(['pra_indicator_id', 'pra_division_code']);
            });
        }

        if (Schema::hasTable('work_planner_tasks') && Schema::hasColumn('work_planner_tasks', 'pra_activity_id')) {
            Schema::table('work_planner_tasks', function (Blueprint $table): void {
                $table->dropUnique('work_planner_tasks_pra_activity_id_unique');
                $table->dropColumn('pra_activity_id');
            });
        }
    }
};
