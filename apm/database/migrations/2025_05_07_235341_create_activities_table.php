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

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forward_workflow_id');
            $table->foreignId('reverse_workflow_id');
            $table->string('workplan_activity_code');
            $table->foreignId('matrix_id')->constrained();
            $table->foreignId('staff_id')->constrained();
            $table->date('date_from');
            $table->date('date_to');
            $table->json('location_id');
            $table->integer('total_participants');
            $table->json('internal_participants');
            $table->json('budget_id');
            $table->longText('key_result_area');
            $table->foreignId('request_type_id')->constrained();
            $table->string('activity_title');
            $table->longText('background');
            $table->longText('activity_request_remarks');
            $table->boolean('is_sepecial_memo')->default();
            $table->json('budget');
            $table->json('attachment');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
