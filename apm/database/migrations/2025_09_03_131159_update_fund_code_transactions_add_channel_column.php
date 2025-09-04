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
        Schema::table('fund_code_transactions', function (Blueprint $table) {
            // Make matrix_id nullable
            $table->unsignedBigInteger('matrix_id')->nullable()->change();
            
            // Add channel column with enum values
            $table->enum('channel', ['matrix', 'non_travel', 'special_memo', 'single_memo'])
                  ->default('matrix')
                  ->after('matrix_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fund_code_transactions', function (Blueprint $table) {
            // Remove the channel column
            $table->dropColumn('channel');
            
            // Revert matrix_id to not nullable (if needed)
            // Note: This might fail if there are NULL values in matrix_id
            // $table->unsignedBigInteger('matrix_id')->nullable(false)->change();
        });
    }
};
