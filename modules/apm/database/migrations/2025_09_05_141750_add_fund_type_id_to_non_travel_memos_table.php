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
            $table->unsignedBigInteger('fund_type_id')->nullable()->after('division_id');
            $table->foreign('fund_type_id')->references('id')->on('fund_types')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('non_travel_memos', function (Blueprint $table) {
            $table->dropForeign(['fund_type_id']);
            $table->dropColumn('fund_type_id');
        });
    }
};
