@php
    $filterId = $filterId ?? 'matrixFilters';
    $resetUrl = $resetUrl ?? route('matrices.index');

    $yearOptions = [['label' => 'All Years', 'value' => '']];
    foreach (range((int) date('Y') + 1, (int) date('Y') - 5) as $year) {
        $yearOptions[] = ['label' => (string) $year, 'value' => (string) $year];
    }

    $quarterOptions = [
        ['label' => 'All Quarters', 'value' => ''],
        ['label' => 'Q1', 'value' => 'Q1'],
        ['label' => 'Q2', 'value' => 'Q2'],
        ['label' => 'Q3', 'value' => 'Q3'],
        ['label' => 'Q4', 'value' => 'Q4'],
    ];

    $divisionOptions = [['label' => 'All Divisions', 'value' => '']];
    foreach ($divisions ?? [] as $division) {
        $divisionId = $division->id ?? $division->division_id ?? null;
        if ($divisionId === null) {
            continue;
        }
        $divisionOptions[] = [
            'label' => $division->division_name ?? '',
            'value' => (string) $divisionId,
        ];
    }

    $focalOptions = [['label' => 'All Focal Persons', 'value' => '']];
    foreach ($focalPersons ?? [] as $person) {
        $staffId = $person->staff_id ?? $person->id ?? null;
        if ($staffId === null) {
            continue;
        }
        $focalOptions[] = [
            'label' => $person->name ?? trim(($person->fname ?? '') . ' ' . ($person->lname ?? '')),
            'value' => (string) $staffId,
        ];
    }

    $matrixStatusOptions = [
        ['label' => 'Active Only', 'value' => 'active'],
        ['label' => 'Archived Only', 'value' => 'archived'],
        ['label' => 'All', 'value' => 'all'],
    ];

    $defaultYear = '';
    if (request()->has('year')) {
        $defaultYear = (string) request('year', '');
    } elseif (!empty($selectedYear)) {
        $defaultYear = (string) $selectedYear;
    } else {
        $defaultYear = (string) date('Y');
    }

    $fields = [
        ['key' => 'year', 'param' => 'year', 'domId' => 'yearFilter'],
        ['key' => 'quarter', 'param' => 'quarter', 'domId' => 'quarterFilter'],
        ['key' => 'division', 'param' => 'division', 'domId' => 'divisionFilter'],
        ['key' => 'focal_person', 'param' => 'focal_person', 'domId' => 'focalFilter'],
        ['key' => 'status', 'param' => 'status', 'domId' => 'statusFilter'],
    ];

    $values = [
        'year' => (string) (request()->has('year') ? request('year', '') : $defaultYear),
        'quarter' => (string) ($selectedQuarter ?? request('quarter', '')),
        'division' => (string) request('division', ''),
        'focal_person' => (string) request('focal_person', ''),
        'status' => (string) ($selectedStatus ?? request('status', 'active')),
    ];

    $filterConfig = [
        'filterId' => $filterId,
        'resetUrl' => $resetUrl,
        'fields' => $fields,
        'values' => $values,
        'options' => [
            'years' => $yearOptions,
            'quarters' => $quarterOptions,
            'divisions' => $divisionOptions,
            'focalPersons' => $focalOptions,
            'matrixStatuses' => $matrixStatusOptions,
        ],
    ];
@endphp

<div class="apm-matrix-filters-mount w-100" data-filter-id="{{ $filterId }}">
    <script type="application/json" class="apm-matrix-filters-config">{!! json_encode($filterConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
</div>

<div id="{{ $filterId }}-sync" class="apm-memo-filters-sync" aria-hidden="true">
    <input type="hidden" data-sync="year" id="yearFilter" value="{{ $values['year'] }}">
    <input type="hidden" data-sync="quarter" id="quarterFilter" value="{{ $values['quarter'] }}">
    <input type="hidden" data-sync="division" id="divisionFilter" value="{{ $values['division'] }}">
    <input type="hidden" data-sync="focal_person" id="focalFilter" value="{{ $values['focal_person'] }}">
    <input type="hidden" data-sync="status" id="statusFilter" value="{{ $values['status'] }}">
</div>
