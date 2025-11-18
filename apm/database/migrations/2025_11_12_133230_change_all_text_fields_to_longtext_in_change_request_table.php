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
        Schema::table('change_request', function (Blueprint $table) {
            // Change text fields to longText, preserving nullable status
            if (Schema::hasColumn('change_request', 'supporting_reasons')) {
                $table->longText('supporting_reasons')->nullable()->change();
            }
            if (Schema::hasColumn('change_request', 'key_result_area')) {
                $table->longText('key_result_area')->nullable()->change();
            }
            if (Schema::hasColumn('change_request', 'justification')) {
                $table->longText('justification')->nullable()->change();
            }
            if (Schema::hasColumn('change_request', 'activity_title')) {
                $table->longText('activity_title')->change();
            }
            if (Schema::hasColumn('change_request', 'background')) {
                $table->longText('background')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('change_request', function (Blueprint $table) {
            // Revert longText back to text, preserving nullable status
            if (Schema::hasColumn('change_request', 'supporting_reasons')) {
                $table->text('supporting_reasons')->nullable()->change();
            }
            if (Schema::hasColumn('change_request', 'key_result_area')) {
                $table->text('key_result_area')->nullable()->change();
            }
            if (Schema::hasColumn('change_request', 'justification')) {
                $table->text('justification')->nullable()->change();
            }
            if (Schema::hasColumn('change_request', 'activity_title')) {
                $table->text('activity_title')->change();
            }
            if (Schema::hasColumn('change_request', 'background')) {
                $table->text('background')->nullable()->change();
            }
        });
    }
};
