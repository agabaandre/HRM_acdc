<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_sap_imports') && ! Schema::hasColumn('fin_sap_imports', 'period_ym')) {
            Schema::table('fin_sap_imports', function (Blueprint $table) {
                $table->string('period_ym', 7)->nullable()->after('budget_year');
                $table->index(['period_ym', 'is_latest']);
                $table->index(['budget_year', 'period_ym']);
            });

            DB::table('fin_sap_imports')->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $ym = $row->created_at
                        ? date('Y-m', strtotime((string) $row->created_at))
                        : date('Y-m');
                    DB::table('fin_sap_imports')->where('id', $row->id)->update([
                        'period_ym' => $ym,
                    ]);
                }
            });
        }

        if (Schema::hasTable('fin_sap_rows')) {
            Schema::table('fin_sap_rows', function (Blueprint $table) {
                if (! Schema::hasColumn('fin_sap_rows', 'budget_code')) {
                    $table->string('budget_code', 64)->nullable()->after('fund_center')->index();
                }
                if (! Schema::hasColumn('fin_sap_rows', 'cost_center')) {
                    $table->string('cost_center', 64)->nullable()->after('budget_code');
                }
                if (! Schema::hasColumn('fin_sap_rows', 'fund_center_text')) {
                    $table->string('fund_center_text', 255)->nullable()->after('cost_center');
                }
                if (! Schema::hasColumn('fin_sap_rows', 'gl_description')) {
                    $table->string('gl_description', 255)->nullable()->after('gl_account');
                }
                if (! Schema::hasColumn('fin_sap_rows', 'is_expense_row')) {
                    $table->boolean('is_expense_row')->default(false)->after('is_total_row')->index();
                }
                if (! Schema::hasColumn('fin_sap_rows', 'commitment')) {
                    $table->decimal('commitment', 18, 2)->default(0)->after('is_expense_row');
                }
                if (! Schema::hasColumn('fin_sap_rows', 'actual_expenditure')) {
                    $table->decimal('actual_expenditure', 18, 2)->default(0)->after('commitment');
                }
                if (! Schema::hasColumn('fin_sap_rows', 'total_released_budget')) {
                    $table->decimal('total_released_budget', 18, 2)->default(0)->after('actual_expenditure');
                }
            });
        }

        if (Schema::hasTable('fin_sap_fund_centers') && ! Schema::hasColumn('fin_sap_fund_centers', 'budget_code')) {
            Schema::table('fin_sap_fund_centers', function (Blueprint $table) {
                $table->string('budget_code', 128)->nullable()->after('fund_center')->index();
                $table->string('fund_center_text', 255)->nullable()->after('budget_code');
                $table->decimal('commitment', 18, 2)->default(0)->after('released_budget_balance');
                $table->decimal('actual_expenditure', 18, 2)->default(0)->after('commitment');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fin_sap_fund_centers')) {
            Schema::table('fin_sap_fund_centers', function (Blueprint $table) {
                foreach (['budget_code', 'fund_center_text', 'commitment', 'actual_expenditure'] as $col) {
                    if (Schema::hasColumn('fin_sap_fund_centers', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('fin_sap_rows')) {
            Schema::table('fin_sap_rows', function (Blueprint $table) {
                foreach ([
                    'budget_code',
                    'cost_center',
                    'fund_center_text',
                    'gl_description',
                    'is_expense_row',
                    'commitment',
                    'actual_expenditure',
                    'total_released_budget',
                ] as $col) {
                    if (Schema::hasColumn('fin_sap_rows', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('fin_sap_imports') && Schema::hasColumn('fin_sap_imports', 'period_ym')) {
            Schema::table('fin_sap_imports', function (Blueprint $table) {
                $table->dropIndex(['period_ym', 'is_latest']);
                $table->dropIndex(['budget_year', 'period_ym']);
                $table->dropColumn('period_ym');
            });
        }
    }
};
