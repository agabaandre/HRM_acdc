<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CostItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $costItems = [
            ['name' => 'Tickets', 'cost_type' => 'Individual Cost'],
            ['name' => 'DSA', 'cost_type' => 'Individual Cost'],
            ['name' => 'Conference', 'cost_type' => 'Other Cost'],
            ['name' => 'Accommodation', 'cost_type' => 'Individual Cost'],
            ['name' => 'Car Hire', 'cost_type' => 'Other Cost'],
            ['name' => 'Visa', 'cost_type' => 'Individual Cost'],
            ['name' => 'Printing of Branded Materials', 'cost_type' => 'Other Cost'],
            ['name' => 'Honorarium', 'cost_type' => 'Other Cost'],
            ['name' => 'Transport Refund', 'cost_type' => 'Individual Cost'],
            ['name' => 'Terminal Fee', 'cost_type' => 'Individual Cost'],
            ['name' => 'Stipend', 'cost_type' => 'Individual Cost'],
            ['name' => 'Interpreter', 'cost_type' => 'Other Cost'],
            ['name' => 'Interpretation equipment', 'cost_type' => 'Other Cost'],
            ['name' => 'Banners', 'cost_type' => 'Other Cost'],
            ['name' => 'Production of training materials', 'cost_type' => 'Other Cost'],
            ['name' => 'Communication', 'cost_type' => 'Other Cost'],
            ['name' => 'Supplies of Medical countermeasures', 'cost_type' => 'Other Cost'],
            ['name' => 'Training Faciitation', 'cost_type' => 'Other Cost'],
            ['name' => 'DSA Advance Team', 'cost_type' => 'Individual Cost'],
            ['name' => 'Ticket Business Class', 'cost_type' => 'Individual Cost'],
            ['name' => 'Accommodation Advance Team', 'cost_type' => 'Individual Cost'],
            ['name' => 'Equipment and Accessories', 'cost_type' => 'Other Cost'],
            ['name' => 'Ticket change fee', 'cost_type' => 'Individual Cost'],
            ['name' => 'Translation', 'cost_type' => 'Other Cost'],
            ['name' => 'Health insurance', 'cost_type' => 'Individual Cost'],
            ['name' => 'Conference Registration', 'cost_type' => 'Individual Cost'],
            ['name' => 'Mobile data', 'cost_type' => 'Individual Cost'],
            ['name' => 'Learning resources', 'cost_type' => 'Other Cost'],
            ['name' => 'Networking', 'cost_type' => 'Individual Cost'],
            ['name' => 'Cocktail', 'cost_type' => 'Other Cost'],
            ['name' => 'Field activities', 'cost_type' => 'Other Cost'],
            ['name' => 'Software purchase', 'cost_type' => 'Other Cost'],
        ];

        foreach ($costItems as $item) {
            DB::table('cost_items')->insert([
                'name' => $item['name'],
                'cost_type' => $item['cost_type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
