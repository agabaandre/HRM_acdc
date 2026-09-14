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
            $table->unsignedBigInteger('funder_id')->nullable()->after('fund_type_id');
            $table->string('extramural_code')->nullable()->after('funder_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_arfs', function (Blueprint $table) {
            $table->dropColumn(['funder_id', 'extramural_code']);
        });
    }
};