<?php

namespace Modules\Leave\Database\Seeders;

use Illuminate\Database\Seeder;

class LeaveDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(LeavePolicySeeder::class);
    }
}
