<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('org_structure_nodes')) {
            Schema::create('org_structure_nodes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->string('node_type', 32)->default('position')->index();
                $table->string('title', 255);
                $table->unsignedInteger('job_id')->nullable()->index();
                $table->unsignedInteger('grade_id')->nullable()->index();
                $table->string('grade_code', 32)->nullable();
                $table->string('grade_band', 64)->nullable();
                $table->unsignedBigInteger('directorate_id')->nullable()->index();
                $table->unsignedInteger('division_id')->nullable()->index();
                $table->unsignedInteger('unit_id')->nullable()->index();
                $table->unsignedInteger('approved_slots')->default(1);
                $table->integer('sort_order')->default(0);
                $table->unsignedTinyInteger('is_active')->default(1);
                $table->string('source', 32)->default('generated');
                $table->string('tier', 32)->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('org_structure_assignments')) {
            Schema::create('org_structure_assignments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('node_id')->index();
                $table->unsignedInteger('staff_id')->index();
                $table->unsignedInteger('staff_contract_id')->nullable()->index();
                $table->unsignedTinyInteger('is_primary')->default(1);
                $table->string('match_status', 32)->default('auto');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('org_structure_assignments');
        Schema::dropIfExists('org_structure_nodes');
    }
};
