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
            $table->unsignedBigInteger('forward_workflow_id')->nullable()->change();
            $table->unsignedBigInteger('reverse_workflow_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_arfs', function (Blueprint $table) {
            $table->unsignedBigInteger('forward_workflow_id')->nullable(false)->change();
            $table->unsignedBigInteger('reverse_workflow_id')->nullable(false)->change();
        });
    }
};