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
        Schema::table('activity_approval_trails', function (Blueprint $table) {
            $table->decimal('amount_allocated', 15, 2)->nullable()->after('remarks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_approval_trails', function (Blueprint $table) {
            $table->dropColumn('amount_allocated');
        });
    }
};
