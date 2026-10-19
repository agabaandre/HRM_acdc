<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workplan_indicators', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('directorate')->nullable();
            $table->string('programme')->nullable();
            $table->unsignedSmallInteger('year');
            $table->string('target')->nullable();
            $table->string('unit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('work_class');
            $table->foreignId('workplan_indicator_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('work_request_id')->nullable()->index();
            $table->string('stage')->default('intake');
            $table->string('rag_status')->default('green');
            $table->text('rag_reason')->nullable();
            $table->string('priority_category')->default('planned');
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('target_end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->string('sprint_goal')->nullable();
            $table->string('budget_status')->nullable();
            $table->string('resource_status')->nullable();
            $table->unsignedTinyInteger('uat_progress_percent')->default(0);
            $table->string('deployment_readiness')->default('not_ready');
            $table->string('data_classification')->default('internal');
            $table->text('scope')->nullable();
            $table->text('success_measures')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('work_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('title');
            $table->string('requesting_unit');
            $table->string('requester_name');
            $table->string('requester_email')->nullable();
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('business_owner')->nullable();
            $table->text('problem_statement');
            $table->text('expected_outcomes')->nullable();
            $table->text('success_measures')->nullable();
            $table->text('target_users')->nullable();
            $table->string('urgency')->default('medium');
            $table->date('deadline')->nullable();
            $table->text('urgency_reason')->nullable();
            $table->string('data_classification')->default('internal');
            $table->text('existing_systems')->nullable();
            $table->text('integrations_needed')->nullable();
            $table->text('budget_notes')->nullable();
            $table->text('risk_of_not_delivering')->nullable();
            $table->string('work_class')->nullable();
            $table->string('priority_category')->nullable();
            $table->unsignedTinyInteger('score_strategic_alignment')->default(0);
            $table->unsignedTinyInteger('score_impact')->default(0);
            $table->unsignedTinyInteger('score_urgency_risk')->default(0);
            $table->unsignedTinyInteger('score_user_reach')->default(0);
            $table->unsignedTinyInteger('score_feasibility')->default(0);
            $table->unsignedTinyInteger('score_cost_effort')->default(0);
            $table->unsignedTinyInteger('score_security')->default(0);
            $table->unsignedTinyInteger('weighted_score')->default(0);
            $table->string('status')->default('submitted');
            $table->foreignId('workplan_indicator_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('project_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();
            $table->unique(['project_id', 'role']);
        });

        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('goal')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('planned');
            $table->unsignedSmallInteger('committed_points')->default(0);
            $table->unsignedSmallInteger('completed_points')->default(0);
            $table->timestamps();
        });

        Schema::create('work_items', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sprint_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('work_items')->nullOnDelete();
            $table->foreignId('work_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('acceptance_criteria')->nullable();
            $table->string('type')->default('task');
            $table->string('status')->default('not_started');
            $table->string('priority')->default('medium');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('estimate_hours')->nullable();
            $table->unsignedSmallInteger('story_points')->nullable();
            $table->date('due_date')->nullable();
            $table->text('blocked_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['project_id', 'status']);
            $table->index(['assignee_id', 'status']);
        });

        Schema::create('raid_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('severity')->default('medium');
            $table->string('status')->default('open');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->text('mitigation')->nullable();
            $table->timestamps();
        });

        Schema::create('status_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('title');
            $table->string('rag_status')->default('green');
            $table->text('summary')->nullable();
            $table->text('accomplishments')->nullable();
            $table->text('next_period_plan')->nullable();
            $table->text('blockers')->nullable();
            $table->text('decisions_required')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('project_stage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage')->nullable();
            $table->string('to_stage');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_stage_events');
        Schema::dropIfExists('status_reports');
        Schema::dropIfExists('raid_items');
        Schema::dropIfExists('work_items');
        Schema::dropIfExists('sprints');
        Schema::dropIfExists('project_role_assignments');
        Schema::dropIfExists('work_requests');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('workplan_indicators');
    }
};
