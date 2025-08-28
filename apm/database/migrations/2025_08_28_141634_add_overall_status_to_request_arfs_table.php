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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_arfs', function (Blueprint $table) {
            // Drop overall_status column if it exists
            if (Schema::hasColumn('request_arfs', 'overall_status')) {
                $table->dropColumn('overall_status');
            }
        });
    }
};
