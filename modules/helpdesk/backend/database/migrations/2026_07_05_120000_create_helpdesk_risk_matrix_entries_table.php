<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_risk_matrix_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->string('priority', 24);
            $table->foreignId('category_id')->nullable()->constrained('helpdesk_categories')->nullOnDelete();
            $table->string('notes', 2000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['staff_id', 'is_active']);
            $table->unique(['staff_id', 'category_id'], 'helpdesk_risk_matrix_staff_category_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_risk_matrix_entries');
    }
};
