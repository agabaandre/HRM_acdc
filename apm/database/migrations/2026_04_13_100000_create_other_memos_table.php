<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('other_memos', function (Blueprint $table) {
            $table->id();
            $table->string('memo_type_slug', 191);
            $table->string('memo_type_name_snapshot');
            $table->string('ref_prefix_snapshot', 64)->nullable();
            $table->string('signature_style_snapshot', 32)->nullable();
            $table->json('fields_schema_snapshot');
            $table->json('payload')->nullable();
            $table->json('approvers_config')->nullable();
            $table->string('document_number', 191)->nullable()->unique();
            $table->unsignedBigInteger('staff_id')->index();
            $table->unsignedBigInteger('division_id')->nullable()->index();
            $table->string('overall_status', 32)->default('draft');
            $table->unsignedInteger('active_sequence')->nullable();
            $table->unsignedInteger('returned_at_sequence')->nullable();
            $table->unsignedBigInteger('current_approver_staff_id')->nullable()->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('other_memos');
    }
};
