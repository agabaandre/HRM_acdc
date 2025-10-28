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
        Schema::table('change_request', function (Blueprint $table) {
            $table->boolean('has_date_stayed_quarter')->default(false)->after('has_memo_date_changed');
            $table->boolean('has_number_of_participants_changed')->default(false)->after('has_internal_participants_changed');
            $table->boolean('has_participant_days_changed')->default(false)->after('has_number_of_participants_changed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('change_request', function (Blueprint $table) {
            $table->dropColumn([
                'has_date_stayed_quarter',
                'has_number_of_participants_changed',
                'has_participant_days_changed'
            ]);
        });
    }
};
