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
            // Change text fields to longText, preserving nullable status
            if (Schema::hasColumn('request_arfs', 'purpose')) {
                $table->longText('purpose')->change();
            }
            if (Schema::hasColumn('request_arfs', 'activity_title')) {
                $table->longText('activity_title')->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_arfs', function (Blueprint $table) {
            // Revert longText back to text, preserving nullable status
            if (Schema::hasColumn('request_arfs', 'purpose')) {
                $table->text('purpose')->change();
            }
            if (Schema::hasColumn('request_arfs', 'activity_title')) {
                $table->text('activity_title')->change();
            }
        });
    }
};
