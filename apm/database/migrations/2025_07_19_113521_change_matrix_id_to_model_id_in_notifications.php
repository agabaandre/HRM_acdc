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
     

        Schema::table('notifications', function (Blueprint $table) {
            
            $table->renameColumn('matrix_id','model_id');
            $table->string('model_type')->nullable();

        });

        Schema::table('notifications', function (Blueprint $table) {
            
            $table->unsignedBigInteger('model_id')->nullable()->change();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            //
        });
    }
};
