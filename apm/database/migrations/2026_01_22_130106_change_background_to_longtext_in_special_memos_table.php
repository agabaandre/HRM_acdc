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
            if (Schema::hasColumn('special_memos', 'background')) {
                $table->longText('background')->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('special_memos', function (Blueprint $table) {
            if (Schema::hasColumn('special_memos', 'background')) {
                $table->text('background')->change();
            }
        });
    }
};
