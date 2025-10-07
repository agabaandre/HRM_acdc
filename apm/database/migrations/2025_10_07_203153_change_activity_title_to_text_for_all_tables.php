<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change activity_title to TEXT for all relevant tables
        // Note: We need to drop indexes before changing to TEXT
        
        // Request ARFs table
        if (Schema::hasTable('request_arfs') && Schema::hasColumn('request_arfs', 'activity_title')) {
            // Drop indexes using raw SQL to avoid naming issues
            try {
                DB::statement('ALTER TABLE request_arfs DROP INDEX request_arfs_activity_title_index');
            } catch (Exception $e) {
                // Index might not exist, continue
            }
            
            Schema::table('request_arfs', function (Blueprint $table) {
                $table->text('activity_title')->change();
            });
        }
        
        // Non Travel Memos table
        if (Schema::hasTable('non_travel_memos') && Schema::hasColumn('non_travel_memos', 'activity_title')) {
            try {
                DB::statement('ALTER TABLE non_travel_memos DROP INDEX non_travel_memos_activity_title_index');
            } catch (Exception $e) {
                // Index might not exist, continue
            }
            
            Schema::table('non_travel_memos', function (Blueprint $table) {
                $table->text('activity_title')->change();
            });
        }
        
        // Special Memos table
        if (Schema::hasTable('special_memos') && Schema::hasColumn('special_memos', 'activity_title')) {
            try {
                DB::statement('ALTER TABLE special_memos DROP INDEX special_memos_activity_title_index');
            } catch (Exception $e) {
                // Index might not exist, continue
            }
            
            Schema::table('special_memos', function (Blueprint $table) {
                $table->text('activity_title')->change();
            });
        }
        
        // Change Request table
        if (Schema::hasTable('change_request') && Schema::hasColumn('change_request', 'activity_title')) {
            try {
                DB::statement('ALTER TABLE change_request DROP INDEX change_request_activity_title_index');
            } catch (Exception $e) {
                // Index might not exist, continue
            }
            
            Schema::table('change_request', function (Blueprint $table) {
                $table->text('activity_title')->change();
            });
        }
        
        // Service Requests table (service_title column)
        if (Schema::hasTable('service_requests') && Schema::hasColumn('service_requests', 'service_title')) {
            try {
                DB::statement('ALTER TABLE service_requests DROP INDEX service_requests_service_title_index');
            } catch (Exception $e) {
                // Index might not exist, continue
            }
            
            Schema::table('service_requests', function (Blueprint $table) {
                $table->text('service_title')->change();
            });
        }
        
        // Activities table (already changed to TEXT in previous migration, but ensuring it's correct)
        if (Schema::hasTable('activities') && Schema::hasColumn('activities', 'activity_title')) {
            try {
                DB::statement('ALTER TABLE activities DROP INDEX activities_activity_title_index');
            } catch (Exception $e) {
                // Index might not exist, continue
            }
            
            Schema::table('activities', function (Blueprint $table) {
                $table->text('activity_title')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert activity_title back to string for all tables
        
        // Request ARFs table
        if (Schema::hasTable('request_arfs') && Schema::hasColumn('request_arfs', 'activity_title')) {
            Schema::table('request_arfs', function (Blueprint $table) {
                $table->string('activity_title', 255)->change();
            });
        }
        
        // Non Travel Memos table
        if (Schema::hasTable('non_travel_memos') && Schema::hasColumn('non_travel_memos', 'activity_title')) {
            Schema::table('non_travel_memos', function (Blueprint $table) {
                $table->string('activity_title', 255)->change();
            });
        }
        
        // Special Memos table
        if (Schema::hasTable('special_memos') && Schema::hasColumn('special_memos', 'activity_title')) {
            Schema::table('special_memos', function (Blueprint $table) {
                $table->string('activity_title', 255)->change();
            });
        }
        
        // Change Request table
        if (Schema::hasTable('change_request') && Schema::hasColumn('change_request', 'activity_title')) {
            Schema::table('change_request', function (Blueprint $table) {
                $table->string('activity_title', 255)->change();
            });
        }
        
        // Service Requests table (service_title column)
        if (Schema::hasTable('service_requests') && Schema::hasColumn('service_requests', 'service_title')) {
            Schema::table('service_requests', function (Blueprint $table) {
                $table->string('service_title', 255)->change();
            });
        }
        
        // Activities table
        if (Schema::hasTable('activities') && Schema::hasColumn('activities', 'activity_title')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->string('activity_title', 255)->change();
            });
        }
    }
};
