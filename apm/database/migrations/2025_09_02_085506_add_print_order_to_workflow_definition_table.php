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
        Schema::table('workflow_definition', function (Blueprint $table) {
            // Add print_order column to control the order of workflow steps in memo printing
            $table->integer('print_order')
                  ->nullable()
                  ->after('memo_print_section')
                  ->comment('Order in which this workflow step should appear in memo printing within its section');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_definition', function (Blueprint $table) {
            // Drop the print_order column
            $table->dropColumn('print_order');
        });
    }
};
