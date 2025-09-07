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
            $table->json('internal_participants')->nullable()->after('attachment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_arfs', function (Blueprint $table) {
            $table->dropColumn('internal_participants');
        });
    }
};