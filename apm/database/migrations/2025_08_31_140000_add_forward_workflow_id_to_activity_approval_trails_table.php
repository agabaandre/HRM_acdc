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
        Schema::table('activity_approval_trails', function (Blueprint $table) {
            // Add forward_workflow_id column if it doesn't exist
            if (!Schema::hasColumn('activity_approval_trails', 'forward_workflow_id')) {
                $table->unsignedBigInteger('forward_workflow_id')->default(1)->after('approval_order')->comment('Reference to the forward workflow definition for this approval trail');
            }
        });
        

        
        // Add index on forward_workflow_id for better query performance
        if (!Schema::hasIndex('activity_approval_trails', 'activity_approval_trails_forward_workflow_id_index')) {
            Schema::table('activity_approval_trails', function (Blueprint $table) {
                $table->index('forward_workflow_id', 'activity_approval_trails_forward_workflow_id_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop index on forward_workflow_id
        if (Schema::hasIndex('activity_approval_trails', 'activity_approval_trails_forward_workflow_id_index')) {
            Schema::table('activity_approval_trails', function (Blueprint $table) {
                $table->dropIndex('activity_approval_trails_forward_workflow_id_index');
            });
        }
        
        Schema::table('activity_approval_trails', function (Blueprint $table) {
            // Drop foreign key constraint first
            if (Schema::hasColumn('activity_approval_trails', 'forward_workflow_id')) {
                $table->dropForeign(['forward_workflow_id']);
            }
            
            // Drop forward_workflow_id column if it exists
            if (Schema::hasColumn('activity_approval_trails', 'forward_workflow_id')) {
                $table->dropColumn('forward_workflow_id');
            }
        });
    }
};
