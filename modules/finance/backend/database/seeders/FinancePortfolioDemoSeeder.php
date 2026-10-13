<?php

namespace Database\Seeders;

use App\Services\FinanceApmOrgService;
use App\Support\PortfolioSectionSchema;
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

        (new FinanceGrantsSeeder)->run();

        $org = new FinanceApmOrgService;
        try {
            $org->fetchFromApm();
        } catch (\Throwable) {
            // Offline / missing APM DB — continue with plain seed rows.
        }
        $funderId = fn (string $label) => $org->resolveFunderId($label);
        $funderName = function (string $label) use ($org, $funderId): string {
            $id = $funderId($label);
            if (! $id) {
                return $label;
            }
            foreach ($org->apmFunders(false) as $f) {
                if ($f['id'] === $id) {
                    return $f['name'];
                }
            }

            return $label;
        };
        $grantByName = function (string $name): ?array {
            if (! Schema::hasTable('fin_grants')) {
                return null;
            }
            $row = DB::table('fin_grants')->where('name', $name)->first();
            if (! $row) {
                $row = DB::table('fin_grants')->where('name', 'like', $name.'%')->first();
            }

            return $row ? (array) $row : null;
        };

        // Extramural totals inspired by Fund Portfolio Summary (Approved ~46.1m, funding balance 34m).
        $demo = [
            'extramural' => [
                array_merge(PortfolioSectionSchema::emptyRow('extramural'), [
                    'funder_id' => $funderId('World Bank'),
                    'funder' => $funderName('World Bank'),
                    'grant_id' => ($g = $grantByName('WB Support Africa CDC (extramural portion)'))['id'] ?? null,
                    'grant' => 'WB Support Africa CDC (extramural portion)',
                    'approved_budget' => 18000000,
                    'released_budget' => 0,
                    'commitment' => 0,
                    'actual_expenditure' => 0,
                    'budget_funding_balance' => 12000000,
                    'execution_pct' => 0,
                    'comments' => 'Mapped from Funding Portfolio Summary / receipts',
                ]),
                array_merge(PortfolioSectionSchema::emptyRow('extramural'), [
                    'funder_id' => $funderId('Gavi'),
                    'funder' => $funderName('Gavi'),
                    'grant_id' => ($g = $grantByName('Africa CDC-Gavi Joint Action Plan'))['id'] ?? null,
                    'grant' => 'Africa CDC-Gavi Joint Action Plan',
                    'approved_budget' => 8500000,
                    'released_budget' => 0,
                    'budget_funding_balance' => 6500000,
                    'execution_pct' => 0,
                ]),
                array_merge(PortfolioSectionSchema::emptyRow('extramural'), [
                    'funder_id' => $funderId('CEPI'),
                    'funder' => $funderName('CEPI'),
                    'grant_id' => ($g = $grantByName('CEPI – Africa CDC Funding Agreement'))['id'] ?? null,
                    'grant' => 'CEPI – Africa CDC Funding Agreement',
                    'approved_budget' => 7200000,
                    'released_budget' => 0,
                    'budget_funding_balance' => 5500000,
                    'execution_pct' => 0,
                ]),
                array_merge(PortfolioSectionSchema::emptyRow('extramural'), [
                    'funder_id' => $funderId('Susan Thompson Buffet Foundation (STBF)'),
                    'funder' => $funderName('Susan Thompson Buffet Foundation (STBF)'),
                    'grant_id' => ($g = $grantByName('STBF health workforce / SRHR'))['id'] ?? null,
                    'grant' => 'STBF health workforce / SRHR',
                    'approved_budget' => 5000000,
                    'released_budget' => 0,
                    'budget_funding_balance' => 4000000,
                    'execution_pct' => 0,
                ]),
                array_merge(PortfolioSectionSchema::emptyRow('extramural'), [
                    'funder_id' => $funderId('European CDC'),
                    'funder' => $funderName('European CDC'),
                    'grant_id' => ($g = $grantByName('EU for Health security in Africa'))['id'] ?? null,
                    'grant' => 'EU for Health security in Africa (ECDC 4 Africa CDC)',
                    'approved_budget' => 3200000,
                    'released_budget' => 0,
                    'budget_funding_balance' => 2500000,
                    'execution_pct' => 0,
                ]),
                array_merge(PortfolioSectionSchema::emptyRow('extramural'), [
                    'funder_id' => $funderId('KDCA'),
                    'funder' => $funderName('KDCA'),
                    'grant_id' => ($g = $grantByName('KDCA capacity strengthening'))['id'] ?? null,
                    'grant' => 'KDCA capacity strengthening',
                    'approved_budget' => 2199513.07,
                    'released_budget' => 0,
                    'budget_funding_balance' => 1500000,
                    'execution_pct' => 0,
                    'comments' => 'Balances to Summary Approved Budget 46,099,513.07',
                ]),
            ],
            'indirect_cost' => [
                array_merge(PortfolioSectionSchema::emptyRow('indirect_cost'), [
                    'funder_id' => $funderId('CEPI'),
                    'funder' => $funderName('CEPI'),
                    'grant_id' => ($g = $grantByName('CEPI – Africa CDC Funding to Improve Vaccine Research'))['id'] ?? null,
                    'grant_name' => $g['name'] ?? 'CEPI – Africa CDC Funding to Improve Vaccine Research, Development and Manufacture in Africa',
                    'end_date' => '2027-09-30',
                    'total_grant' => 20000000,
                    'direct_cost_usd' => 17391304,
                    'acdc_indirect_cost' => 2608696,
                    'indirect_pct' => 0.15,
                    'funds_received' => 12586010.01,
                    'total_expenses' => 4948826,
                    'indirect_to_earn' => 742324,
                    'budget_code' => 'CDC0202015MB',
                    'indirect_committed' => 400000,
                    'indirect_available' => 10978.98,
                ]),
                array_merge(PortfolioSectionSchema::emptyRow('indirect_cost'), [
                    'funder_id' => $funderId('Gavi'),
                    'funder' => $funderName('Gavi'),
                    'grant_id' => ($g = $grantByName('Support for sustainable systems for immunisation'))['id'] ?? null,
                    'grant_name' => $g['name'] ?? 'Support for sustainable systems for immunisation going forward',
                    'end_date' => '2026-07-31',
                    'total_grant' => 12095000,
                    'direct_cost_usd' => 11303738,
                    'acdc_indirect_cost' => 791262,
                    'indirect_pct' => 0.07,
                    'funds_received' => 12095000,
                    'total_expenses' => 7413569,
                    'budget_code' => 'CDC0202011MB',
                ]),
                array_merge(PortfolioSectionSchema::emptyRow('indirect_cost'), [
                    'funder_id' => $funderId('European CDC'),
                    'funder' => $funderName('European CDC'),
                    'grant_id' => ($g = $grantByName('EU for Health security in Africa'))['id'] ?? null,
                    'grant_name' => $g['name'] ?? 'EU for Health security in Africa: ECDC 4 Africa CDC (Human resources component)',
                    'end_date' => '2026-04-30',
                    'total_grant' => 1284000,
                    'direct_cost_usd' => 1200000,
                    'acdc_indirect_cost' => 84000,
                    'indirect_pct' => 0.07,
                ]),
                array_merge(PortfolioSectionSchema::emptyRow('indirect_cost'), [
                    'funder_id' => $funderId('KDCA'),
                    'funder' => $funderName('KDCA'),
                    'grant_id' => ($g = $grantByName('Strengthening AU member states capacity'))['id'] ?? null,
                    'grant_name' => $g['name'] ?? 'Strengthening AU member states capacity and capabilities to detect, prevent and respond to public health emergencies',
                    'end_date' => '2027-10-30',
                    'total_grant' => 3231000,
                    'direct_cost_usd' => 3077143,
                    'acdc_indirect_cost' => 153857,
                    'indirect_pct' => 0.05,
                ]),
            ],
            'administrative_cost' => [
                array_merge(PortfolioSectionSchema::emptyRow('administrative_cost'), [
                    'description' => 'Hall/Conference Hire',
                    'amount' => 5360,
                    'text' => '17.02.26 - 21.02.2026',
                    'currency' => 'USD',
                    'funds_center' => 'CDC101205MB',
                    'fund' => 'PB-MST01',
                ]),
                array_merge(PortfolioSectionSchema::emptyRow('administrative_cost'), [
                    'description' => 'Cost Recovery for Meetings (Total)',
                    'amount' => 19560,
                    'currency' => 'USD',
                ]),
            ],
            'investments' => [
                array_merge(PortfolioSectionSchema::emptyRow('investments'), [
                    'gl_account' => '167000',
                    'account_name' => 'AU- Africa Centre for Disease Control – FCY',
                    'bank_name' => 'CBE',
                    'account_number' => '1000148122923',
                    'account_type' => 'Partner Account',
                    'country' => 'Ethiopia',
                    'region' => 'East Africa',
                    'currency' => 'USD',
                    'bank_balance_usd' => 11875911.27,
                    'invested_amount' => 3000000,
                ]),
                array_merge(PortfolioSectionSchema::emptyRow('investments'), [
                    'account_name' => 'Dedicated Account Balance (summary)',
                    'bank_balance_usd' => 49946628.51,
                    'invested_amount' => 0,
                ]),
                array_merge(PortfolioSectionSchema::emptyRow('investments'), [
                    'account_name' => 'Invested Amount (summary)',
                    'bank_balance_usd' => 0,
                    'invested_amount' => 16877315.55,
                ]),
            ],
            'afef' => [
                array_merge(PortfolioSectionSchema::emptyRow('afef'), [
                    'category' => 'opening',
                    'label' => 'AfEF b/f',
                    'amount' => 20054305,
                ]),
                array_merge(PortfolioSectionSchema::emptyRow('afef'), [
                    'category' => 'income',
                    'label' => 'Additional Fund-Ebola',
                    'notes' => '1',
                    'amount' => 4500000,
                ]),
                array_merge(PortfolioSectionSchema::emptyRow('afef'), [
                    'category' => 'expense',
                    'label' => 'Less: Expenses',
                    'notes' => '2',
                    'amount' => -1567700,
                ]),
                array_merge(PortfolioSectionSchema::emptyRow('afef'), [
                    'category' => 'cphia',
                    'label' => 'CPHIA Fund Balance',
                    'amount' => 558330.3,
                ]),
                array_merge(PortfolioSectionSchema::emptyRow('afef'), [
                    'category' => 'balance',
                    'label' => 'Total AfEF Fund Balance',
                    'amount' => 23544935.3,
                ]),
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
