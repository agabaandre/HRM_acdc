<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Performance approval workflows (see PerformanceWorkflowService)
    |--------------------------------------------------------------------------
    |
    | ppa / midterm: employee submit → supervisor 1 → supervisor 2 (optional)
    | endterm: employee submit → employee consent → supervisor 1 → supervisor 2
    |
    | Supervisors resolve from latest staff_contracts.first_supervisor / second_supervisor.
    | Phase supervisor columns on ppa_entries update only while phase is in-flight.
    | Trails (ppa_approval_trail*) are append-only.
    */
    'draft_status' => [
        'draft' => 1,
        'submitted' => 0,
        'approved' => 2,
    ],
];
