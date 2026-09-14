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

        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->date('request_date');
            $table->foreignId('staff_id')->constrained();
            $table->foreignId('activity_id')->nullable()->constrained();
            $table->foreignId('workflow_id');
            $table->foreignId('reverse_workflow_id');
            $table->foreignId('division_id')->constrained();
            $table->string('service_title');
            $table->text('description');
            $table->text('justification');
            $table->date('required_by_date');
            $table->string('location')->nullable();
            $table->decimal('estimated_cost', 15, 2)->default(0);
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('service_type', ['it', 'maintenance', 'procurement', 'travel', 'other'])->default('other');
            $table->json('specifications')->nullable();
            $table->json('attachments')->nullable();
            $table->enum('status', ['draft', 'submitted', 'in_progress', 'approved', 'rejected', 'completed'])->default('draft');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
