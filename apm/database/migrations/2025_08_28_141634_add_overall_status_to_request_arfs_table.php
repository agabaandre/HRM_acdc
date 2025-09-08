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
        Schema::table('request_arfs', function (Blueprint $table) {
            // Add overall_status column if it doesn't exist
            if (!Schema::hasColumn('request_arfs', 'overall_status')) {
                $table->string('overall_status')->default('draft')->after('id')->comment('Overall status of the ARF request');
            }
            
            // Add status_updated_at column if it doesn't exist
            if (!Schema::hasColumn('request_arfs', 'status_updated_at')) {
                $table->timestamp('status_updated_at')->nullable()->after('overall_status')->comment('When the status was last updated');
            }
            
            // Add status_updated_by column if it doesn't exist
            if (!Schema::hasColumn('request_arfs', 'status_updated_by')) {
                $table->unsignedBigInteger('status_updated_by')->nullable()->after('status_updated_at')->comment('Staff ID who last updated the status');
            }
        });
        
        // Add index on overall_status for better query performance
        if (!Schema::hasIndex('request_arfs', 'request_arfs_overall_status_index')) {
            Schema::table('request_arfs', function (Blueprint $table) {
                $table->index('overall_status', 'request_arfs_overall_status_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop index on overall_status
        if (Schema::hasIndex('request_arfs', 'request_arfs_overall_status_index')) {
            Schema::table('request_arfs', function (Blueprint $table) {
                $table->dropIndex('request_arfs_overall_status_index');
            });
        }
        
        Schema::table('request_arfs', function (Blueprint $table) {
            // Drop status_updated_by column if it exists
            if (Schema::hasColumn('request_arfs', 'status_updated_by')) {
                $table->dropColumn('status_updated_by');
            }
            
            // Drop status_updated_at column if it exists
            if (Schema::hasColumn('request_arfs', 'status_updated_at')) {
                $table->dropColumn('status_updated_at');
            }
            
            // Drop overall_status column if it exists
            if (Schema::hasColumn('request_arfs', 'overall_status')) {
                $table->dropColumn('overall_status');
            }
        });
    }
};
