<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the table if it exists
        Schema::dropIfExists('special_mem');
        Schema::dropIfExists('special_memos');

        // Recreate the activities table for special memos
        Schema::create('special_memos', function (Blueprint $table) {
            $table->id();

            $table->string('activity_id');
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('division_id')->nullable();

            $table->date('date_from');
            $table->date('date_to');

            $table->json('location_id');
            $table->integer('total_participants');
            $table->json('internal_participants');
            $table->unsignedInteger('total_external_participants')->nullable();

            $table->text('key_result_area');
            $table->unsignedBigInteger('request_type_id');
            $table->string('activity_title');
            $table->text('background');
            $table->text('activity_request_remarks')->nullable();
            $table->text('justification')->nullable();

            $table->boolean('is_special_memo')->default(0);

            $table->json('budget');
            $table->json('attachment');

            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');

            $table->timestamps();

            // Foreign keys (optional, uncomment if needed)
            // $table->foreign('staff_id')->references('id')->on('staff')->onDelete('cascade');
            // $table->foreign('division_id')->references('id')->on('divisions')->onDelete('set null');
            // $table->foreign('request_type_id')->references('id')->on('request_types');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_memo');
    }
};
