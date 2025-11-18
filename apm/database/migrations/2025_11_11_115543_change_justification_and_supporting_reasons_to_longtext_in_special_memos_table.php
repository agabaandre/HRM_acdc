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
        Schema::table('special_memos', function (Blueprint $table) {
            $table->longText('justification')->nullable()->change();
            $table->longText('supporting_reasons')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('special_memos', function (Blueprint $table) {
            $table->text('justification')->nullable()->change();
            $table->text('supporting_reasons')->nullable()->change();
        });
    }
};
