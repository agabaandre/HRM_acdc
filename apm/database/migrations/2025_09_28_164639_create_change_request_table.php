<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('change_request', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_memo_id')->nullable();
            $table->string('parent_memo_model')->nullable(); // e.g app/models/activity/
            $table->unsignedBigInteger('activity_id')->nullable();
            $table->unsignedBigInteger('special_memo_id')->nullable();
            $table->unsignedBigInteger('non_travel_memo_id')->nullable();
            $table->unsignedBigInteger('request_arf_id')->nullable();
            $table->unsignedBigInteger('service_request_id')->nullable();
            
            // Change tracking flags
            $table->boolean('has_budget_id_changed')->default(false); // budget code changes
            $table->boolean('has_internal_participants_changed')->default(false);
            $table->boolean('has_request_type_id_changed')->default(false);
            $table->boolean('has_total_external_participants_changed')->default(false);
            $table->boolean('has_location_changed')->default(false);
            $table->boolean('has_memo_date_changed')->default(false);
            $table->boolean('has_activity_title_changed')->default(false);
            $table->boolean('has_activity_request_remarks_changed')->default(false);
            $table->boolean('has_is_single_memo_changed')->default(false);
            $table->boolean('has_budget_breakdown_changed')->default(false);
            $table->boolean('has_status_changed')->default(false);
            $table->boolean('has_fund_type_id_changed')->default(false);
            
            // Document and workflow fields
            $table->string('document_number', 100)->nullable();
            $table->unsignedBigInteger('forward_workflow_id')->nullable();
            $table->string('workplan_activity_code')->nullable();
            $table->unsignedBigInteger('matrix_id')->nullable();
            $table->unsignedBigInteger('division_id')->nullable();
            $table->unsignedInteger('staff_id');
            $table->unsignedInteger('responsible_person_id')->nullable();
            
            // Content fields
            $table->text('supporting_reasons')->nullable(); // for special memo
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->date('memo_date')->nullable(); // for non travel memo
            $table->json('location_id')->nullable();
            $table->integer('total_participants')->nullable();
            $table->json('internal_participants')->nullable();
            $table->unsignedInteger('total_external_participants')->nullable();
            $table->json('division_staff_request')->nullable();
            $table->json('budget_id')->nullable();
            $table->text('key_result_area')->nullable();
            $table->text('justification')->nullable(); // additional justification field
            $table->unsignedBigInteger('non_travel_memo_category_id')->nullable();
            $table->unsignedBigInteger('request_type_id')->nullable();
            $table->text('activity_title');
            $table->text('background')->nullable();
            $table->longText('activity_request_remarks')->nullable();
            $table->boolean('is_single_memo')->default(false);
            $table->json('budget_breakdown')->nullable();
            $table->decimal('available_budget', 15, 2)->nullable();
            $table->json('attachment')->nullable();
            
            // Status fields
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->unsignedBigInteger('fund_type_id')->nullable();
            $table->string('activity_ref')->nullable();
            $table->string('approval_level')->nullable();
            $table->integer('next_approval_level')->nullable();
            $table->string('overall_status')->nullable();
            
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['parent_memo_id', 'parent_memo_model']);
            $table->index('activity_id');
            $table->index('special_memo_id');
            $table->index('non_travel_memo_id');
            $table->index('request_arf_id');
            $table->index('service_request_id');
            $table->index('staff_id');
            $table->index('division_id');
            $table->index('overall_status');
            $table->index('approval_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('change_request');
    }
};