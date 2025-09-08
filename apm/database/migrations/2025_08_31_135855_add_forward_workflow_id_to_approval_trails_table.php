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
        Schema::table('approval_trails', function (Blueprint $table) {
            // Add forward_workflow_id column if it doesn't exist
            if (!Schema::hasColumn('approval_trails', 'forward_workflow_id')) {
                $table->unsignedBigInteger('forward_workflow_id')->nullable()->before('approval_order')->comment('Reference to the forward workflow definition');
            }
        });
        
        // Add foreign key constraint if it doesn't exist
        if (!Schema::hasColumn('approval_trails', 'forward_workflow_id')) {
            Schema::table('approval_trails', function (Blueprint $table) {
                $table->foreign('forward_workflow_id')->references('id')->on('workflow_definitions')->onDelete('set null');
            });
        }
        
        // Add index on forward_workflow_id for better query performance
        if (!Schema::hasIndex('approval_trails', 'approval_trails_forward_workflow_id_index')) {
            Schema::table('approval_trails', function (Blueprint $table) {
                $table->index('forward_workflow_id', 'approval_trails_forward_workflow_id_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop index on forward_workflow_id
        if (Schema::hasIndex('approval_trails', 'approval_trails_forward_workflow_id_index')) {
            Schema::table('approval_trails', function (Blueprint $table) {
                $table->dropIndex('approval_trails_forward_workflow_id_index');
            });
        }
        
        Schema::table('approval_trails', function (Blueprint $table) {
            // Drop foreign key constraint first
            if (Schema::hasColumn('approval_trails', 'forward_workflow_id')) {
                $table->dropForeign(['forward_workflow_id']);
            }
            
            // Drop forward_workflow_id column if it exists
            if (Schema::hasColumn('approval_trails', 'forward_workflow_id')) {
                $table->dropColumn('forward_workflow_id');
            }
        });
    }
};
