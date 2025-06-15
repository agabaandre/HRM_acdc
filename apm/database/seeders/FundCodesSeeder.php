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

            DB::table('fund_codes')->insert([
                'year' => $data['year'],
                'code' => $data['code'],
                'activity' => $data['activity'],
                'fund_type_id' => $data['fund_type_id'] ?: null,
                'division_id' => $data['division_id'] ?: null,
                'cost_centre' => $data['Cost Centres'] ?? null,
                'amert_code' => $data['AMERT Code'] ?? null,
                'fund' => $data['Fund'] ?? null,
                'budget' => isset($data['Budget']) ? str_replace(',', '', $data['Budget']) : null,
                'uploaded' => isset($data['Uploaded']) ? str_replace(',', '', $data['Uploaded']) : null,
                'approved_amert' => isset($data['Approved in AMERT']) ? str_replace(',', '', $data['Approved in AMERT']) : null,
                'is_active' => isset($data['is_active']) ? (int) $data['is_active'] === 1 : true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Fund codes seeded successfully.');
    }
}
