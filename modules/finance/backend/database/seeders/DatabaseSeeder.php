<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Production installs must not create demo users (factories need faker / composer --dev).
     */
    public function run(): void
    {
        // Production-safe upserts (no demo users / faker). Seeders skip when data exists.
        $this->call(FinanceGrantsSeeder::class);
        $this->call(FinanceInvestmentsSeeder::class);
    }
}
