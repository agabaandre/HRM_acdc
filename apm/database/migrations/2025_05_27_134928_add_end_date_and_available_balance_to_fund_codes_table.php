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
        Schema::table('fund_codes', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('is_active');
            $table->decimal('available_balance', 15, 2)->nullable()->after('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fund_codes', function (Blueprint $table) {
            $table->dropColumn(['end_date', 'available_balance']);
        });
    }
};
