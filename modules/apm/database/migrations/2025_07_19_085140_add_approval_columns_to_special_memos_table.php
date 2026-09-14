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
        Schema::table('special_memos', function (Blueprint $table) {
            //
            $table->string('overall_status')->nullable()->after('status');
            $table->foreignId('forward_workflow_id')->nullable()->after('overall_status');
            $table->foreignId('approval_level')->nullable()->after('forward_workflow_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('special_memos', function (Blueprint $table) {
            $table->dropColumn('overall_status');
            $table->dropColumn('forward_workflow_id');
            $table->dropColumn('approval_level');
        });
    }
};
