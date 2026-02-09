<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('fund_codes', 'partner_id')) {
            Schema::table('fund_codes', function (Blueprint $table) {
                $table->unsignedBigInteger('partner_id')->nullable()->after('funder_id');
            });
        }

        $tableName = 'fund_codes';
        $foreignKeyName = 'fund_codes_partner_id_foreign';
        $hasFk = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $tableName)
            ->where('CONSTRAINT_NAME', $foreignKeyName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();

        if (!$hasFk) {
            Schema::table('fund_codes', function (Blueprint $table) {
                $table->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'fund_codes';
        $foreignKeyName = 'fund_codes_partner_id_foreign';
        $hasFk = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $tableName)
            ->where('CONSTRAINT_NAME', $foreignKeyName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();

        Schema::table('fund_codes', function (Blueprint $table) use ($hasFk, $foreignKeyName) {
            if ($hasFk) {
                $table->dropForeign($foreignKeyName);
            }
            if (Schema::hasColumn('fund_codes', 'partner_id')) {
                $table->dropColumn('partner_id');
            }
        });
    }
};
