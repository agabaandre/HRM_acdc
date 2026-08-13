<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_leave_plans')) {
            Schema::create('staff_leave_plans', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('staff_id');
                $table->unsignedSmallInteger('plan_year');
                /** 1 = draft (editable), 0 = submitted (locked for employee) */
                $table->unsignedTinyInteger('draft_status')->default(1);
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedInteger('submitted_by_user_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['staff_id', 'plan_year'], 'staff_leave_plans_staff_year_unique');
                $table->index(['plan_year', 'draft_status']);
            });
        }

        if (! Schema::hasTable('staff_leave_plan_entries')) {
            Schema::create('staff_leave_plan_entries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('leave_plan_id');
                $table->unsignedInteger('leave_id');
                $table->date('start_date');
                $table->date('end_date');
                $table->decimal('planned_days', 8, 2)->default(0);
                $table->string('remarks', 500)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index('leave_plan_id');
                $table->foreign('leave_plan_id')
                    ->references('id')
                    ->on('staff_leave_plans')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_leave_plan_entries');
        Schema::dropIfExists('staff_leave_plans');
    }
};
