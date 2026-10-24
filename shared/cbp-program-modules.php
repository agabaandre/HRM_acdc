<?php

/**
 * Programs shown in the CBP Modules menu. Icons match the home catalog.
 * A program is listed only when its cbp_modules row is visible to the user.
 *
 * @return list<array{title: string, items: list<array{label: string, icon: string, module_key: string}>}>
 */
if (! function_exists('cbp_program_module_keys')) {
    /**
     * @param  list<array<string, mixed>>  $groups
     * @return array<string, true>
     */
    function cbp_program_module_keys(array $groups): array
    {
        $keys = [];
        foreach ($groups as $group) {
            foreach ($group['items'] ?? [] as $item) {
                $key = (string) ($item['module_key'] ?? '');
                if ($key !== '') {
                    $keys[$key] = true;
                }
            }
        }

        return $keys;
    }
}

if (! function_exists('cbp_module_home_area')) {
    /**
     * Top home tab for a nav module. Saved home_area wins; otherwise the catalog list.
     *
     * @param  array<string, mixed>  $mod
     * @param  array<string, true>  $programKeys
     */
    function cbp_module_home_area(array $mod, array $programKeys): string
    {
        $area = strtolower(trim((string) ($mod['home_area'] ?? '')));
        if (in_array($area, ['operations', 'programs', 'configuration'], true)) {
            return $area;
        }
        $key = (string) ($mod['module_key'] ?? '');
        if ($key !== '' && isset($programKeys[$key])) {
            return 'programs';
        }

        return 'operations';
    }
}

if (! function_exists('cbp_visible_program_groups')) {
    /**
     * Programs the user can open, grouped by the saved division chip.
     *
     * @param  list<array<string, mixed>>  $groups
     * @param  list<array<string, mixed>>  $modules
     * @return list<array<string, mixed>>
     */
    function cbp_visible_program_groups(array $groups, array $modules): array
    {
        $programKeys = cbp_program_module_keys($groups);
        $catalog = [];
        $titleOrder = [];
        foreach ($groups as $group) {
            $title = (string) ($group['title'] ?? '');
            $titleOrder[] = $title;
            foreach ($group['items'] ?? [] as $item) {
                $key = (string) ($item['module_key'] ?? '');
                if ($key === '') {
                    continue;
                }
                $item['_title'] = $title;
                $catalog[$key] = $item;
            }
        }

        $byTitle = [];
        foreach ($modules as $mod) {
            if (cbp_module_home_area($mod, $programKeys) !== 'programs') {
                continue;
            }
            $key = (string) ($mod['module_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $known = $catalog[$key] ?? null;
            $title = trim((string) ($mod['primary_tab'] ?? ''));
            if ($title === '' && is_array($known)) {
                $title = (string) ($known['_title'] ?? '');
            }
            if ($title === '') {
                $title = 'Programs';
            }
            $item = is_array($known) ? $known : [
                'label' => (string) ($mod['label'] ?? $key),
                'icon' => (string) ($mod['icon'] ?? 'fa-th'),
                'module_key' => $key,
            ];
            unset($item['_title']);
            $item['module'] = $mod;
            $byTitle[$title][] = $item;
        }

        $ordered = [];
        foreach ($titleOrder as $title) {
            if (isset($byTitle[$title])) {
                $ordered[] = $title;
            }
        }
        foreach (array_keys($byTitle) as $title) {
            if (! in_array($title, $ordered, true)) {
                $ordered[] = $title;
            }
        }

        $visible = [];
        foreach ($ordered as $title) {
            $visible[] = ['title' => $title, 'items' => $byTitle[$title]];
        }

        return $visible;
    }
}

return [
    [
        'title' => 'EPR',
        'items' => [
            ['label' => 'Logistics (LOG)', 'icon' => 'fa-truck-medical', 'module_key' => 'epr_logistics'],
            ['label' => 'ePHEM (EPH)', 'icon' => 'fa-kit-medical', 'module_key' => 'ephem'],
            ['label' => 'AVHOC (AVH)', 'icon' => 'fa-people-group', 'module_key' => 'avhoc'],
            ['label' => 'FRORP (FRP)', 'icon' => 'fa-flag', 'module_key' => 'frorp'],
        ],
    ],
    [
        'title' => 'SDI',
        'items' => [
            ['label' => 'EMS (EMS)', 'icon' => 'fa-truck-medical', 'module_key' => 'ems'],
            ['label' => 'Events Dashboard (EVD)', 'icon' => 'fa-calendar-days', 'module_key' => 'events_dashboard'],
            ['label' => 'IMST tracker (IMS)', 'icon' => 'fa-virus', 'module_key' => 'imst'],
        ],
    ],
    [
        'title' => 'LAB',
        'items' => [
            ['label' => 'NIMS (NIM)', 'icon' => 'fa-flask', 'module_key' => 'nims'],
            ['label' => 'Asana (ASN)', 'icon' => 'fa-list-check', 'module_key' => 'asana'],
        ],
    ],
    [
        'title' => 'Science',
        'items' => [
            ['label' => 'Prioritization (PRI)', 'icon' => 'fa-ranking-star', 'module_key' => 'prioritization'],
            ['label' => 'Knowledge Hub (KH)', 'icon' => 'fa-book', 'module_key' => 'knowledge_hub'],
            ['label' => 'RedCap (RDC)', 'icon' => 'fa-database', 'module_key' => 'redcap'],
        ],
    ],
    [
        'title' => 'Comms',
        'items' => [
            ['label' => 'Media Hub (MDH)', 'icon' => 'fa-bullhorn', 'module_key' => 'media_hub'],
        ],
    ],
    [
        'title' => 'PHIR',
        'items' => [
            ['label' => 'CUE (CUE)', 'icon' => 'fa-graduation-cap', 'module_key' => 'cue'],
            ['label' => 'Learning Management System (LMS)', 'icon' => 'fa-chalkboard-user', 'module_key' => 'lms'],
        ],
    ],
    [
        'title' => 'Lusaka Agenda',
        'items' => [
            ['label' => 'Scorecard Dashboard (SCD)', 'icon' => 'fa-chart-column', 'module_key' => 'scorecard'],
        ],
    ],
    [
        'title' => 'MIS',
        'items' => [
            ['label' => 'StarLink Dashboard (SLD)', 'icon' => 'fa-satellite-dish', 'module_key' => 'starlink'],
        ],
    ],
];
