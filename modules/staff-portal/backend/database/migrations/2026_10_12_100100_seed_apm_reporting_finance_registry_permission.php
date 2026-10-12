<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed apm_reporting_finance_registry for APM Reporting & Finance Registry process actions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $name = 'apm_reporting_finance_registry';
        if (DB::table('permissions')->where('name', $name)->exists()) {
            return;
        }

        $row = [
            'name' => $name,
            'definition' => 'APM Reporting & Finance Registry',
        ];

        if (Schema::hasColumn('permissions', 'module')) {
            $row['module'] = 'apm';
        }

        DB::table('permissions')->insert($row);
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')->where('name', 'apm_reporting_finance_registry')->delete();
    }
};
