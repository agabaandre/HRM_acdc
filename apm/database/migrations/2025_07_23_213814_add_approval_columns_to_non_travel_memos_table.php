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
        Schema::table('non_travel_memos', function (Blueprint $table) {
            if (!Schema::hasColumn('non_travel_memos', 'forward_workflow_id')) {
                $table->unsignedBigInteger('forward_workflow_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('non_travel_memos', 'reverse_workflow_id')) {
                $table->unsignedBigInteger('reverse_workflow_id')->nullable()->after('forward_workflow_id');
            }
            if (!Schema::hasColumn('non_travel_memos', 'overall_status')) {
                $table->string('overall_status')->nullable()->after('attachment');
            }
            if (!Schema::hasColumn('non_travel_memos', 'approval_level')) {
                $table->integer('approval_level')->nullable()->after('overall_status');
            }
            if (!Schema::hasColumn('non_travel_memos', 'next_approval_level')) {
                $table->integer('next_approval_level')->nullable()->after('approval_level');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('non_travel_memos', function (Blueprint $table) {
            if (Schema::hasColumn('non_travel_memos', 'forward_workflow_id')) {
                $table->dropColumn('forward_workflow_id');
            }
            if (Schema::hasColumn('non_travel_memos', 'reverse_workflow_id')) {
                $table->dropColumn('reverse_workflow_id');
            }
            if (Schema::hasColumn('non_travel_memos', 'overall_status')) {
                $table->dropColumn('overall_status');
            }
            if (Schema::hasColumn('non_travel_memos', 'approval_level')) {
                $table->dropColumn('approval_level');
            }
            if (Schema::hasColumn('non_travel_memos', 'next_approval_level')) {
                $table->dropColumn('next_approval_level');
            }
        });
    }
};
