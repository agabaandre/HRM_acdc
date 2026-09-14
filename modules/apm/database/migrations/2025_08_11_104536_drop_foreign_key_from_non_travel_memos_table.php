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
        Schema::table('non_travel_memos', function (Blueprint $table) {
            $table->dropForeign('non_travel_memos_staff_id_foreign'); // Drop the foreign key
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('non_travel_memos', function (Blueprint $table) {
            $table->foreign('staff_id')
                ->references('id') // Adjust this if needed
                ->on('staff')
                ->onDelete('cascade');
        });
    }
};
