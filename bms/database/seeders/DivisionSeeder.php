<?php

namespace Database\Seeders;

use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample divisions
        $divisions = [
            [
                'division_name' => 'IT Department',
                'division_head' => 1,
                'focal_person' => 2,
                'admin_assistant' => 3,
                'finance_officer' => 4,
            ],
            [
                'division_name' => 'HR Department',
                'division_head' => 5,
                'focal_person' => 6,
                'admin_assistant' => 7,
                'finance_officer' => 8,
            ],
            [
                'division_name' => 'Finance Department',
                'division_head' => 9,
                'focal_person' => 10,
                'admin_assistant' => 11,
                'finance_officer' => 12,
            ],
            [
                'division_name' => 'Operations Department',
                'division_head' => 13,
                'focal_person' => 14,
                'admin_assistant' => 15,
                'finance_officer' => 16,
            ],
        ];

        // Insert the divisions into the database
        foreach ($divisions as $division) {
            Division::create($division);
        }
    }
}