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
        // Drop the existing table if it exists
        Schema::dropIfExists('request_arfs');
        
        // Create the complete request_arfs table
        Schema::create('request_arfs', function (Blueprint $table) {
            $table->id();
            $table->integer('staff_id')->comment('References staff.staff_id');
            $table->integer('responsible_person_id')->nullable()->comment('References staff.staff_id');
            $table->integer('forward_workflow_id')->nullable();
            $table->integer('reverse_workflow_id')->nullable();
            $table->string('arf_number');
            $table->date('request_date');
            $table->unsignedBigInteger('division_id');
            $table->json('location_id')->nullable();
            $table->string('activity_title');
            $table->text('purpose');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('requested_amount', 15, 2);
            $table->string('accounting_code');
            $table->json('budget_breakdown')->nullable();
            $table->json('attachment')->nullable();
            $table->unsignedBigInteger('fund_type_id')->nullable();
            $table->unsignedBigInteger('funder_id')->nullable();
            $table->string('extramural_code')->nullable();
            $table->string('model_type')->nullable();
            $table->integer('source_id')->nullable();
            $table->string('source_type')->nullable();
            $table->integer('approval_level')->default(0);
            $table->integer('next_approval_level')->nullable();
            $table->enum('overall_status', ['draft', 'pending', 'approved', 'rejected', 'returned'])->default('draft');
            $table->timestamp('status_updated_at')->nullable()->comment('When the status was last updated');
            $table->integer('status_updated_by')->nullable()->comment('Staff ID who last updated the status');
            $table->json('internal_participants')->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('staff_id')->references('staff_id')->on('staff')->onDelete('cascade');
            $table->foreign('responsible_person_id')->references('staff_id')->on('staff')->onDelete('set null');
            $table->foreign('forward_workflow_id')->references('id')->on('workflows')->onDelete('set null');
            $table->foreign('reverse_workflow_id')->references('id')->on('workflows')->onDelete('set null');
            $table->foreign('division_id')->references('id')->on('divisions')->onDelete('cascade');
            $table->foreign('fund_type_id')->references('id')->on('fund_types')->onDelete('set null');
            $table->foreign('funder_id')->references('id')->on('funders')->onDelete('set null');
            $table->foreign('status_updated_by')->references('staff_id')->on('staff')->onDelete('set null');
            
            // Indexes for better performance
            $table->index('arf_number');
            $table->index('overall_status');
            $table->index('request_date');
            $table->index('division_id');
            $table->index('staff_id');
            $table->index(['model_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_arfs');
    }
};