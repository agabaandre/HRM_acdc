<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funders', function (Blueprint $table) {
            $table->string('activity_code_label', 255)->nullable()->after('show_activity_code');
        });
    }

    public function down(): void
    {
        Schema::table('funders', function (Blueprint $table) {
            $table->dropColumn('activity_code_label');
        });
    }
};
