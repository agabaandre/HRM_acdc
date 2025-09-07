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
        Schema::table('request_arfs', function (Blueprint $table) {
            // Change the column type to match staff.staff_id (int)
            $table->integer('staff_id')->change();
            
            // Add foreign key constraint to reference staff.staff_id
            $table->foreign('staff_id')->references('staff_id')->on('staff');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_arfs', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['staff_id']);
            
            // Change the column type back to bigint unsigned
            $table->unsignedBigInteger('staff_id')->change();
        });
    }
};