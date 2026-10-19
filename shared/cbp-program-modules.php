<?php

/**
 * Programs shown in the CBP Modules menu. Icons match the home catalog.
 *
 * @return list<array{title: string, items: list<array{label: string, icon: string}>}>
 */
return [
    [
        'title' => 'EPR',
        'items' => [
            ['label' => 'Logistics (LOG)', 'icon' => 'fa-truck-medical'],
            ['label' => 'ePHEM (EPH)', 'icon' => 'fa-kit-medical'],
            ['label' => 'AVHOC (AVH)', 'icon' => 'fa-people-group'],
            ['label' => 'FRORP (FRP)', 'icon' => 'fa-flag'],
        ],
    ],
    [
        'title' => 'SDI',
        'items' => [
            ['label' => 'EMS (EMS)', 'icon' => 'fa-truck-medical'],
            ['label' => 'Events Dashboard (EVD)', 'icon' => 'fa-calendar-days'],
            ['label' => 'IMST tracker (IMS)', 'icon' => 'fa-virus'],
        ],
    ],
    [
        'title' => 'LAB',
        'items' => [
            ['label' => 'NIMS (NIM)', 'icon' => 'fa-flask'],
            ['label' => 'Asana (ASN)', 'icon' => 'fa-list-check'],
        ],
    ],
    [
        'title' => 'Science',
        'items' => [
            ['label' => 'Prioritization (PRI)', 'icon' => 'fa-ranking-star'],
            ['label' => 'Knowledge Hub (KH)', 'icon' => 'fa-book'],
            ['label' => 'RedCap (RDC)', 'icon' => 'fa-database'],
        ],
    ],
    [
        'title' => 'Comms',
        'items' => [
            ['label' => 'Media Hub (MDH)', 'icon' => 'fa-bullhorn'],
        ],
    ],
    [
        'title' => 'PHIR',
        'items' => [
            ['label' => 'CUE (CUE)', 'icon' => 'fa-graduation-cap'],
            ['label' => 'Learning Management System (LMS)', 'icon' => 'fa-chalkboard-user'],
        ],
    ],
    [
        'title' => 'Lusaka Agenda',
        'items' => [
            ['label' => 'Scorecard Dashboard (SCD)', 'icon' => 'fa-chart-column'],
        ],
    ],
    [
        'title' => 'MIS',
        'items' => [
            ['label' => 'StarLink Dashboard (SLD)', 'icon' => 'fa-satellite-dish'],
        ],
    ],
];
