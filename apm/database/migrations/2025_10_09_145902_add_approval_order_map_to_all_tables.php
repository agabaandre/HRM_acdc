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
        // Add approval_order_map column to matrices table
        if (Schema::hasTable('matrices')) {
            Schema::table('matrices', function (Blueprint $table) {
                $table->json('approval_order_map')->nullable()->after('approval_level');
            });
        }

        // Add approval_order_map column to non_travel_memos table
        if (Schema::hasTable('non_travel_memos')) {
            Schema::table('non_travel_memos', function (Blueprint $table) {
                $table->json('approval_order_map')->nullable()->after('approval_level');
            });
        }

        // Add approval_order_map column to special_memos table
        if (Schema::hasTable('special_memos')) {
            Schema::table('special_memos', function (Blueprint $table) {
                $table->json('approval_order_map')->nullable()->after('approval_level');
            });
        }

        // Add approval_order_map column to activities table
        if (Schema::hasTable('activities')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->json('approval_order_map')->nullable()->after('approval_level');
            });
        }

        // Add approval_order_map column to request_arfs table
        if (Schema::hasTable('request_arfs')) {
            Schema::table('request_arfs', function (Blueprint $table) {
                $table->json('approval_order_map')->nullable()->after('approval_level');
            });
        }

        // Add approval_order_map column to service_requests table
        if (Schema::hasTable('service_requests')) {
            Schema::table('service_requests', function (Blueprint $table) {
                $table->json('approval_order_map')->nullable()->after('approval_level');
            });
        }

        // Add approval_order_map column to change_request table
        if (Schema::hasTable('change_request')) {
            Schema::table('change_request', function (Blueprint $table) {
                $table->json('approval_order_map')->nullable()->after('approval_level');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove approval_order_map column from matrices table
        if (Schema::hasTable('matrices') && Schema::hasColumn('matrices', 'approval_order_map')) {
            Schema::table('matrices', function (Blueprint $table) {
                $table->dropColumn('approval_order_map');
            });
        }

        // Remove approval_order_map column from non_travel_memos table
        if (Schema::hasTable('non_travel_memos') && Schema::hasColumn('non_travel_memos', 'approval_order_map')) {
            Schema::table('non_travel_memos', function (Blueprint $table) {
                $table->dropColumn('approval_order_map');
            });
        }

        // Remove approval_order_map column from special_memos table
        if (Schema::hasTable('special_memos') && Schema::hasColumn('special_memos', 'approval_order_map')) {
            Schema::table('special_memos', function (Blueprint $table) {
                $table->dropColumn('approval_order_map');
            });
        }

        // Remove approval_order_map column from activities table
        if (Schema::hasTable('activities') && Schema::hasColumn('activities', 'approval_order_map')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->dropColumn('approval_order_map');
            });
        }

        // Remove approval_order_map column from request_arfs table
        if (Schema::hasTable('request_arfs') && Schema::hasColumn('request_arfs', 'approval_order_map')) {
            Schema::table('request_arfs', function (Blueprint $table) {
                $table->dropColumn('approval_order_map');
            });
        }

        // Remove approval_order_map column from service_requests table
        if (Schema::hasTable('service_requests') && Schema::hasColumn('service_requests', 'approval_order_map')) {
            Schema::table('service_requests', function (Blueprint $table) {
                $table->dropColumn('approval_order_map');
            });
        }

        // Remove approval_order_map column from change_request table
        if (Schema::hasTable('change_request') && Schema::hasColumn('change_request', 'approval_order_map')) {
            Schema::table('change_request', function (Blueprint $table) {
                $table->dropColumn('approval_order_map');
            });
        }
    }
};
