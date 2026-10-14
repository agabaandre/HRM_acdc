<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('divisions')) {
            return;
        }

        Schema::table('divisions', function (Blueprint $table) {
            if (! Schema::hasColumn('divisions', 'finance_officer_oic_id')) {
                $table->unsignedBigInteger('finance_officer_oic_id')->nullable()->after('finance_officer');
            }
            if (! Schema::hasColumn('divisions', 'finance_officer_oic_start_date')) {
                $table->date('finance_officer_oic_start_date')->nullable()->after('finance_officer_oic_id');
            }
            if (! Schema::hasColumn('divisions', 'finance_officer_oic_end_date')) {
                $table->date('finance_officer_oic_end_date')->nullable()->after('finance_officer_oic_start_date');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('divisions')) {
            return;
        }

        Schema::table('divisions', function (Blueprint $table) {
            foreach (['finance_officer_oic_end_date', 'finance_officer_oic_start_date', 'finance_officer_oic_id'] as $col) {
                if (Schema::hasColumn('divisions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
