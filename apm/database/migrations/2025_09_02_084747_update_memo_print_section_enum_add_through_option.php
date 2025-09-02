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
