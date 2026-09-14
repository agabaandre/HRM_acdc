<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('special_memos', function (Blueprint $table) {
            $table->boolean('request_travel_with_cash')->default(false)->after('supporting_reasons');
            $table->unsignedInteger('cash_carrier_staff_id')->nullable()->after('request_travel_with_cash');
            $table->text('cash_bank_transfer_unavailable_reason')->nullable()->after('cash_carrier_staff_id');
        });
    }

    public function down(): void
    {
        Schema::table('special_memos', function (Blueprint $table) {
            $table->dropColumn([
                'request_travel_with_cash',
                'cash_carrier_staff_id',
                'cash_bank_transfer_unavailable_reason',
            ]);
        });
    }
};
