<?php

namespace Database\Seeders;

use App\Models\HelpdeskProfile;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Helpdesk Admin',
            'email' => 'helpdesk.admin@africacdc.local',
        ])->helpdeskProfile()->create([
            'staff_id' => 100001,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'directorate_id' => null,
            'division_id' => null,
            'synced_at' => now(),
        ]);

        $this->call(HelpdeskCategorySeeder::class);
    }
}
