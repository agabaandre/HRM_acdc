<?php

namespace Database\Seeders;

use App\Services\FinanceGeographyService;
use App\Support\PortfolioSectionSchema;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Import Investments & Bank rows from Funding Portfolio workbook mapping.
 * Runs only when fin_portfolio_entries has no investments for the current year.
 */
class FinanceInvestmentsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('fin_portfolio_entries')) {
            return;
        }

        $year = (int) date('Y');
        $existing = DB::table('fin_portfolio_entries')
            ->where('section', 'investments')
            ->where('budget_year', $year)
            ->get(['id', 'data']);
        $hasMapped = $existing->contains(function ($row) {
            $data = json_decode((string) $row->data, true);

            return is_array($data) && ! empty($data['country_id']);
        });
        // Only import when no mapped Investments & Bank rows exist yet.
        if ($hasMapped) {
            return;
        }
        if ($existing->isNotEmpty()) {
            DB::table('fin_portfolio_entries')
                ->where('section', 'investments')
                ->where('budget_year', $year)
                ->delete();
        }

        $geo = new FinanceGeographyService;
        $geo->seedRegionsIfEmpty();
        $geo->ensureRegionAliases();

        $countriesById = [];
        foreach ($geo->countries() as $c) {
            $countriesById[$c['id']] = $c;
        }
        $regionsById = [];
        foreach ($geo->regions() as $r) {
            $regionsById[$r['id']] = $r;
        }
        $locationsById = [];
        foreach ($geo->locations() as $l) {
            $locationsById[$l['id']] = $l;
        }

        // From Africa CDC Funding Portfolio.xlsx → "5. Investment Update & Bank Bal"
        $rows = [
            ['gl' => '167000', 'account' => 'AU- Africa Centre for Disease Control – FCY', 'bank' => 'CBE', 'number' => '1000148122923', 'type' => 'Partner Account', 'location' => 'Africa', 'country' => 'Ethiopia', 'region' => 'East Africa', 'currency' => 'USD', 'balance' => 13558204.72],
            ['gl' => '167010', 'account' => 'Africa Center for Disease Control & Prevention CDC', 'bank' => 'CBE', 'number' => '1000676580728', 'type' => 'Partner Account', 'location' => 'Africa', 'country' => 'Ethiopia', 'region' => 'East Africa', 'currency' => 'ETB', 'balance' => 40495.95],
            ['gl' => '167030', 'account' => 'AU-Africa Against Ebola FCY CBE', 'bank' => 'CBE', 'number' => '1000296999931', 'type' => 'Partner Account', 'location' => 'Africa', 'country' => 'Ethiopia', 'region' => 'East Africa', 'currency' => 'USD', 'balance' => 170000],
            ['gl' => '167040', 'account' => 'WB Support - Africa CDC - FCY', 'bank' => 'CBE', 'number' => '1000332426827', 'type' => 'Partner Account', 'location' => 'Africa', 'country' => 'Ethiopia', 'region' => 'East Africa', 'currency' => 'USD', 'balance' => 5303144.8],
            ['gl' => '167060', 'account' => 'African Union COVID-19 Response Fund', 'bank' => 'Standard Bank, SA', 'number' => '90846613', 'type' => 'Partner Account', 'location' => 'Africa', 'country' => 'South Africa', 'region' => 'Southern Africa', 'currency' => 'USD', 'balance' => 3179843.51],
            ['gl' => '167070', 'account' => 'AU Support Against Covid-19', 'bank' => 'ECOBANK Kenya, LTD', 'number' => '6640002533', 'type' => 'Partner Account', 'location' => 'Africa', 'country' => 'Kenya', 'region' => 'East Africa', 'currency' => 'USD', 'balance' => 2564217.25],
            ['gl' => '167120', 'account' => 'Africa Center for Disease Control & Prevention CDC', 'bank' => 'Dashen', 'number' => '1144355935012', 'type' => 'Member States', 'location' => 'Africa', 'country' => 'Ethiopia', 'region' => 'East Africa', 'currency' => 'USD', 'balance' => 248893],
            ['gl' => '167160', 'account' => 'Africa CDC-General Fund Account', 'bank' => 'ECOBANK Kenya, LTD', 'number' => '6640004891', 'type' => 'Partner Account', 'location' => 'Africa', 'country' => 'Kenya', 'region' => 'East Africa', 'currency' => 'USD', 'balance' => 3682171.42],
            ['gl' => '167180', 'account' => 'Africa CDC-Regional Coordination Centre -General Account', 'bank' => 'ECOBANK Zambia, LTD', 'number' => '5615000012186', 'type' => 'Partner Account', 'location' => 'Africa', 'country' => 'Zambia', 'region' => 'Southern Africa', 'currency' => 'USD', 'balance' => 121402.77],
            ['gl' => '167190', 'account' => 'Africa CDC-Regional Coordination Centre -General Account', 'bank' => 'ECOBANK Gabon, LTD', 'number' => '36188017137', 'type' => 'Partner Account', 'location' => 'Africa', 'country' => 'Gabon', 'region' => 'Central Africa', 'currency' => 'USD', 'balance' => 219400],
            ['gl' => '167220', 'account' => 'Africa CDC-Gavi Joint Action Plan Account', 'bank' => 'AFREXIM BANK', 'number' => '500072801220001', 'type' => 'Partner Account', 'location' => 'Africa', 'country' => 'Egypt', 'region' => 'North Africa', 'currency' => 'USD', 'balance' => 5485028.41],
            ['gl' => '167230', 'account' => 'Africa CDC-CEPI Joint Action Plan Account', 'bank' => 'AFREXIM BANK', 'number' => '500072801220003', 'type' => 'Partner Account', 'location' => 'Africa', 'country' => 'Egypt', 'region' => 'North Africa', 'currency' => 'USD', 'balance' => 3242497.01],
            ['gl' => null, 'account' => 'Africa CDC-Western Regional Coordination Centre -General Account', 'bank' => 'Ecobank', 'number' => '3360032514', 'type' => 'Partner Account', 'location' => 'Africa', 'country' => 'Nigeria', 'region' => 'West Africa', 'currency' => 'USD', 'balance' => 222903.1],
            ['gl' => null, 'account' => 'Africa CDC (DRC)', 'bank' => 'Ecobank DRC', 'number' => '35080093684', 'type' => 'Partner Account', 'location' => 'Africa', 'country' => 'DRC', 'region' => 'East/Central Africa', 'currency' => 'USD', 'balance' => 648177.91],
        ];

        $now = now();
        $inserts = [];
        foreach ($rows as $i => $row) {
            $countryId = $geo->resolveCountryId($row['country']);
            $country = $countryId ? ($countriesById[$countryId] ?? null) : null;
            $regionId = $geo->resolveRegionId($row['region'])
                ?? ($country['region_id'] ?? null);
            $region = $regionId ? ($regionsById[$regionId] ?? null) : null;
            $locationId = $geo->resolveLocationId($row['country'], $row['location']);
            $location = $locationId ? ($locationsById[$locationId] ?? null) : null;

            $data = array_merge(PortfolioSectionSchema::emptyRow('investments'), [
                'gl_account' => $row['gl'] ?? '',
                'account_name' => $row['account'],
                'bank_name' => $row['bank'],
                'account_number' => (string) $row['number'],
                'account_type' => $row['type'],
                'location_id' => $locationId,
                'location' => $location['name']
                    ?? (strcasecmp(trim($row['location']), 'Africa') === 0 ? '' : trim($row['location'])),
                'country_id' => $countryId,
                'country' => $country['name'] ?? $row['country'],
                'region_id' => $regionId,
                'region' => $region['name'] ?? $row['region'],
                'currency' => trim($row['currency']),
                'bank_balance_usd' => $row['balance'],
                'invested_amount' => 0,
                'comments' => 'Imported from Funding Portfolio · Investment Update & Bank Bal',
            ]);

            $inserts[] = [
                'section' => 'investments',
                'budget_year' => $year,
                'sort_order' => $i,
                'data' => json_encode($data, JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($inserts !== []) {
            DB::table('fin_portfolio_entries')->insert($inserts);
        }
    }
}
