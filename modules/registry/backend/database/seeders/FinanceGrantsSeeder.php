<?php

namespace Database\Seeders;

use App\Services\FinanceApmOrgService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upserts Funding Portfolio grants (Indirect Cost + Extramural catalogue).
 * Safe for production — idempotent by grant name.
 */
class FinanceGrantsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('fin_grants')) {
            return;
        }

        $org = new FinanceApmOrgService;
        try {
            $org->fetchFromApm();
        } catch (\Throwable) {
            // Offline APM — resolveFunderId may still match existing aliases.
        }

        $resolve = function (string $label) use ($org): array {
            $id = $org->resolveFunderId($label);
            $name = $label;
            if ($id) {
                foreach ($org->apmFunders(false) as $f) {
                    if ($f['id'] === $id) {
                        $name = $f['name'];
                        break;
                    }
                }
            }

            return ['funder_id' => $id, 'funder_name' => $name];
        };

        // From Africa CDC Funding Portfolio.xlsx → "3. Indirect Cost" (+ extramural programme names).
        $catalog = [
            [
                'name' => 'CEPI – Africa CDC Funding to Improve Vaccine Research, Development and Manufacture in Africa',
                'short_name' => 'CEPI JAP',
                'funder_label' => 'CEPI',
                'end_date' => '2027-09-30',
                'total_budget' => 20000000,
                'direct_cost_usd' => 17391304,
                'indirect_cost' => 2608696,
                'indirect_pct' => 0.15,
                'budget_code' => 'CDC0202015MB',
                'notes' => 'Indirect Cost sheet · Africa CDC - CEPI JAP',
            ],
            [
                'name' => 'Support for sustainable systems for immunisation going forward',
                'short_name' => 'Gavi JAP 5.0/5.1',
                'funder_label' => 'Gavi',
                'end_date' => '2026-07-31',
                'total_budget' => 12095000,
                'direct_cost_usd' => 11303738,
                'indirect_cost' => 791262,
                'indirect_pct' => 0.07,
                'budget_code' => 'CDC0202011MB',
                'notes' => 'Indirect Cost sheet · GAVI strategic period',
            ],
            [
                'name' => 'EU for Health security in Africa: ECDC 4 Africa CDC (Human resources component)',
                'short_name' => 'ECDC 4 Africa CDC',
                'funder_label' => 'European CDC',
                'end_date' => '2026-04-30',
                'total_budget' => 1284000,
                'direct_cost_usd' => 1200000,
                'indirect_cost' => 84000,
                'indirect_pct' => 0.07,
                'budget_code' => null,
            ],
            [
                'name' => 'Strengthening AU member states capacity and capabilities to detect, prevent and respond to public health emergencies',
                'short_name' => 'KDCA capacity',
                'funder_label' => 'KDCA',
                'end_date' => '2027-10-30',
                'total_budget' => 3231000,
                'direct_cost_usd' => 3077143,
                'indirect_cost' => 153857,
                'indirect_pct' => 0.05,
                'budget_code' => null,
            ],
            [
                'name' => 'Developing Health Workforce to Safeguard SRHR during Public Health Emergencies',
                'short_name' => 'STBF SRHR workforce',
                'funder_label' => 'Susan Thompson Buffet Foundation (STBF)',
                'end_date' => '2027-06-30',
                'total_budget' => 10000000,
                'direct_cost_usd' => 9090909,
                'indirect_cost' => 909091,
                'indirect_pct' => 0.1,
                'budget_code' => null,
            ],
            [
                'name' => 'Support to Africa CDC',
                'short_name' => 'US CDC Support',
                'funder_label' => 'US CDC Support to AfCDC',
                'end_date' => '2026-09-30',
                'total_budget' => 650000,
                'direct_cost_usd' => 607477,
                'indirect_cost' => 45500,
                'indirect_pct' => 0.07,
                'budget_code' => null,
            ],
            [
                'name' => 'Advancing the implementation of the African Union-Africa CDC strategy on Non-communicable Diseases (NCDs), Injuries and Mental Health',
                'short_name' => 'WDF NCD strategy',
                'funder_label' => 'World Diabetes Fund',
                'end_date' => '2026-12-31',
                'total_budget' => 511264,
                'direct_cost_usd' => 524959,
                'indirect_cost' => 62995,
                'indirect_pct' => 0.12,
                'budget_code' => null,
            ],
            [
                'name' => 'Tackling Deadly Diseases in Africa 2 (TDDAP2) FCDO Project Number: 300585',
                'short_name' => 'FCDO TDDAP2',
                'funder_label' => 'FCDO',
                'end_date' => '2027-03-31',
                'total_budget' => 2944289,
                'direct_cost_usd' => 2676626,
                'indirect_cost' => 267663,
                'indirect_pct' => 0.1,
                'budget_code' => null,
            ],
            [
                'name' => 'Fighting antimicrobial resistance and strengthening the One Health workforce in Africa',
                'short_name' => 'EDCTP One Health',
                'funder_label' => 'EDCTP',
                'end_date' => '2030-11-30',
                'total_budget' => 19636992,
                'direct_cost_usd' => 18352329,
                'indirect_cost' => 1284663,
                'indirect_pct' => 0.07,
                'budget_code' => null,
            ],
            [
                'name' => 'Safeguarding the health and resilience of populations in AU Member States',
                'short_name' => 'Mission of Japan',
                'funder_label' => 'Mission of Japan',
                'end_date' => '2026-12-31',
                'total_budget' => 2360000,
                'direct_cost_usd' => 2205607,
                'indirect_cost' => 154393,
                'indirect_pct' => 0.07,
                'budget_code' => null,
            ],
            [
                'name' => 'WB Support Africa CDC (extramural portion)',
                'short_name' => 'World Bank extramural',
                'funder_label' => 'World Bank',
                'end_date' => null,
                'total_budget' => 18000000,
                'notes' => 'Extramural portfolio entry',
            ],
            [
                'name' => 'Africa CDC-Gavi Joint Action Plan',
                'short_name' => 'Gavi JAP',
                'funder_label' => 'Gavi',
                'end_date' => null,
                'total_budget' => 8500000,
                'notes' => 'Extramural portfolio entry',
            ],
            [
                'name' => 'CEPI – Africa CDC Funding Agreement',
                'short_name' => 'CEPI Funding Agreement',
                'funder_label' => 'CEPI',
                'end_date' => null,
                'total_budget' => 7200000,
                'notes' => 'Extramural portfolio entry',
            ],
            [
                'name' => 'STBF health workforce / SRHR',
                'short_name' => 'STBF SRHR',
                'funder_label' => 'Susan Thompson Buffet Foundation (STBF)',
                'end_date' => null,
                'total_budget' => 5000000,
                'notes' => 'Extramural portfolio entry',
            ],
            [
                'name' => 'KDCA capacity strengthening',
                'short_name' => 'KDCA strengthening',
                'funder_label' => 'KDCA',
                'end_date' => null,
                'total_budget' => 2199513.07,
                'notes' => 'Extramural portfolio entry',
            ],
        ];

        $now = now();
        foreach ($catalog as $row) {
            $funder = $resolve((string) $row['funder_label']);
            $payload = [
                'short_name' => $row['short_name'] ?? null,
                'funder_id' => $funder['funder_id'],
                'funder_name' => $funder['funder_name'],
                'end_date' => $row['end_date'] ?? null,
                'total_budget' => $row['total_budget'] ?? null,
                'direct_cost_usd' => $row['direct_cost_usd'] ?? null,
                'indirect_cost' => $row['indirect_cost'] ?? null,
                'indirect_pct' => $row['indirect_pct'] ?? null,
                'budget_code' => isset($row['budget_code']) ? trim((string) $row['budget_code']) ?: null : null,
                'status' => 'active',
                'is_active' => true,
                'notes' => $row['notes'] ?? null,
                'updated_at' => $now,
            ];

            $existing = DB::table('fin_grants')->where('name', $row['name'])->first();
            if ($existing) {
                DB::table('fin_grants')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('fin_grants')->insert(array_merge($payload, [
                    'name' => $row['name'],
                    'created_at' => $now,
                ]));
            }
        }
    }
}
