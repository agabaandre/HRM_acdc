<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rr_risks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('import_row_number')->nullable()->index();
            $table->string('source_business_unit', 255)->nullable();
            $table->unsignedInteger('division_id')->nullable()->index();
            $table->unsignedInteger('directorate_id')->nullable()->index();
            $table->string('unmapped_business_unit', 255)->nullable();
            $table->foreignId('enterprise_theme_id')->nullable()->constrained('rr_enterprise_themes')->nullOnDelete();
            $table->string('name', 512);
            $table->text('consequence')->nullable();
            $table->text('root_causes')->nullable();
            $table->foreignId('risk_type_id')->nullable()->constrained('rr_risk_types')->nullOnDelete();
            $table->unsignedTinyInteger('inherent_likelihood')->nullable();
            $table->unsignedTinyInteger('inherent_impact')->nullable();
            $table->unsignedTinyInteger('inherent_score')->nullable();
            $table->string('inherent_rating', 32)->nullable();
            $table->text('mitigation')->nullable();
            $table->text('management_response')->nullable();
            $table->string('timeline', 255)->nullable();
            $table->foreignId('status_id')->nullable()->constrained('rr_statuses')->nullOnDelete();
            $table->text('action_update')->nullable();
            $table->date('date_of_update')->nullable();
            $table->text('oio_verification_notes')->nullable();
            $table->foreignId('mitigation_effectiveness_id')->nullable()->constrained('rr_mitigation_effectiveness')->nullOnDelete();
            $table->unsignedTinyInteger('residual_likelihood')->nullable();
            $table->unsignedTinyInteger('residual_impact')->nullable();
            $table->unsignedTinyInteger('residual_score')->nullable();
            $table->string('residual_rating', 32)->nullable();
            $table->string('risk_movement', 64)->nullable();
            $table->string('workflow_state', 64)->default('draft')->index();
            $table->boolean('imported')->default(false);
            $table->timestamps();
        });

        Schema::create('rr_risk_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_id')->constrained('rr_risks')->cascadeOnDelete();
            $table->unsignedInteger('staff_id')->index();
            $table->boolean('is_default_hod')->default(false);
            $table->timestamps();
            $table->unique(['risk_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rr_risk_owners');
        Schema::dropIfExists('rr_risks');
    }
};
