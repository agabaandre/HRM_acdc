<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approver_document_timing_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('approval_trail_id')->nullable();
            $table->unique('approval_trail_id', 'adt_appr_trail_uid');

            $table->unsignedBigInteger('other_memo_approval_trail_id')->nullable();
            $table->unique('other_memo_approval_trail_id', 'adt_om_trail_uid');

            $table->unsignedBigInteger('staff_id');
            $table->string('staff_name_snapshot', 255)->nullable();

            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('forward_workflow_id')->nullable();

            $table->unsignedInteger('approval_order')->nullable();
            $table->string('action', 32);
            $table->timestamp('received_at');
            $table->timestamp('acted_at');
            $table->decimal('hours_elapsed', 14, 4);

            $table->string('document_type_label', 96)->nullable();
            $table->text('document_title')->nullable();
            $table->string('document_number_snapshot', 128)->nullable();

            $table->unsignedBigInteger('division_id')->nullable()->index();
            $table->string('division_name_snapshot', 255)->nullable();

            $table->string('workflow_name_snapshot', 255)->nullable();
            $table->string('workflow_role_snapshot', 255)->nullable();

            $table->timestamps();

            $table->index(['staff_id', 'acted_at']);
            $table->index(['model_type', 'model_id']);
            $table->index('acted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approver_document_timing_records');
    }
};
