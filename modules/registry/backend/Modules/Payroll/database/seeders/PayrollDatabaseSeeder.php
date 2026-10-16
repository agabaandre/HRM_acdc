<?php

namespace Modules\Payroll\Database\Seeders;

use Illuminate\Database\Seeder;

class PayrollDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PayrollPermissionsSeeder::class,
        ]);
    }
}
