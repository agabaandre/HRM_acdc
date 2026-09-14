<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_leave')) {
            return;
        }

        Schema::table('staff_leave', function (Blueprint $table): void {
            $sm = Schema::getConnection()->getSchemaBuilder();
            $indexes = collect($sm->getIndexes('staff_leave'))->pluck('name')->all();

            if (! in_array('staff_leave_overall_status_created_at_index', $indexes, true)) {
                $table->index(['overall_status', 'created_at'], 'staff_leave_overall_status_created_at_index');
            }
            if (! in_array('staff_leave_supervisor_id_index', $indexes, true)) {
                $table->index('supervisor_id', 'staff_leave_supervisor_id_index');
            }
            if (! in_array('staff_leave_supervisor2_id_index', $indexes, true)) {
                $table->index('supervisor2_id', 'staff_leave_supervisor2_id_index');
            }
            if (! in_array('staff_leave_division_head_index', $indexes, true)) {
                $table->index('division_head', 'staff_leave_division_head_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff_leave')) {
            return;
        }

        Schema::table('staff_leave', function (Blueprint $table): void {
            $sm = Schema::getConnection()->getSchemaBuilder();
            $indexes = collect($sm->getIndexes('staff_leave'))->pluck('name')->all();

            foreach ([
                'staff_leave_overall_status_created_at_index',
                'staff_leave_supervisor_id_index',
                'staff_leave_supervisor2_id_index',
                'staff_leave_division_head_index',
            ] as $name) {
                if (in_array($name, $indexes, true)) {
                    $table->dropIndex($name);
                }
            }
        });
    }
};
