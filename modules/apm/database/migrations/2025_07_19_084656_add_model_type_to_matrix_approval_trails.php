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
        Schema::table('matrix_approval_trails', function (Blueprint $table) {
            $table->foreignId('matrix_id')->nullable()->change();
            $table->foreignId('model_id')->nullable()->after('matrix_id');
            $table->string('model_type')->nullable()->after('model_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matrix_approval_trails', function (Blueprint $table) {
            $table->foreignId('matrix_id')->nullable(false)->change();
            $table->dropColumn('model_id');
            $table->dropColumn('model_type');
        });
    }
};
