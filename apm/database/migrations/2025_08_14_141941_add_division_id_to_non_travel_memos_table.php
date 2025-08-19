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
        Schema::table('non_travel_memos', function (Blueprint $table) {
            // Add division_id column after staff_id
            $table->foreignId('division_id')->nullable()->after('staff_id');
        });

        // Populate division_id for existing records based on staff division
        $this->populateDivisionId();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('non_travel_memos', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
            $table->dropColumn('division_id');
        });
    }

    /**
     * Populate division_id for existing records based on staff division
     */
    private function populateDivisionId(): void
    {
        // Update division_id based on staff division for existing records
        DB::statement("
            UPDATE non_travel_memos 
            SET division_id = (
                SELECT division_id 
                FROM staff 
                WHERE staff.staff_id = non_travel_memos.staff_id
            )
            WHERE division_id IS NULL
        ");
    }
};
