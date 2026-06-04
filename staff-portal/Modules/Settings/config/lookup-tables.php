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
        'columns' => ['contract_type' => ['label' => 'Type', 'required' => true]],
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
        'pk' => 'directorate_id',
        'columns' => ['name' => ['label' => 'Name', 'required' => true]],
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
        'pk' => 'training_skill_id',
        'columns' => ['skill_name' => ['label' => 'Skill', 'required' => true]],
        'order' => 'skill_name',
    ],
    'au_values' => [
        'label' => 'AU Values',
        'pk' => 'au_value_id',
        'columns' => ['value_name' => ['label' => 'Value', 'required' => true]],
        'order' => 'value_name',
    ],
    'divisions' => [
        'label' => 'Divisions',
        'pk' => 'division_id',
        'columns' => [
            'division_name' => ['label' => 'Division name', 'required' => true],
            'division_short_name' => ['label' => 'Short name'],
            'category' => ['label' => 'Category'],
        ],
        'order' => 'division_name',
    ],
    'kin_relationship_types' => [
        'label' => 'Next of kin relationships',
        'pk' => 'relationship_id',
        'columns' => [
            'name' => ['label' => 'Name', 'required' => true],
            'sort_order' => ['label' => 'Sort order', 'type' => 'number'],
            'is_active' => ['label' => 'Active', 'type' => 'checkbox'],
        ],
        'order' => 'sort_order',
    ],
];
