<?php

namespace Database\Seeders;

use App\Models\Directorate;
use Illuminate\Database\Seeder;

class DirectorateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $directorates = [
            ['name' => 'IT Directorate', 'is_active' => true],
            ['name' => 'HR Directorate', 'is_active' => true],
            ['name' => 'Finance Directorate', 'is_active' => true],
            ['name' => 'Operations Directorate', 'is_active' => true],
            ['name' => 'Public Health Directorate', 'is_active' => true],
            ['name' => 'Research & Innovation Directorate', 'is_active' => true],
            ['name' => 'Partnerships & Governance Directorate', 'is_active' => true],
        ];

        foreach ($directorates as $directorate) {
            Directorate::create($directorate);
        }
    }
}
