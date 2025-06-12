<?php

namespace Database\Seeders;

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
        // Create a test user
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Call our custom seeders in the correct order to maintain relationships
        $this->call([
            // DivisionSeeder::class,        // Seed divisions first
            WorkflowSeeder::class,        // Then seed workflows
            WorkflowDefinitionSeeder::class, // Then workflow definitions
            ApprovalConditionSeeder::class,  // Then approval conditions
            ApproverSeeder::class,        // Then seed approvers
            MemoSeeder::class,            // Finally, seed memos
            CostItemSeeder::class, 
            FundTypesSeeder::class,     
            FundCodesSeeder::class       // Then seed cost items
        ]);
    }
}
