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
        Schema::table('matrices', function (Blueprint $table) {
            $table->unsignedBigInteger('forward_workflow_id')->nullable()->after('staff_id');
            $table->unsignedBigInteger('reverse_workflow_id')->nullable()->after('forward_workflow_id');
            $table->unsignedInteger('approval_level')->default(1)->after('reverse_workflow_id');
            $table->unsignedInteger('next_approval_level')->nullable()->after('approval_level');
            $table->string('overall_status')->default('pending')->after('next_approval_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matrices', function (Blueprint $table) {
            $table->dropColumn([
                'forward_workflow_id',
                'reverse_workflow_id',
                'approval_level',
                'next_approval_level',
                'overall_status',
            ]);
        });
    }
};
