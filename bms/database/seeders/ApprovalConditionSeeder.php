<?php

namespace Database\Seeders;

use App\Models\ApprovalCondition;
use Illuminate\Database\Seeder;

class ApprovalConditionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample approval conditions
        $conditions = [
            // Purchase Request approval conditions
            [
                'workflow_id' => 1,
                'column_name' => 'amount',
                'operator' => '>',
                'value' => '50000',
                'workflow_definition_id' => 3,
                'flow_type' => 'forward',
                'is_enabled' => 1
            ],
            [
                'workflow_id' => 1,
                'column_name' => 'amount',
                'operator' => '<=',
                'value' => '50000',
                'workflow_definition_id' => 2,
                'flow_type' => 'forward',
                'is_enabled' => 1
            ],

            // Leave Application conditions
            [
                'workflow_id' => 2,
                'column_name' => 'days',
                'operator' => '>',
                'value' => '5',
                'workflow_definition_id' => 5,
                'flow_type' => 'forward',
                'is_enabled' => 1
            ],
            [
                'workflow_id' => 2,
                'column_name' => 'leave_type',
                'operator' => '=',
                'value' => 'medical',
                'workflow_definition_id' => 5,
                'flow_type' => 'forward',
                'is_enabled' => 1
            ],

            // Travel Authorization conditions
            [
                'workflow_id' => 3,
                'column_name' => 'destination_type',
                'operator' => '=',
                'value' => 'international',
                'workflow_definition_id' => 8,
                'flow_type' => 'forward',
                'is_enabled' => 1
            ],
            [
                'workflow_id' => 3,
                'column_name' => 'estimated_cost',
                'operator' => '>',
                'value' => '100000',
                'workflow_definition_id' => 7,
                'flow_type' => 'forward',
                'is_enabled' => 1
            ],

            // Budget Request conditions
            [
                'workflow_id' => 4,
                'column_name' => 'budget_amount',
                'operator' => '>',
                'value' => '1000000',
                'workflow_definition_id' => 11,
                'flow_type' => 'forward',
                'is_enabled' => 1
            ],
            [
                'workflow_id' => 4,
                'column_name' => 'budget_type',
                'operator' => '=',
                'value' => 'capital',
                'workflow_definition_id' => 11,
                'flow_type' => 'forward',
                'is_enabled' => 1
            ]
        ];

        // Insert the approval conditions into the database
        foreach ($conditions as $condition) {
            ApprovalCondition::create($condition);
        }
    }
}
