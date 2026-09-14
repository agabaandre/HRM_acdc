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
        Schema::table('activities', function (Blueprint $table) {
            // Change text fields to longText, preserving nullable status
            if (Schema::hasColumn('activities', 'key_result_area')) {
                $table->longText('key_result_area')->change();
            }
            if (Schema::hasColumn('activities', 'background')) {
                $table->longText('background')->change();
            }
            if (Schema::hasColumn('activities', 'activity_request_remarks')) {
                $table->longText('activity_request_remarks')->change();
            }
            if (Schema::hasColumn('activities', 'activity_title')) {
                $table->longText('activity_title')->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            // Revert longText back to text, preserving nullable status
            if (Schema::hasColumn('activities', 'key_result_area')) {
                $table->text('key_result_area')->change();
            }
            if (Schema::hasColumn('activities', 'background')) {
                $table->text('background')->change();
            }
            if (Schema::hasColumn('activities', 'activity_request_remarks')) {
                $table->text('activity_request_remarks')->change();
            }
            if (Schema::hasColumn('activities', 'activity_title')) {
                $table->text('activity_title')->change();
            }
        });
    }
};
