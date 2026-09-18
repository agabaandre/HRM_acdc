<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_staff_pay')) {
            Schema::table('payroll_staff_pay', function (Blueprint $table): void {
                if (! Schema::hasColumn('payroll_staff_pay', 'staff_contract_id')) {
                    $table->unsignedInteger('staff_contract_id')->nullable()->after('staff_id');
                }
                if (! Schema::hasColumn('payroll_staff_pay', 'inherited_unverified')) {
                    $table->boolean('inherited_unverified')->default(false)->after('notes');
                }
            });

            $indexes = collect(DB::select('SHOW INDEX FROM payroll_staff_pay'))
                ->pluck('Key_name')
                ->unique()
                ->all();

            if (in_array('payroll_staff_pay_staff_id_unique', $indexes, true)) {
                Schema::table('payroll_staff_pay', function (Blueprint $table): void {
                    $table->dropUnique('payroll_staff_pay_staff_id_unique');
                });
            }

            $indexes = collect(DB::select('SHOW INDEX FROM payroll_staff_pay'))
                ->pluck('Key_name')
                ->unique()
                ->all();

            Schema::table('payroll_staff_pay', function (Blueprint $table) use ($indexes): void {
                if (! in_array('payroll_staff_pay_staff_id_index', $indexes, true)) {
                    $table->index('staff_id');
                }
                if (! in_array('payroll_staff_pay_staff_contract_id_unique', $indexes, true)) {
                    $table->unique('staff_contract_id');
                }
            });

            if (Schema::hasTable('staff_contracts')) {
                $latest = DB::table('staff_contracts')
                    ->select('staff_id', DB::raw('MAX(staff_contract_id) as staff_contract_id'))
                    ->groupBy('staff_id');

                DB::table('payroll_staff_pay as p')
                    ->joinSub($latest, 'lc', function ($join): void {
                        $join->on('lc.staff_id', '=', 'p.staff_id');
                    })
                    ->whereNull('p.staff_contract_id')
                    ->update(['p.staff_contract_id' => DB::raw('lc.staff_contract_id')]);
            }
        }

        if (Schema::hasTable('payroll_staff_wage_items')) {
            Schema::table('payroll_staff_wage_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('payroll_staff_wage_items', 'staff_contract_id')) {
                    $table->unsignedInteger('staff_contract_id')->nullable()->after('staff_id');
                    $table->index(['staff_id', 'staff_contract_id']);
                }
            });

            if (Schema::hasTable('payroll_staff_pay')) {
                DB::table('payroll_staff_wage_items as w')
                    ->join('payroll_staff_pay as p', 'p.staff_id', '=', 'w.staff_id')
                    ->whereNull('w.staff_contract_id')
                    ->whereNotNull('p.staff_contract_id')
                    ->update(['w.staff_contract_id' => DB::raw('p.staff_contract_id')]);
            }
        }
    }

    public function down(): void
    {
        // Keep columns for legacy safety.
    }
};
