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

        Schema::create('non_travel_memos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forward_workflow_id');
            $table->foreignId('reverse_workflow_id');
            $table->string('workplan_activity_code');
            $table->foreignId('staff_id')->constrained();
            $table->date('memo_date');
            $table->json('location_id');
            $table->foreignId('non_travel_memo_category_id')->constrained();
            $table->json('budget_id');
            $table->string('activity_title');
            $table->longText('background');
            $table->longText('activity_request_remarks');
            $table->longText('justification');
            $table->json('budget_breakdown');
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
        Schema::dropIfExists('non_travel_memos');
    }
};
