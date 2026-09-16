<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_sap_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('original_filename');
            $table->unsignedSmallInteger('budget_year');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('total_row_count')->default(0);
            $table->unsignedInteger('apm_updated_count')->default(0);
            $table->string('sync_status', 32)->default('pending');
            $table->text('sync_error')->nullable();
            $table->boolean('is_latest')->default(false);
            $table->timestamps();
            $table->index(['budget_year', 'is_latest']);
        });

        Schema::create('fin_sap_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('fin_sap_imports')->cascadeOnDelete();
            $table->unsignedSmallInteger('budget_year');
            $table->string('fund_center', 64)->nullable()->index();
            $table->string('gl_account', 64)->nullable();
            $table->boolean('is_total_row')->default(false);
            $table->json('payload');
            $table->timestamps();
        });

        Schema::create('fin_sap_fund_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('fin_sap_imports')->cascadeOnDelete();
            $table->unsignedSmallInteger('budget_year')->index();
            $table->string('fund_center', 64)->index();
            $table->decimal('total_released_budget', 18, 2)->default(0);
            $table->decimal('released_budget_balance', 18, 2)->default(0);
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['import_id', 'fund_center']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_sap_fund_centers');
        Schema::dropIfExists('fin_sap_rows');
        Schema::dropIfExists('fin_sap_imports');
    }
};
