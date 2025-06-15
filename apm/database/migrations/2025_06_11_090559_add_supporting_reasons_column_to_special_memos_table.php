<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('special_memos', function (Blueprint $table) {
            $table->text('supporting_reasons')->nullable()->after('justification');
        });
    }

    public function down(): void
    {
        Schema::table('special_memos', function (Blueprint $table) {
            $table->dropColumn('supporting_reasons');
        });
    }
};
