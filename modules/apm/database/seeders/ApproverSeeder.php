<?php

namespace Database\Seeders;

use App\Models\Approver;
use Illuminate\Database\Seeder;

class ApproverSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample approvers
        $approvers = [
            // Approvers for Purchase Request workflow definitions
            [
                'workflow_dfn_id' => 1, // Division Head in Purchase Request
                'staff_id' => 1, // IT Department Head
                'oic_staff_id' => null,
                'start_date' => '2025-01-01',
                'end_date' => null,
            ],
            [
                'workflow_dfn_id' => 1, // Division Head in Purchase Request
                'staff_id' => 5, // HR Department Head
                'oic_staff_id' => null,
                'start_date' => '2025-01-01',
                'end_date' => null,
            ],
            [
                'workflow_dfn_id' => 2, // Finance Officer in Purchase Request
                'staff_id' => 4, // IT Finance Officer
                'oic_staff_id' => 12, // Finance Department Finance Officer
                'start_date' => '2025-01-01',
                'end_date' => null,
            ],
            [
                'workflow_dfn_id' => 3, // Procurement Officer in Purchase Request
                'staff_id' => 20, // Procurement Officer ID
                'oic_staff_id' => null,
                'start_date' => '2025-01-01',
                'end_date' => null,
            ],
            
            // Approvers for Leave Application workflow definitions
            [
                'workflow_dfn_id' => 4, // Division Head in Leave Application
                'staff_id' => 1, // IT Department Head
                'oic_staff_id' => null,
                'start_date' => '2025-01-01',
                'end_date' => null,
            ],
            [
                'workflow_dfn_id' => 4, // Division Head in Leave Application
                'staff_id' => 5, // HR Department Head
                'oic_staff_id' => null,
                'start_date' => '2025-01-01',
                'end_date' => null,
            ],
            [
                'workflow_dfn_id' => 5, // HR Manager in Leave Application
                'staff_id' => 25, // HR Manager ID
                'oic_staff_id' => 26,
                'start_date' => '2025-01-01',
                'end_date' => null,
            ],
            
            // Approvers for Travel Authorization workflow definitions
            [
                'workflow_dfn_id' => 6, // Division Head in Travel Authorization
                'staff_id' => 9, // Finance Department Head
                'oic_staff_id' => null,
                'start_date' => '2025-01-01',
                'end_date' => null,
            ],
            [
                'workflow_dfn_id' => 7, // Finance Officer in Travel Authorization
                'staff_id' => 12, // Finance Department Finance Officer
                'oic_staff_id' => null,
                'start_date' => '2025-01-01',
                'end_date' => null,
            ],
            [
                'workflow_dfn_id' => 8, // Executive Director in Travel Authorization
                'staff_id' => 30, // Executive Director ID
                'oic_staff_id' => null,
                'start_date' => '2025-01-01',
                'end_date' => null,
            ],
            
            // Approvers for Budget Request workflow definitions
            [
                'workflow_dfn_id' => 9, // Division Head in Budget Request
                'staff_id' => 13, // Operations Department Head
                'oic_staff_id' => null,
                'start_date' => '2025-01-01',
                'end_date' => null,
            ],
            [
                'workflow_dfn_id' => 10, // Budget Officer in Budget Request
                'staff_id' => 35, // Budget Officer ID
                'oic_staff_id' => null,
                'start_date' => '2025-01-01',
                'end_date' => null,
            ],
            [
                'workflow_dfn_id' => 11, // Finance Director in Budget Request
                'staff_id' => 40, // Finance Director ID
                'oic_staff_id' => null,
                'start_date' => '2025-01-01',
                'end_date' => null,
            ],
        ];

        // Insert the approvers into the database
        foreach ($approvers as $approver) {
            Approver::create($approver);
        }
    }
}
