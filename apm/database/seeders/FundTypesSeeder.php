<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FundTypesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('fund_types')->insert([
            [
                'id' => 1,
                'name' => 'Intramural',
                'description' => 'Funds from internal institutional sources',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Extramural',
                'description' => 'Funds provided by external donors or partners',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'Intramural Shared',
                'description' => 'Internally managed funds shared by divisions',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'name' => 'External Source',
                'description' => 'Funds and support originating from external sources',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
