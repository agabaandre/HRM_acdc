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
        Schema::table('service_requests', function (Blueprint $table) {
            $table->text('internal_participants_comment')->nullable()->after('internal_participants_cost');
            $table->text('external_participants_comment')->nullable()->after('external_participants_cost');
            $table->text('other_costs_comment')->nullable()->after('other_costs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn([
                'internal_participants_comment',
                'external_participants_comment',
                'other_costs_comment'
            ]);
        });
    }
};