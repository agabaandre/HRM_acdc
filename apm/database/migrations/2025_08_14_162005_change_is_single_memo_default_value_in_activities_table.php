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
        // Change the default value to 0
        DB::statement('ALTER TABLE activities ALTER COLUMN is_single_memo SET DEFAULT 0');
        
        // Update existing records that have NULL values to 0
        DB::statement('UPDATE activities SET is_single_memo = 0 WHERE is_single_memo IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Change the default value back to 1
        DB::statement('ALTER TABLE activities ALTER COLUMN is_single_memo SET DEFAULT 1');
    }
};
