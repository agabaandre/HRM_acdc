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
        // Add document_number column to all relevant tables
        $tables = [
            'matrices',
            'activities', 
            'non_travel_memos',
            'special_memos',
            'service_requests',
            'request_arfs'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->string('document_number', 50)->nullable()->unique()->after('id');
                    $table->index('document_number');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'matrices',
            'activities',
            'non_travel_memos', 
            'special_memos',
            'service_requests',
            'request_arfs'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropIndex(['document_number']);
                    $table->dropColumn('document_number');
                });
            }
        }
    }
};