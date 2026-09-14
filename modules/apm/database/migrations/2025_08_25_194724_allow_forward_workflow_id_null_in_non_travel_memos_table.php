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
            // Allow forward_workflow_id to be null for draft memos
            $table->foreignId('forward_workflow_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('non_travel_memos', function (Blueprint $table) {
            // Revert forward_workflow_id to not nullable
            $table->foreignId('forward_workflow_id')->nullable(false)->change();
        });
    }
};
