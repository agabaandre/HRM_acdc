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
        Schema::table('service_requests', function (Blueprint $table) {
            // Budget breakdown and cost columns
            $table->json('budget_breakdown')->nullable()->comment('Original budget breakdown from source');
            $table->json('internal_participants_cost')->nullable()->comment('Cost breakdown for internal participants');
            $table->json('external_participants_cost')->nullable()->comment('Cost breakdown for external participants');
            $table->json('other_costs')->nullable()->comment('Other miscellaneous costs');
            $table->decimal('original_total_budget', 15, 2)->nullable()->comment('Original total budget from source');
            $table->decimal('new_total_budget', 15, 2)->nullable()->comment('New calculated total budget');
            
            // Fund and budget related columns
            $table->unsignedBigInteger('fund_type_id')->nullable()->comment('Fund type reference');
            $table->string('title')->nullable()->comment('Service request title');
            $table->integer('responsible_person_id')->nullable()->comment('Responsible person staff_id reference');
            $table->json('budget_id')->nullable()->comment('Budget IDs array');
            
            // Source tracking columns
            $table->string('model_type')->nullable()->comment('Source model class name');
            $table->unsignedBigInteger('source_id')->nullable()->comment('Source record ID');
            $table->string('source_type')->nullable()->comment('Source type description');
            
            // Approval workflow columns
            $table->integer('approval_level')->nullable()->comment('Current approval level');
            $table->integer('next_approval_level')->nullable()->comment('Next approval level');
            
            // Add foreign key constraints
            $table->foreign('fund_type_id')->references('id')->on('fund_types')->onDelete('set null');
            $table->foreign('responsible_person_id')->references('staff_id')->on('staff')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['fund_type_id']);
            $table->dropForeign(['responsible_person_id']);
            
            // Drop columns
            $table->dropColumn([
                'budget_breakdown',
                'internal_participants_cost',
                'external_participants_cost',
                'other_costs',
                'original_total_budget',
                'new_total_budget',
                'fund_type_id',
                'title',
                'responsible_person_id',
                'budget_id',
                'model_type',
                'source_id',
                'source_type',
                'approval_level',
                'next_approval_level'
            ]);
        });
    }
};