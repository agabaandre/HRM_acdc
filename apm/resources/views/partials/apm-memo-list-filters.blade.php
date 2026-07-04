@php
    $filterId = $filterId ?? 'memoFilters';
    $resetUrl = $resetUrl ?? url()->current();
    $showQuarter = $showQuarter ?? false;
    $fundTypeFilterOptions = $fundTypeFilterOptions ?? \App\Support\MemoFundTypeFilter::options();
    $showFundType = $showFundType ?? true;
    $showRequestType = $showRequestType ?? false;
    $showMemoType = $showMemoType ?? false;
    $showServiceType = $showServiceType ?? false;
    $showOverallStatus = $showOverallStatus ?? false;
    $statusDomId = $statusDomId ?? ($showOverallStatus ? 'overall_status' : 'status');
    $statusParam = $statusParam ?? ($showOverallStatus ? 'overall_status' : 'status');
    $statusModelKey = $showOverallStatus ? 'overall_status' : 'status';
    $searchLabel = $searchLabel ?? 'Search title';
    $searchPlaceholder = $searchPlaceholder ?? 'Search…';
    $staffLabel = $staffLabel ?? 'Responsible person';

    $staffOptions = [['label' => 'All Staff', 'value' => '']];
    foreach ($staff ?? [] as $member) {
        $staffId = $member->staff_id ?? $member->id ?? null;
        if ($staffId === null) {
            continue;
        }
        $staffLabelText = isset($member->fname)
            ? trim(($member->fname ?? '') . ' ' . ($member->lname ?? ''))
            : ($member->name ?? '');
        $staffOptions[] = ['label' => $staffLabelText, 'value' => (string) $staffId];
    }

    $divisionOptions = [['label' => 'All Divisions', 'value' => '']];
    foreach ($divisions ?? [] as $division) {
        $divisionId = $division->division_id ?? $division->id ?? null;
        if ($divisionId === null) {
            continue;
        }
        $divisionOptions[] = [
            'label' => $division->division_name ?? '',
            'value' => (string) $divisionId,
        ];
    }

    $yearOptions = [];
    foreach ($years ?? range((int) date('Y'), (int) date('Y') - 5) as $yearKey => $yearValue) {
        if (is_int($yearKey)) {
            $yearOptions[] = ['label' => (string) $yearValue, 'value' => (string) $yearValue];
        } else {
            $yearOptions[] = ['label' => (string) $yearValue, 'value' => (string) $yearKey];
        }
    }

    $quarterList = $quarters ?? ['Q1', 'Q2', 'Q3', 'Q4'];
    $quarterOptions = [];
    foreach ($quarterList as $quarter) {
        $quarterOptions[] = ['label' => (string) $quarter, 'value' => (string) $quarter];
    }

    $fundTypeOptions = [['label' => 'All Fund Types', 'value' => '']];
    foreach ($fundTypeFilterOptions ?? [] as $fundTypeId => $fundTypeLabel) {
        $fundTypeOptions[] = ['label' => (string) $fundTypeLabel, 'value' => (string) $fundTypeId];
    }

    $requestTypeOptions = [['label' => 'All Request Types', 'value' => '']];
    foreach ($requestTypes ?? [] as $requestType) {
        $requestTypeOptions[] = [
            'label' => $requestType->name ?? (string) $requestType,
            'value' => (string) ($requestType->id ?? $requestType),
        ];
    }

    $memoTypeOptions = $memoTypeOptions ?? [
        ['label' => 'All Memo Types', 'value' => ''],
        ['label' => 'Activity', 'value' => 'App\Models\Activity'],
        ['label' => 'Special Memo', 'value' => 'App\Models\SpecialMemo'],
        ['label' => 'Non-Travel Memo', 'value' => 'App\Models\NonTravelMemo'],
        ['label' => 'Request ARF', 'value' => 'App\Models\RequestArf'],
        ['label' => 'Service Request', 'value' => 'App\Models\ServiceRequest'],
    ];

    $serviceTypeOptions = $serviceTypeOptions ?? [
        ['label' => 'All Types', 'value' => ''],
        ['label' => 'IT Support', 'value' => 'IT Support'],
        ['label' => 'Maintenance', 'value' => 'Maintenance'],
        ['label' => 'Other', 'value' => 'Other'],
    ];

    $fields = [
        ['key' => 'search', 'param' => 'search', 'domId' => 'search'],
        ['key' => 'document_number', 'param' => 'document_number', 'domId' => 'document_number'],
        ['key' => 'staff_id', 'param' => 'staff_id', 'domId' => 'staff_id'],
        ['key' => 'year', 'param' => 'year', 'domId' => 'year', 'defaultYear' => (string) ($selectedYear ?? date('Y'))],
        ['key' => 'division_id', 'param' => 'division_id', 'domId' => 'division_id'],
        ['key' => $statusModelKey, 'param' => $statusParam, 'domId' => $statusDomId],
    ];
    if ($showQuarter) {
        $fields[] = ['key' => 'quarter', 'param' => 'quarter', 'domId' => 'quarter', 'defaultQuarter' => $selectedQuarter ?? ('Q' . ceil(date('n') / 3))];
    }
    if ($showFundType) {
        $fields[] = ['key' => 'fund_type_id', 'param' => 'fund_type_id', 'domId' => 'fund_type_id'];
    }
    if ($showRequestType) {
        $fields[] = ['key' => 'request_type_id', 'param' => 'request_type_id', 'domId' => 'request_type_id'];
    }
    if ($showMemoType) {
        $fields[] = ['key' => 'memo_type', 'param' => 'memo_type', 'domId' => 'memo_type'];
    }
    if ($showServiceType) {
        $fields[] = ['key' => 'service_type', 'param' => 'service_type', 'domId' => 'service_type'];
    }

    $values = [
        'search' => (string) ($searchTerm ?? request('search', '')),
        'document_number' => (string) request('document_number', ''),
        'staff_id' => (string) request('staff_id', ''),
        'year' => (string) ($selectedYear ?? request('year', date('Y'))),
        'division_id' => (string) ($selectedDivisionId ?? request('division_id', '')),
        'status' => (string) ($selectedStatus ?? request('status', '')),
        'overall_status' => (string) request('overall_status', ''),
        'fund_type_id' => (string) ($selectedFundTypeId ?? request('fund_type_id', '')),
        'request_type_id' => (string) request('request_type_id', ''),
        'memo_type' => (string) request('memo_type', ''),
        'service_type' => (string) request('service_type', ''),
    ];
    if ($showQuarter) {
        $values['quarter'] = (string) ($selectedQuarter ?? request('quarter', 'Q' . ceil(date('n') / 3)));
    }

    $filterConfig = [
        'filterId' => $filterId,
        'resetUrl' => $resetUrl,
        'showSearch' => true,
        'showDocumentNumber' => true,
        'showStaff' => true,
        'showYear' => true,
        'showQuarter' => $showQuarter,
        'showDivision' => true,
        'showStatus' => !$showOverallStatus,
        'showOverallStatus' => $showOverallStatus,
        'showFundType' => $showFundType,
        'showRequestType' => $showRequestType,
        'showMemoType' => $showMemoType,
        'showServiceType' => $showServiceType,
        'searchLabel' => $searchLabel,
        'searchPlaceholder' => $searchPlaceholder,
        'staffLabel' => $staffLabel,
        'statusDomId' => $statusDomId,
        'fields' => $fields,
        'values' => $values,
        'options' => [
            'staff' => $staffOptions,
            'divisions' => $divisionOptions,
            'years' => $yearOptions,
            'quarters' => $quarterOptions,
            'fundTypes' => $fundTypeOptions,
            'requestTypes' => $requestTypeOptions,
            'memoTypes' => $memoTypeOptions,
            'serviceTypes' => $serviceTypeOptions,
        ],
    ];
