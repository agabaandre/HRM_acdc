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
        Schema::table('approval_trails', function (Blueprint $table) {
            $table->boolean('is_archived')->default(0)->after('action');
            $table->index(['is_archived', 'model_id', 'model_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_trails', function (Blueprint $table) {
            $table->dropIndex(['is_archived', 'model_id', 'model_type']);
            $table->dropColumn('is_archived');
        });
    }
};