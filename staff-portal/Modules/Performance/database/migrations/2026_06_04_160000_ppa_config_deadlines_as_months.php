<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MONTH_COLUMNS = [
        'ppa_start',
        'ppa_deadline',
        'mid_term_start',
        'mid_term_deadline',
        'end_term_start',
        'end_term_deadline',
    ];

    public function up(): void
    {
        foreach (self::MONTH_COLUMNS as $column) {
            if (! Schema::hasColumn('ppa_configs', $column)) {
                continue;
            }

            $tmp = "{$column}_month_tmp";
            if (Schema::hasColumn('ppa_configs', $tmp)) {
                continue;
            }

            DB::statement("ALTER TABLE ppa_configs ADD `{$tmp}` TINYINT UNSIGNED NULL AFTER `{$column}`");
            DB::statement("UPDATE ppa_configs SET `{$tmp}` = MONTH(`{$column}`) WHERE `{$column}` IS NOT NULL");
            DB::statement("ALTER TABLE ppa_configs DROP COLUMN `{$column}`");
            DB::statement("ALTER TABLE ppa_configs CHANGE `{$tmp}` `{$column}` TINYINT UNSIGNED NULL");
        }
    }

    public function down(): void
    {
        $year = (int) date('Y');

        foreach (self::MONTH_COLUMNS as $column) {
            if (! Schema::hasColumn('ppa_configs', $column)) {
                continue;
            }

            $tmp = "{$column}_date_tmp";
            DB::statement("ALTER TABLE ppa_configs ADD `{$tmp}` DATE NULL AFTER `{$column}`");
            DB::statement("
                UPDATE ppa_configs
                SET `{$tmp}` = LAST_DAY(STR_TO_DATE(CONCAT('{$year}-', LPAD(`{$column}`, 2, '0'), '-01'), '%Y-%m-%d'))
                WHERE `{$column}` IS NOT NULL
            ");
            DB::statement("ALTER TABLE ppa_configs DROP COLUMN `{$column}`");
            DB::statement("ALTER TABLE ppa_configs CHANGE `{$tmp}` `{$column}` DATE NULL");
        }
    }
};
