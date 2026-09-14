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
        Schema::create('document_counters', function (Blueprint $table) {
            $table->id();
            $table->string('division_short_name', 10);
            $table->integer('year');
            $table->string('document_type', 10); // QM, NT, SPM, SM, CR, SR, ARF
            $table->integer('counter')->default(0);
            $table->timestamps();
            
            // Unique constraint to prevent duplicates
            $table->unique(['division_short_name', 'year', 'document_type'], 'unique_division_year_type');
            
            // Indexes for performance
            $table->index(['division_short_name', 'year']);
            $table->index('document_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_counters');
    }
};