<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id');
            $table->string('source_type');
            $table->string('mission_type')->nullable();
            $table->string('document_number')->nullable();
            $table->unsignedBigInteger('division_id')->nullable();
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->unsignedBigInteger('responsible_person_id')->nullable();
            $table->unsignedBigInteger('forward_workflow_id')->nullable();
            $table->unsignedBigInteger('reverse_workflow_id')->nullable();
            $table->string('approval_level')->nullable();
            $table->string('next_approval_level')->nullable();
            $table->string('overall_status')->default('draft');
            $table->json('approval_order_map')->nullable();
            $table->boolean('not_executed')->default(false);
            $table->boolean('has_been_submitted')->default(false);
            $table->text('tier2')->nullable();
            $table->text('tier4')->nullable();
            $table->string('activity_name_code')->nullable();
            $table->text('broad_activity_amert')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('duration_days')->nullable();
            $table->longText('objectives')->nullable();
            $table->string('location_venue')->nullable();
            $table->unsignedBigInteger('lead_organiser_staff_id')->nullable();
            $table->json('other_organiser_staff_ids')->nullable();
            $table->unsignedInteger('participants_count')->nullable();
            $table->longText('member_states_covered')->nullable();
            $table->longText('key_strategic_needs')->nullable();
            $table->longText('narrative_achievements')->nullable();
            $table->longText('challenges_recommendations')->nullable();
            $table->longText('means_of_verification')->nullable();
            $table->string('budget_code')->nullable();
            $table->decimal('total_cost_usd', 15, 2)->nullable();
            $table->longText('logistics_planned')->nullable();
            $table->longText('logistics_actuals')->nullable();
            $table->json('mission_extras')->nullable();
            $table->json('attachments')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'source_type'], 'activity_reports_source_unique');
            $table->index(['overall_status', 'division_id']);
        });

        Schema::create('activity_report_related', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_report_id')->constrained('activity_reports')->cascadeOnDelete();
            $table->unsignedBigInteger('related_source_id');
            $table->string('related_source_type');
            $table->timestamps();
            $table->unique(
                ['activity_report_id', 'related_source_id', 'related_source_type'],
                'activity_report_related_unique'
            );
        });

        Schema::create('reporting_registry_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id');
            $table->string('source_type');
            $table->timestamp('started_at')->nullable();
            $table->unsignedBigInteger('started_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamps();
            $table->unique(['source_id', 'source_type'], 'reporting_registry_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_report_related');
        Schema::dropIfExists('activity_reports');
        Schema::dropIfExists('reporting_registry_statuses');
    }
};
