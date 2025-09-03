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
        // Check if the column already exists
        $columnExists = DB::select("SHOW COLUMNS FROM workflow_definition LIKE 'print_order'");
        
        if (empty($columnExists)) {
            Schema::table('workflow_definition', function (Blueprint $table) {
                // Add print_order column to control the order of workflow steps in memo printing
                $table->integer('print_order')
                      ->nullable()
                      ->after('memo_print_section')
                      ->comment('Order in which this workflow step should appear in memo printing within its section');
            });
        } else {
            // Column already exists, skip the migration
            echo "print_order column already exists, skipping migration.\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if the column exists before trying to drop it
        $columnExists = DB::select("SHOW COLUMNS FROM workflow_definition LIKE 'print_order'");
        
        if (!empty($columnExists)) {
            Schema::table('workflow_definition', function (Blueprint $table) {
                // Drop the print_order column
                $table->dropColumn('print_order');
            });
        } else {
            echo "print_order column does not exist, skipping rollback.\n";
        }
    }
};
