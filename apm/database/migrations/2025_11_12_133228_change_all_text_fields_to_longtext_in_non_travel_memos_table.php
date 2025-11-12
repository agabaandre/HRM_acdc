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
        Schema::table('non_travel_memos', function (Blueprint $table) {
            // Change text fields to longText, preserving nullable status
            if (Schema::hasColumn('non_travel_memos', 'background')) {
                $table->longText('background')->change();
            }
            if (Schema::hasColumn('non_travel_memos', 'activity_request_remarks')) {
                $table->longText('activity_request_remarks')->change();
            }
            if (Schema::hasColumn('non_travel_memos', 'justification')) {
                $table->longText('justification')->change();
            }
            if (Schema::hasColumn('non_travel_memos', 'activity_title')) {
                $table->longText('activity_title')->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('non_travel_memos', function (Blueprint $table) {
            // Revert longText back to text, preserving nullable status
            if (Schema::hasColumn('non_travel_memos', 'background')) {
                $table->text('background')->change();
            }
            if (Schema::hasColumn('non_travel_memos', 'activity_request_remarks')) {
                $table->text('activity_request_remarks')->change();
            }
            if (Schema::hasColumn('non_travel_memos', 'justification')) {
                $table->text('justification')->change();
            }
            if (Schema::hasColumn('non_travel_memos', 'activity_title')) {
                $table->text('activity_title')->change();
            }
        });
    }
};
