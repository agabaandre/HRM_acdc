<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_leave_approval_trail')) {
            Schema::create('staff_leave_approval_trail', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('request_id');
                $table->unsignedInteger('staff_id')->default(0);
                $table->unsignedInteger('step_id')->nullable();
                $table->string('role', 20)->nullable();
                $table->string('label', 120)->nullable();
                $table->string('action', 40);
                $table->text('comments')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['request_id', 'id'], 'leave_approval_trail_request_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_leave_approval_trail');
    }
};
