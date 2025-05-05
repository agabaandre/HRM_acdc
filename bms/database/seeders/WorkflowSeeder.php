<?php

namespace Database\Seeders;

use App\Models\Workflow;
use Illuminate\Database\Seeder;

class WorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample workflows
        $workflows = [
            [
                'workflow_name' => 'Purchase Request Approval',
                'Description' => 'Workflow for purchase request approval process',
                'is_active' => 1
            ],
            [
                'workflow_name' => 'Leave Application',
                'Description' => 'Workflow for leave application approval process',
                'is_active' => 1
            ],
            [
                'workflow_name' => 'Travel Authorization',
                'Description' => 'Workflow for travel authorization approval process',
                'is_active' => 1
            ],
            [
                'workflow_name' => 'Budget Request',
                'Description' => 'Workflow for budget request approval process',
                'is_active' => 1
            ]
        ];

        // Insert the workflows into the database
        foreach ($workflows as $workflow) {
            Workflow::create($workflow);
        }
    }
}
