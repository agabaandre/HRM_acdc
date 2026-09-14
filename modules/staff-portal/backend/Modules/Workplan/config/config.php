<?php

$aliasesRaw = (string) env('PRA_WORKPLAN_DIVISION_ALIASES', 'MIS:DHIS');
$aliases = [];
foreach (preg_split('/\s*,\s*/', $aliasesRaw) ?: [] as $pair) {
    if ($pair === '' || ! str_contains($pair, ':')) {
        continue;
    }
    [$from, $to] = array_map('trim', explode(':', $pair, 2));
    if ($from !== '' && $to !== '') {
        $aliases[strtoupper($from)] = strtoupper($to);
    }
}

// Empty / * / all = sync every local division short code (recommended).
$divisionsRaw = trim((string) env('PRA_WORKPLAN_DIVISIONS', ''));
$divisions = [];
if ($divisionsRaw !== '' && ! in_array(strtolower($divisionsRaw), ['*', 'all'], true)) {
    foreach (preg_split('/\s*,\s*/', $divisionsRaw) ?: [] as $code) {
        $code = strtoupper(trim((string) $code));
        if ($code !== '') {
            $divisions[] = $code;
        }
    }
}

return [
    'name' => 'Workplan',

    'pra' => [
        /**
         * Fallback from .env. Settings → Workplan / PRA overrides these when saved.
         */
        'base_url' => rtrim((string) env(
            'PRA_WORKPLAN_API_URL',
            'https://pra.africacdc.org/api/public/workplan'
        ), '/'),
        'api_key' => (string) env('PRA_WORKPLAN_API_KEY', ''),
        'tiers' => (string) env('PRA_WORKPLAN_TIERS', '3,4'),
        /** Unused. Sync uses the current calendar year unless Settings pins one. */
        'fiscal_year' => null,
        /**
         * PRA division codes to fetch on scheduled/full sync (comma-separated).
         * Empty / * / all = every local division_short_name (plus aliases).
         */
        'divisions' => $divisions,
        /**
         * PRA division.code → local divisions.division_short_name
         * Default maps MIS (PRA) onto DHIS (local Digital Health / MIS).
         */
        'division_aliases' => $aliases,
        'timeout' => (int) env('PRA_WORKPLAN_TIMEOUT', 60),
    ],
];
