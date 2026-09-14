<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropForeignKeysFromActivitiesTable extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            // Drop all foreign key constraints manually
           
        });
    }

    public function down(): void
    {
        // Recreate foreign key constraints if needed — adjust as required
       
    }
}
