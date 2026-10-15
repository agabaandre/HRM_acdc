<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fund_codes')) {
            return;
        }
        if (Schema::hasColumn('fund_codes', 'sap_synced_at')) {
            return;
        }

        Schema::table('fund_codes', function (Blueprint $table) {
            $table->timestamp('sap_synced_at')->nullable()->after('uploaded_budget');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fund_codes') || ! Schema::hasColumn('fund_codes', 'sap_synced_at')) {
            return;
        }

        Schema::table('fund_codes', function (Blueprint $table) {
            $table->dropColumn('sap_synced_at');
        });
    }
};
