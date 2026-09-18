<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rr_approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('division_id')->nullable()->index();
            $table->unsignedInteger('directorate_id')->nullable()->index();
            $table->string('name', 128)->default('Default');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('rr_approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('rr_approval_workflows')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('role', 32); // risk_focal|hod|director|sm_focal|extra
            $table->unsignedInteger('staff_id')->nullable(); // required for sm_focal/extra
            $table->boolean('skippable_if_empty')->default(false);
            $table->timestamps();
            $table->unique(['workflow_id', 'step_order']);
        });

        Schema::create('rr_risk_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_id')->constrained('rr_risks')->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained('rr_approval_workflow_steps')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('role', 32);
            $table->unsignedInteger('assignee_staff_id')->nullable()->index();
            $table->string('status', 32)->default('pending')->index(); // pending|approved|skipped|feedback_requested
            $table->unsignedInteger('acted_by_staff_id')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('rr_feedback_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_approval_id')->constrained('rr_risk_approvals')->cascadeOnDelete();
            $table->unsignedInteger('requester_staff_id');
            $table->text('message');
            $table->string('status', 32)->default('open'); // open|closed
            $table->timestamps();
        });

        Schema::create('rr_feedback_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_request_id')->constrained('rr_feedback_requests')->cascadeOnDelete();
            $table->unsignedInteger('staff_id')->index();
            $table->timestamps();
            $table->unique(['feedback_request_id', 'staff_id'], 'rr_feedback_recip_unique');
        });

        Schema::create('rr_feedback_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_request_id')->constrained('rr_feedback_requests')->cascadeOnDelete();
            $table->unsignedInteger('responder_staff_id');
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rr_feedback_responses');
        Schema::dropIfExists('rr_feedback_recipients');
        Schema::dropIfExists('rr_feedback_requests');
        Schema::dropIfExists('rr_risk_approvals');
        Schema::dropIfExists('rr_approval_workflow_steps');
        Schema::dropIfExists('rr_approval_workflows');
    }
};
