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
        // Check if the column already has the correct definition
        $columnInfo = DB::select("SHOW COLUMNS FROM workflow_definition LIKE 'memo_print_section'");
        
        if (!empty($columnInfo)) {
            $currentType = $columnInfo[0]->Type;
            $currentDefault = $columnInfo[0]->Default;
            
            // Check if 'through' is already in the enum and default is already 'through'
            if (strpos($currentType, "'through'") !== false && $currentDefault === 'through') {
                // Column is already in the correct state, skip the migration
                return;
            }
        }
        
        Schema::table('workflow_definition', function (Blueprint $table) {
            // Modify the memo_print_section column to add 'through' option and set it as default
            $table->enum('memo_print_section', ['from', 'to', 'through', 'others'])
                  ->default('through')
                  ->nullable(false)
                  ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_definition', function (Blueprint $table) {
            // Revert back to the original enum without 'through' and without default
            $table->enum('memo_print_section', ['from', 'to', 'others'])
                  ->nullable()
                  ->change();
        });
    }
};
