<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FundCodesSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/intramural.csv');

        if (!file_exists($path)) {
            $this->command->error("CSV file not found at: $path");
            return;
        }

        $rows = array_map('str_getcsv', file($path));
        $header = array_map('trim', array_shift($rows)); // Skip header

        foreach ($rows as $row) {
            $row = array_map(function ($value) {
                // Convert encoding and trim
                return trim(mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1'));
            }, $row);

            $data = array_combine($header, $row);
            $idata = array(
                'funder_id' => $data['funder_id'],
                'year' => $data['year'],
                'code' => $data['code'],
                'activity' => $data['activity'],
                'fund_type_id' => $data['fund_type_id'] ?: null,
                'division_id' => $data['division_id'] ?: null,
                'cost_centre' => $data['Cost Centres'] ?? null,
                'amert_code' => $data['AMERT Code'] ?? null,
                'fund' => $data['Fund'] ?? null,
                'budget_balance' => isset($data['budget_balance']) ? str_replace(',', '', $data['budget_balance']) : null,
                'uploaded_budget' => isset($data['uploaded_budget']) ? str_replace(',', '', $data['uploaded_budget']) : null,
                'approved_budget' => isset($data['budget_approved']) ? str_replace(',', '', $data['budget_approved']) : null,
                'is_active' => isset($data['is_active']) ? (int) $data['is_active'] === 1 : true,
                'created_at' => now(),
                'updated_at' => now(),
            );
          //  dd($data);

            DB::table('fund_codes')->insert($idata);
          //  dd($data);
        }

        $this->command->info('Fund codes seeded successfully.');
    }
}
