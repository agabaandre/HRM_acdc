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
        Schema::table('service_requests', function (Blueprint $table) {
            // Rename workflow_id to forward_workflow_id
            $table->renameColumn('workflow_id', 'forward_workflow_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            // Rename forward_workflow_id back to workflow_id
            $table->renameColumn('forward_workflow_id', 'workflow_id');
        });
    }
};