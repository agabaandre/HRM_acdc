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
        Schema::table('divisions', function (Blueprint $table) {
            $table->unsignedBigInteger('finance_officer_oic_id')->nullable()->after('finance_officer');
            $table->date('finance_officer_oic_start_date')->nullable()->after('finance_officer_oic_id');
            $table->date('finance_officer_oic_end_date')->nullable()->after('finance_officer_oic_start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            $table->dropColumn([
                'finance_officer_oic_id',
                'finance_officer_oic_start_date',
                'finance_officer_oic_end_date'
            ]);
        });
    }
};