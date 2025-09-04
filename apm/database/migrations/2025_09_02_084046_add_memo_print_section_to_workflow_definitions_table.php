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
            // Add memo_print_section column with enum constraint
            $table->enum('memo_print_section', ['from', 'to', 'others'])
                  ->nullable()
                  ->after('is_enabled')
                  ->comment('Defines which section of the memo this workflow definition should appear in');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_definition', function (Blueprint $table) {
            // Drop the memo_print_section column
            $table->dropColumn('memo_print_section');
        });
    }
};
