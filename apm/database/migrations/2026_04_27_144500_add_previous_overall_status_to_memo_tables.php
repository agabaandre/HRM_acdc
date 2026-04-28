<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('previous_overall_status')->nullable()->after('overall_status');
        });

        Schema::table('non_travel_memos', function (Blueprint $table) {
            $table->string('previous_overall_status')->nullable()->after('overall_status');
        });

        Schema::table('special_memos', function (Blueprint $table) {
            $table->string('previous_overall_status')->nullable()->after('overall_status');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('previous_overall_status')->nullable()->after('overall_status');
        });

        Schema::table('request_arfs', function (Blueprint $table) {
            $table->string('previous_overall_status')->nullable()->after('overall_status');
        });

        Schema::table('other_memos', function (Blueprint $table) {
            $table->string('previous_overall_status')->nullable()->after('overall_status');
        });

        Schema::table('matrices', function (Blueprint $table) {
            $table->string('previous_overall_status')->nullable()->after('overall_status');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('previous_overall_status');
        });

        Schema::table('non_travel_memos', function (Blueprint $table) {
            $table->dropColumn('previous_overall_status');
        });

        Schema::table('special_memos', function (Blueprint $table) {
            $table->dropColumn('previous_overall_status');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn('previous_overall_status');
        });

        Schema::table('request_arfs', function (Blueprint $table) {
            $table->dropColumn('previous_overall_status');
        });

        Schema::table('other_memos', function (Blueprint $table) {
            $table->dropColumn('previous_overall_status');
        });

        Schema::table('matrices', function (Blueprint $table) {
            $table->dropColumn('previous_overall_status');
        });
    }
};