@endphp

<div class="apm-memo-filters-mount w-100" data-filter-id="{{ $filterId }}">
    <script type="application/json" class="apm-memo-filters-config">{!! json_encode($filterConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
</div>

<div id="{{ $filterId }}-sync" class="apm-memo-filters-sync" aria-hidden="true">
    <input type="hidden" data-sync="search" id="search" value="{{ $values['search'] }}">
    <input type="hidden" data-sync="document_number" id="document_number" value="{{ $values['document_number'] }}">
    <input type="hidden" data-sync="staff_id" id="staff_id" value="{{ $values['staff_id'] }}">
    <input type="hidden" data-sync="year" id="year" value="{{ $values['year'] }}">
    @if($showQuarter)
        <input type="hidden" data-sync="quarter" id="quarter" value="{{ $values['quarter'] }}">
    @endif
    <input type="hidden" data-sync="division_id" id="division_id" value="{{ $values['division_id'] }}">
    @if($showOverallStatus)
        <input type="hidden" data-sync="overall_status" id="{{ $statusDomId }}" value="{{ $values['overall_status'] }}">
    @else
        <input type="hidden" data-sync="status" id="{{ $statusDomId }}" value="{{ $values['status'] }}">
    @endif
    @if($showFundType)
        <input type="hidden" data-sync="fund_type_id" id="fund_type_id" value="{{ $values['fund_type_id'] }}">
    @endif
    @if($showRequestType)
        <input type="hidden" data-sync="request_type_id" id="request_type_id" value="{{ $values['request_type_id'] }}">
    @endif
    @if($showMemoType)
        <input type="hidden" data-sync="memo_type" id="memo_type" value="{{ $values['memo_type'] }}">
    @endif
    @if($showServiceType)
        <input type="hidden" data-sync="service_type" id="service_type" value="{{ $values['service_type'] }}">
    @endif
</div>
