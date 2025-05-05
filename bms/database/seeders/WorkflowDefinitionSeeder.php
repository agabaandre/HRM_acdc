<?php

namespace Database\Seeders;

use App\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;

class WorkflowDefinitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample workflow definitions
        $definitions = [
            // Purchase Request Workflow (ID: 1)
            [
                'role' => 'Division Head',
                'workflow_id' => 1,
                'approval_order' => 1,
                'is_enabled' => 1
            ],
            [
                'role' => 'Finance Officer',
                'workflow_id' => 1,
                'approval_order' => 2,
                'is_enabled' => 1
            ],
            [
                'role' => 'Procurement Officer',
                'workflow_id' => 1,
                'approval_order' => 3,
                'is_enabled' => 1
            ],

            // Leave Application Workflow (ID: 2)
            [
                'role' => 'Division Head',
                'workflow_id' => 2,
                'approval_order' => 1,
                'is_enabled' => 1
            ],
            [
                'role' => 'HR Manager',
                'workflow_id' => 2,
                'approval_order' => 2,
                'is_enabled' => 1
            ],

            // Travel Authorization Workflow (ID: 3)
            [
                'role' => 'Division Head',
                'workflow_id' => 3,
                'approval_order' => 1,
                'is_enabled' => 1
            ],
            [
                'role' => 'Finance Officer',
                'workflow_id' => 3,
                'approval_order' => 2,
                'is_enabled' => 1
            ],
            [
                'role' => 'Executive Director',
                'workflow_id' => 3,
                'approval_order' => 3,
                'is_enabled' => 1
            ],

            // Budget Request Workflow (ID: 4)
            [
                'role' => 'Division Head',
                'workflow_id' => 4,
                'approval_order' => 1,
                'is_enabled' => 1
            ],
            [
                'role' => 'Budget Officer',
                'workflow_id' => 4,
                'approval_order' => 2,
                'is_enabled' => 1
            ],
            [
                'role' => 'Finance Director',
                'workflow_id' => 4,
                'approval_order' => 3,
                'is_enabled' => 1
            ]
        ];

        // Insert the workflow definitions into the database
        foreach ($definitions as $definition) {
            WorkflowDefinition::create($definition);
        }
    }
}