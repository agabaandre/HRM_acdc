<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_approval_levels')) {
            Schema::create('leave_approval_levels', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('role', 20);
                $table->unsignedInteger('staff_id')->nullable();
                $table->string('label', 120);
                $table->timestamps();
                $table->index(['sort_order'], 'leave_approval_levels_sort_index');
            });
        }

        if (! Schema::hasTable('staff_leave_approval_steps')) {
            Schema::create('staff_leave_approval_steps', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('request_id');
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('role', 20);
                $table->unsignedInteger('staff_id')->default(0);
                $table->string('label', 120);
                $table->string('status', 20)->default('Pending');
                $table->text('comments')->nullable();
                $table->timestamp('acted_at')->nullable();
                $table->unsignedInteger('acted_by')->nullable();
                $table->timestamps();
                $table->index(['request_id', 'sort_order'], 'leave_approval_steps_request_sort');
                $table->index(['staff_id', 'status'], 'leave_approval_steps_staff_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_leave_approval_steps');
        Schema::dropIfExists('leave_approval_levels');
    }
};
