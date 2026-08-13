<?php

/**
 * Simple lookup tables managed via generic Settings CRUD (mirrors CI3 settings/*).
 * Complex tables (divisions, cbp_modules, ppa_variables) have dedicated Livewire pages.
 */
return [
    'nationalities' => [
        'label' => 'Nationalities',
        'pk' => 'nationality_id',
        'columns' => ['nationality' => ['label' => 'Name', 'required' => true]],
        'order' => 'nationality',
    ],
    'duty_stations' => [
        'label' => 'Duty Stations',
        'pk' => 'duty_station_id',
        'columns' => ['duty_station_name' => ['label' => 'Name', 'required' => true]],
        'order' => 'duty_station_name',
    ],
    'contract_types' => [
        'label' => 'Contract Types',
        'pk' => 'contract_type_id',
        'columns' => [
            'contract_type' => ['label' => 'Type', 'required' => true],
            'category' => [
                'label' => 'Category',
                'required' => true,
                'type' => 'select',
                'options' => [
                    'main_staff' => 'Main staff',
                    'other_staff' => 'Other staff',
                ],
            ],
        ],
        'order' => 'contract_type',
    ],
    'contracting_institutions' => [
        'label' => 'Contracting Institutions',
        'pk' => 'contracting_institution_id',
        'columns' => ['contracting_institution' => ['label' => 'Institution', 'required' => true]],
        'order' => 'contracting_institution',
    ],
    'directorates' => [
        'label' => 'Directorates',
        'pk' => 'id',
        'columns' => [
            'name' => ['label' => 'Name', 'required' => true],
            'is_active' => ['label' => 'Active', 'type' => 'checkbox'],
            'director_id' => ['label' => 'Director staff id', 'type' => 'number'],
        ],
        'order' => 'name',
    ],
    'grades' => [
        'label' => 'Grades',
        'pk' => 'grade_id',
        'columns' => ['grade' => ['label' => 'Grade', 'required' => true]],
        'order' => 'grade',
    ],
    'jobs' => [
        'label' => 'Jobs',
        'pk' => 'job_id',
        'columns' => ['job_name' => ['label' => 'Job name', 'required' => true]],
        'order' => 'job_name',
    ],
    'funders' => [
        'label' => 'Funders',
        'pk' => 'funder_id',
        'columns' => ['funder' => ['label' => 'Funder', 'required' => true]],
        'order' => 'funder',
    ],
    'regions' => [
        'label' => 'Regions',
        'pk' => 'region_id',
        'columns' => ['region_name' => ['label' => 'Region', 'required' => true]],
        'order' => 'region_name',
    ],
    'units' => [
        'label' => 'Units',
        'pk' => 'unit_id',
        'columns' => ['unit_name' => ['label' => 'Unit', 'required' => true]],
        'order' => 'unit_name',
    ],
    'training_skills' => [
        'label' => 'Training Skills',
        'pk' => 'id',
        'columns' => [
            'skill' => ['label' => 'Skill', 'required' => true],
            'category_id' => [
                'label' => 'Category',
                'required' => true,
                'type' => 'select',
                'options_from' => [
                    'table' => 'training_categories',
                    'value' => 'id',
                    'label' => 'category_name',
                    'order' => 'category_name',
                ],
            ],
        ],
        'order' => 'skill',
    ],
    'au_values' => [
        'label' => 'AU Values',
        'pk' => 'id',
        'columns' => [
            'description' => ['label' => 'Description', 'required' => true, 'type' => 'textarea'],
            'annotation' => ['label' => 'Annotation', 'type' => 'textarea'],
            'category' => ['label' => 'Category', 'required' => true],
            'version' => ['label' => 'Version', 'type' => 'number'],
            'score_5' => ['label' => 'Score 5', 'type' => 'textarea'],
            'score_4' => ['label' => 'Score 4', 'type' => 'textarea'],
            'score_3' => ['label' => 'Score 3', 'type' => 'textarea'],
            'score_2' => ['label' => 'Score 2', 'type' => 'textarea'],
            'score_1' => ['label' => 'Score 1', 'type' => 'textarea'],
        ],
        'order' => 'id',
    ],
    'divisions' => [
        'label' => 'Divisions',
        'pk' => 'division_id',
        'columns' => [
            'division_name' => ['label' => 'Division name', 'required' => true],
            'division_short_name' => ['label' => 'Short name'],
            'category' => [
                'label' => 'Category',
                'required' => true,
                'type' => 'select',
                'options' => [
                    'Programs' => 'Programs',
                    'Operations' => 'Operations',
                    'Other' => 'Other',
                ],
            ],
            'division_head' => ['label' => 'Division head (staff id)', 'type' => 'number', 'required' => true],
            'focal_person' => ['label' => 'Focal person (staff id)', 'type' => 'number', 'required' => true],
            'finance_officer' => ['label' => 'Finance officer (staff id)', 'type' => 'number', 'required' => true],
            'admin_assistant' => ['label' => 'Admin assistant (staff id)', 'type' => 'number', 'required' => true],
            'director_id' => ['label' => 'Director (staff id)', 'type' => 'number'],
            'head_oic_id' => ['label' => 'Head OIC (staff id)', 'type' => 'number'],
            'director_oic_id' => ['label' => 'Director OIC (staff id)', 'type' => 'number'],
            'directorate_id' => ['label' => 'Directorate id', 'type' => 'number'],
            'is_active' => ['label' => 'Active', 'type' => 'checkbox'],
        ],
        'order' => 'division_name',
    ],
    'kin_relationship_types' => [
        'label' => 'Next of kin relationships',
        'pk' => 'kin_relationship_id',
        'columns' => [
            'relationship_name' => ['label' => 'Name', 'required' => true],
            'sort_order' => ['label' => 'Sort order', 'type' => 'number'],
            'is_active' => ['label' => 'Active', 'type' => 'checkbox'],
        ],
        'order' => 'sort_order',
    ],
];
