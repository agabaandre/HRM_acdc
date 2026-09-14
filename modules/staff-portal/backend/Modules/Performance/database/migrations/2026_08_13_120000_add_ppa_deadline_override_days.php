<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ppa_configs')) {
            return;
        }

        Schema::table('ppa_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('ppa_configs', 'ppa_deadline_override_days')) {
                $table->unsignedSmallInteger('ppa_deadline_override_days')->default(0)->after('ppa_deadline');
            }
            if (! Schema::hasColumn('ppa_configs', 'mid_term_deadline_override_days')) {
                $table->unsignedSmallInteger('mid_term_deadline_override_days')->default(0)->after('mid_term_deadline');
            }
            if (! Schema::hasColumn('ppa_configs', 'end_term_deadline_override_days')) {
                $table->unsignedSmallInteger('end_term_deadline_override_days')->default(0)->after('end_term_deadline');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ppa_configs')) {
            return;
        }

        Schema::table('ppa_configs', function (Blueprint $table) {
            foreach ([
                'ppa_deadline_override_days',
                'mid_term_deadline_override_days',
                'end_term_deadline_override_days',
            ] as $column) {
                if (Schema::hasColumn('ppa_configs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
