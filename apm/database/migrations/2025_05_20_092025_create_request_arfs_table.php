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
        Schema::disableForeignKeyConstraints();
        
        Schema::create('request_arfs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained();
            $table->foreignId('forward_workflow_id');
            $table->foreignId('reverse_workflow_id');
            $table->string('arf_number')->unique();
            $table->date('request_date');
            $table->foreignId('division_id')->constrained();
            $table->json('location_id');
            $table->string('activity_title');
            $table->text('purpose');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('requested_amount', 15, 2);
            $table->string('accounting_code');
            $table->json('budget_breakdown');
            $table->json('attachment')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->timestamps();
        });
        
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_arfs');
    }
};
