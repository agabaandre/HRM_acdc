<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinancePortfolioDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('fin_portfolio_entries')) {
            return;
        }

        $year = (int) date('Y');
        DB::table('fin_portfolio_entries')->where('budget_year', $year)->delete();

        $demo = [
            'extramural' => [
                ['funder' => 'Partner A', 'grant' => 'Demo Extramural Grant', 'budget' => 34000000, 'execution_pct' => 0],
            ],
            'indirect_cost' => [
                ['funder' => 'Demo Funder', 'grant_name' => 'Sample IC Grant', 'total_grant' => 1656339, 'indirect_earned' => 349802, 'comment' => 'Demo seed'],
            ],
            'administrative_cost' => [
                ['detail' => 'Admin cost earned', 'amount' => 19560],
            ],
            'investments' => [
                ['label' => 'Bank Balance at investment date', 'amount' => 81310888.36],
                ['label' => 'Dedicated Account Balance', 'amount' => 49946628.51],
                ['label' => 'Invested Amount', 'amount' => 16877315.55],
            ],
            'afef' => [
                ['label' => 'AfEF b/f', 'amount' => 20054305],
                ['label' => 'Additional Fund-Ebola', 'amount' => 4500000],
                ['label' => 'Less: Expenses', 'amount' => -1567700],
                ['label' => 'CPHIA Fund Balance', 'amount' => 558330.3],
                ['label' => 'Total AfEF Fund Balance', 'amount' => 23544935.3],
            ],
        ];

        $now = now();
        $inserts = [];
        foreach ($demo as $section => $rows) {
            foreach ($rows as $i => $data) {
                $inserts[] = [
                    'section' => $section,
                    'budget_year' => $year,
                    'sort_order' => $i,
                    'data' => json_encode($data, JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('fin_portfolio_entries')->insert($inserts);
    }
}
