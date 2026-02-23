@extends('layouts.app')

@php
    // For non-travel, use source document title to match the print (PDF)
    $displayTitle = ($requestARF->model_type === 'App\\Models\\NonTravelMemo' && isset($sourceModel))
        ? ($sourceModel->activity_title ?? $sourceData['title'] ?? $requestARF->activity_title ?? 'Untitled')
        : ($requestARF->activity_title ?? 'Untitled');
    // When this ARF was created from a Change Request, overlay CR's changed data for display
    $cr = $originatingChangeRequest ?? null;
    if ($cr && is_array($sourceData)) {
        if (!empty($cr->has_activity_title_changed) && $cr->activity_title) {
            $sourceData['title'] = $cr->activity_title;
            $sourceData['activity_title'] = $cr->activity_title;
            $displayTitle = $cr->activity_title;
        }
        if (!empty($cr->has_memo_date_changed) && $cr->memo_date) {
            $sourceData['memo_date'] = $cr->memo_date;
        }
        if (!empty($cr->has_participant_days_changed)) {
            if ($cr->date_from) {
                $sourceData['date_from'] = $cr->date_from;
                $sourceData['start_date'] = $cr->date_from;
            }
            if ($cr->date_to) {
                $sourceData['date_to'] = $cr->date_to;
                $sourceData['end_date'] = $cr->date_to;
            }
        }
        if (!empty($cr->has_number_of_participants_changed) && isset($cr->total_participants)) {
            $sourceData['total_participants'] = $cr->total_participants;
        }
        if (!empty($cr->has_total_external_participants_changed) && isset($cr->total_external_participants)) {
            $sourceData['total_external_participants'] = $cr->total_external_participants;
        }
        if (!empty($cr->has_activity_request_remarks_changed) && $cr->activity_request_remarks) {
            $sourceData['activity_request_remarks'] = $cr->activity_request_remarks;
        }
        if (!empty($cr->has_internal_participants_changed) && $cr->internal_participants !== null) {
            $sourceData['internal_participants'] = is_string($cr->internal_participants)
                ? json_decode($cr->internal_participants, true) : $cr->internal_participants;
        }
        if (!empty($cr->has_budget_breakdown_changed) || !empty($cr->has_budget_id_changed)) {
            if ($cr->budget_breakdown !== null && $cr->budget_breakdown !== '') {
                $crBudget = is_string($cr->budget_breakdown) ? json_decode($cr->budget_breakdown, true) : $cr->budget_breakdown;
                if (is_array($crBudget)) {
                    $sourceData['budget_breakdown'] = $crBudget;
                    $fundCodeIds = array_diff(array_keys($crBudget), ['grand_total']);
                    if (!empty($fundCodeIds)) {
                        $sourceData['fund_codes'] = \App\Models\FundCode::whereIn('id', $fundCodeIds)->with('fundType')->get()->keyBy('id');
                    }
                }
            }
            if (isset($cr->available_budget) && $cr->available_budget > 0) {
                $sourceData['total_budget'] = $cr->available_budget;
            }
        }
    }
@endphp
@section('title', 'View Activity Request - ' . $displayTitle)

@section('styles')
    <style>
        .status-badge {
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            text-transform: capitalize;
        }

        .status-approved {
            @apply bg-green-100 text-green-800 border border-green-200;
        }

        .status-rejected {
            @apply bg-red-100 text-red-800 border border-red-200;
        }

        .status-pending {
            @apply bg-yellow-100 text-yellow-800 border border-yellow-200;
        }

        .status-draft {
            @apply bg-gray-100 text-gray-800 border border-gray-200;
        }

        .gradient-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }

        .meta-card {
            background: #f8fafc;
            border-radius: 0.75rem;
            padding: 1rem;
            border: 1px solid #e2e8f0;
        }

        .content-section {
            border-left: 4px solid;
            background: #fafafa;
        }

        .content-section.bg-blue {
            border-left-color: #3b82f6;
        }

        .content-section.bg-green {
            border-left-color: #10b981;
        }

        .content-section.bg-purple {
            border-left-color: #8b5cf6;
        }

        .sidebar-card {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border-radius: 0.75rem;
            overflow: hidden;
        }

        .location-badge,
        .budget-item {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 0.5rem;
            padding: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .budget-table th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .budget-table {
            table-layout: fixed;
            width: 100%;
        }
        
        .budget-table td {
            vertical-align: middle;
            font-size: 0.9rem;
        }
        
        .budget-table td.cost-item,
        .budget-table th.cost-item {
            word-wrap: break-word;
            word-break: break-word;
            white-space: normal;
            overflow-wrap: break-word;
            width: 35%;
            max-width: 35%;
        }
        
        /* Non-travel memo: Description column at 50% width with proper wrapping */
        .budget-table.non-travel-memo td.cost-item,
        .budget-table.non-travel-memo th.cost-item {
            width: 50%;
            max-width: 50%;
            word-wrap: break-word;
            word-break: break-word;
            white-space: normal;
            overflow-wrap: break-word;
            line-height: 1.3;
        }
        
        .budget-table.non-travel-memo td.cost-item > div {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
            line-height: 1.3;
        }
        
        .budget-table td.description,
        .budget-table th.description {
            word-wrap: break-word;
            word-break: break-word;
            white-space: normal;
            overflow-wrap: break-word;
            width: 25%;
            max-width: 25%;
        }

        .budget-total-row {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            font-weight: 700;
        }

        .fund-code-header {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #bae6fd;
            border-radius: 0.5rem;
        }

        .fund-code-header h6 {
            color: #0369a1;
            margin-bottom: 0.25rem;
        }

        .fund-code-header .small {
            color: #64748b;
        }

        .attachment-item {
            background: #faf5ff;
            border: 1px solid #e9d5ff;
            border-radius: 0.5rem;
            padding: 0.75rem;
        }

        /* Matrix-style metadata */
        .memo-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 1.5rem;
            font-size: 0.92rem;
            line-height: 1.1;
            margin-bottom: 0.5rem;
        }

        .memo-meta-item {
            display: flex;
            align-items: center;
            min-width: 120px;
            margin-bottom: 0;
        }

        .memo-meta-item i {
            font-size: 1rem;
            margin-right: 0.3rem;
            color: #007bff;
        }

        .memo-meta-label {
            color: #888;
            font-size: 0.85em;
            margin-right: 0.2em;
        }

        .memo-meta-value {
            font-weight: 500;
        }

        .approval-level-badge {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        /* Summary table styles */
        .summary-table {
            background: white;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .summary-table .table {
            margin-bottom: 0;
        }

        .summary-table .table th {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: none;
            font-weight: 600;
            color: #374151;
            padding: 1rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .summary-table .table td {
            border: none;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem;
            vertical-align: middle;
        }

        .summary-table .table tr:last-child td {
            border-bottom: none;
        }

        .summary-table .table tr:hover {
            background-color: #f9fafb;
        }

        .field-label {
            font-weight: 600;
            color: #374151;
            min-width: 150px;
        }

        .field-value {
            color: #1f2937;
            font-weight: 500;
        }

        .field-value.null {
            color: #9ca3af;
            font-style: italic;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-draft {
            background: #f3f4f6;
            color: #6b7280;
        }

        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-approved {
            background: #d1fae5;
            color: #059669;
        }

        .status-rejected {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-returned {
            background: #dbeafe;
            color: #2563eb;
        }
    </style>
@endsection

@section('content')
    @php
        // Calculate budget total for summary display
        $totalBudget = 0;

        if ($requestARF->model_type === 'App\\Models\\Activity') {
            // For activities, use activity_budget relationship
            $budgetItems = $sourceData['budget_breakdown'] ?? [];
            // Decode JSON string if needed
            if (is_string($budgetItems)) {
                $budgetItems = json_decode($budgetItems, true) ?? [];
            }
            if (!empty($budgetItems)) {
                if (is_array($budgetItems)) {
                    // Check if it has grand_total first
                    if (isset($budgetItems['grand_total'])) {
                        $totalBudget = floatval($budgetItems['grand_total']);
                    } else {
                        // Process individual items
                        foreach ($budgetItems as $key => $item) {
                            if ($key === 'grand_total') {
                                $totalBudget = floatval($item);
                            } elseif (is_array($item)) {
                                foreach ($item as $budgetItem) {
                                    if (is_object($budgetItem)) {
                                        $totalBudget += $budgetItem->unit_cost * $budgetItem->units * $budgetItem->days;
                                    } elseif (is_array($budgetItem)) {
                                        $totalBudget += floatval($budgetItem['unit_cost'] ?? 0) * floatval($budgetItem['units'] ?? 0) * floatval($budgetItem['days'] ?? 0);
                                    }
                                }
                            }
                        }
                    }
                } elseif (is_object($budgetItems) && method_exists($budgetItems, 'each')) {
                    // It's a collection
                    foreach ($budgetItems as $item) {
                        $totalBudget += $item->unit_cost * $item->units * $item->days;
                    }
                }
            }
} else {
    // For memos, use budget_breakdown array
    $budget = $sourceData['budget_breakdown'] ?? [];
    // Decode JSON string if needed
    if (is_string($budget)) {
        $budget = json_decode($budget, true) ?? [];
    }
    if (!empty($budget) && is_array($budget)) {
        // Check if it's a simple array of budget items
                if (isset($budget[0]) && is_array($budget[0])) {
                    // Simple array structure: [0 => {item1}, 1 => {item2}]
                    foreach ($budget as $item) {
                        $totalBudget += floatval(
                            $item['total'] ?? ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1),
                        );
                    }
                } else {
                    // Keyed structure: {fund_code_id => [items]}
                    foreach ($budget as $key => $item) {
                        if ($key === 'grand_total') {
                            $totalBudget = floatval($item);
                        } elseif (is_array($item)) {
                            foreach ($item as $budgetItem) {
                                $totalBudget += floatval(
                                    $budgetItem['total'] ??
                                        ($budgetItem['unit_price'] ?? 0) * ($budgetItem['quantity'] ?? 1),
                                );
                            }
                        }
                    }
                }
            }
        }
    @endphp

    @if (!$requestARF)
        <div class="alert alert-danger">
            <i class="bx bx-error-circle me-2"></i>
            ARF request not found or you don't have permission to view it.
        </div>
    @else
        <div class="min-h-screen bg-gray-50">
            <!-- Enhanced Header -->
            <div class="bg-white border-b border-gray-200 shadow-sm">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-start py-4">
                        <div class="flex-grow-1 me-3" style="min-width: 0;">
                            <h1 class="h2 fw-bold text-dark mb-0">View Activity Request</h1>
                            @if ($requestARF->document_number)
                                <p class="text-muted mb-0">{{ $requestARF->document_number }}</p>
                            @endif
                            <p class="text-dark mb-0 fw-medium text-break" style="word-wrap: break-word; overflow-wrap: break-word; max-width: 100%;">{{ $displayTitle }}</p>
                        </div>

                           <div class="d-flex gap-3 col-md-2 justify-content-end">
                                    <a href="{{ route('request-arf.index') }}"
                                        class="btn btn-outline-secondary d-flex align-items-center gap-2">
                                        <i class="bx bx-arrow-back"></i>
                                        <span>Back to List</span>
                         </a>
                        </div>

                    </div>
                </div>
            </div>

            <div class="container-fluid py-4">
                <div class="row g-4">
                    <!-- Main Content -->
                    <div class="col-lg-8">
                        <!-- ARF Summary Table -->
                        <div class="summary-table mb-4">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th colspan="2">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bx bx-file-blank text-success"></i>
                                                Activity Request Summary
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if ($requestARF->document_number)
                                        <tr>
                                            <td class="field-label">
                                                <i class="bx bx-hash text-success me-2"></i>Document Number
                                            </td>
                                            <td class="field-value">
                                                <span class="text-primary fw-bold">{{ $requestARF->document_number }}</span>
                                            </td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <td class="field-label">
                                            <i class="bx bx-text text-success me-2"></i>ARF Title
                                        </td>
                                        <td class="field-value">{{ $displayTitle }}</td>
                                    </tr>
                                    <tr>
                                        <td class="field-label">
                                            <i class="bx bx-calendar text-success me-2"></i>Request Date
                                        </td>
                                        <td class="field-value">
                                            {{ $requestARF->request_date ? $requestARF->request_date->format('M d, Y') : 'N/A' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="field-label">
                                            <i class="bx bx-user text-success me-2"></i>Requested By
                                        </td>
                                        <td class="field-value">{{ optional($requestARF->responsiblePerson)->fname }}
                                            {{ optional($requestARF->responsiblePerson)->lname ?? 'Not assigned' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="field-label">
                                            <i class="bx bx-building text-success me-2"></i>Division
                                        </td>
                                        <td class="field-value">{{ $sourceData['division']->division_name ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="field-label">
                                            <i class="bx bx-user-check text-success me-2"></i>Division Head
                                        </td>
                                        <td class="field-value">
                                            @if ($sourceData['division_head'])
                                                {{ $sourceData['division_head']->fname }}
                                                {{ $sourceData['division_head']->lname }}
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="field-label">
                                            <i class="bx bx-dollar text-success me-2"></i>Total Budget
                                        </td>
                                        <td class="field-value">
                                            ${{ number_format($totalBudget ?? $requestARF->total_amount ?? ($sourceData['total_budget'] ?? 0), 2) }}
                                        </td>
                                    </tr>
                                    @php
                                        $fundCodes = $sourceData['fund_codes'] ?? collect();
                                        $partnerDisplay = 'N/A';
                                        if ($fundCodes && $fundCodes->isNotEmpty()) {
                                            $partners = [];
                                            foreach ($fundCodes as $fc) {
                                                $name = optional($fc->partner)->name ?? null;
                                                if (!$name) {
                                                    $name = optional($fc->funder)->name ?? null;
                                                }
                                                if ($name && !in_array($name, $partners)) {
                                                    $partners[] = $name;
                                                }
                                            }
                                            if (!empty($partners)) {
                                                $partnerDisplay = implode(', ', $partners);
                                            }
                                        }
                                        if ($partnerDisplay === 'N/A' && $requestARF->partner) {
                                            $partnerDisplay = is_object($requestARF->partner) ? ($requestARF->partner->name ?? 'N/A') : $requestARF->partner;
                                        }
                                        if ($partnerDisplay === 'N/A' && $requestARF->funder) {
                                            $partnerDisplay = $requestARF->funder->name ?? 'N/A';
                                        }
                                        // Partner from fund code partner_id; fallback to fund code funder, then request_arfs.funder_id (backward compat)
                                        $funderDisplay = optional($requestARF->funder)->name ?? null;
                                        if (($funderDisplay === null || $funderDisplay === '') && $fundCodes && $fundCodes->isNotEmpty()) {
                                            $first = $fundCodes->first();
                                            $funderDisplay = optional($first->funder)->name ?? 'N/A';
                                        }
                                        $funderDisplay = $funderDisplay ?? 'N/A';
                                        $codeDisplay = $requestARF->extramural_code ?? null;
                                        if (($codeDisplay === null || $codeDisplay === '') && $fundCodes && $fundCodes->isNotEmpty()) {
                                            $first = $fundCodes->first();
                                            $codeDisplay = $first->code ?? null;
                                        }
                                        $hasExtramuralCode = !empty(trim((string) $codeDisplay)) && (string) $codeDisplay !== 'N/A';
                                    @endphp
                                    <tr>
                                        <td class="field-label">
                                            <i class="bx bx-handshake text-success me-2"></i>Partner
                                        </td>
                                        <td class="field-value">{{ $partnerDisplay }}</td>
                                    </tr>
                                    <tr>
                                        <td class="field-label">
                                            <i class="bx bx-tag text-success me-2"></i>Fund Type
                                        </td>
                                        <td class="field-value">{{ $requestARF->fundType->name ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="field-label">
                                            <i class="bx bx-building text-success me-2"></i>Funder
                                        </td>
                                        <td class="field-value">{{ $funderDisplay }}</td>
                                    </tr>
                                    @if($hasExtramuralCode)
                                    <tr>
                                        <td class="field-label">
                                            <i class="bx bx-hash text-success me-2"></i>Extramural Code
                                        </td>
                                        <td class="field-value">{{ $codeDisplay }}</td>
                                    </tr>
                                    @endif
                                    <tr>
                                        <td class="field-label">
                                            <i class="bx bx-info-circle text-success me-2"></i>Status
                                        </td>
                                        <td class="field-value">
                                            {!! display_memo_status_auto($requestARF) !!}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Source Information -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white border-0 py-3">
                                <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                                    <i class="bx bx-info-circle text-success"></i>
                                    Source Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="summary-table">
                                    <table class="table">
                                        <tbody>
                                            <tr>
                                                <td class="field-label">
                                                    <i class="bx bx-file text-success me-2"></i>Source Type
                                                </td>
                                                <td class="field-value">
                                                    @if ($requestARF->model_type === 'App\\Models\\Activity')
                                                        Activity
                                                    @elseif($requestARF->model_type === 'App\\Models\\NonTravelMemo')
                                                        Non-Travel Memo
                                                    @elseif($requestARF->model_type === 'App\\Models\\SpecialMemo')
                                                        Special Memo
                                                    @else
                                                        Unknown
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="field-label">
                                                    <i class="bx bx-link text-success me-2"></i>Source Link
                                                </td>
                                                <td class="field-value">
                                                    @if ($sourceModel)
                                                        @if ($requestARF->model_type === 'App\\Models\\Activity')
                                                            @if ($sourceData['matrix_id'])
                                                                <a href="{{ route('matrices.activities.show', ['matrix' => $sourceData['matrix_id'], 'activity' => $requestARF->source_id]) }}"
                                                                    class="text-success text-decoration-underline">
                                                                    View Activity
                                                                </a>
                                                            @else
                                                                <span class="text-muted">Activity (No Matrix)</span>
                                                            @endif
                                                        @elseif($requestARF->model_type === 'App\\Models\\NonTravelMemo')
                                                            <a href="{{ route('non-travel.show', $requestARF->source_id) }}"
                                                                class="text-success text-decoration-underline">
                                                                View Non-Travel Memo
                                                            </a>
                                                        @elseif($requestARF->model_type === 'App\\Models\\SpecialMemo')
                                                            <a href="{{ route('special-memo.show', $requestARF->source_id) }}"
                                                                class="text-success text-decoration-underline">
                                                                View Special Memo
                                                            </a>
                                                        @endif
                                                    @else
                                                        <span class="text-muted">N/A</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="field-label">
                                                    <i class="bx bx-edit text-success me-2"></i>Title
                                                </td>
                                                <td class="field-value">
                                                    {{ $sourceData['title'] ?? ($sourceData['activity_title'] ?? 'N/A') }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="field-label">
                                                    <i class="bx bx-calendar text-success me-2"></i>Activity Dates
                                                </td>
                                                <td class="field-value">
                                                    @if (!empty($sourceData['start_date']) && !empty($sourceData['end_date']))
                                                        {{ \Carbon\Carbon::parse($sourceData['start_date'])->format('M d, Y') }}
                                                        -
                                                        {{ \Carbon\Carbon::parse($sourceData['end_date'])->format('M d, Y') }}
                                                    @elseif(!empty($sourceData['date_from']) && !empty($sourceData['date_to']))
                                                        {{ \Carbon\Carbon::parse($sourceData['date_from'])->format('M d, Y') }}
                                                        -
                                                        {{ \Carbon\Carbon::parse($sourceData['date_to'])->format('M d, Y') }}
                                                    @else
                                                        N/A
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="field-label">
                                                    <i class="bx bx-map text-success me-2"></i>Location
                                                </td>
                                                <td class="field-value">{{ $sourceData['location'] ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="field-label">
                                                    <i class="bx bx-user text-success me-2"></i>Responsible Person
                                                </td>
                                                <td class="field-value">
                                                    @if ($sourceData['responsible_person'])
                                                        {{ $sourceData['responsible_person']->fname }}
                                                        {{ $sourceData['responsible_person']->lname }}
                                                    @else
                                                        N/A
                                                    @endif
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Budget Breakdown -->
                        @if (
                            $requestARF->model_type === 'App\\Models\\Activity' &&
                                !empty($sourceData['budget_breakdown']) &&
                                (is_array($sourceData['budget_breakdown']) ||
                                    (is_object($sourceData['budget_breakdown']) &&
                                        method_exists($sourceData['budget_breakdown'], 'count') &&
                                        $sourceData['budget_breakdown']->count() > 0)))
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-white border-0 py-3">
                                    <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                                        <i class="bx bx-calculator text-success"></i>
                                        Budget Details
                                    </h5>
                                </div>
                                <div class="card-body">
                                    @php
                                        $fundCodes = $sourceData['fund_codes'] ?? collect();
                                        $budgetItems = $sourceData['budget_breakdown'] ?? [];
                                        $grandTotal = 0;

                                        // Convert collection to array if needed
                                        if (is_object($budgetItems) && method_exists($budgetItems, 'toArray')) {
                                            $budgetItems = $budgetItems->toArray();
                                        }
                                    @endphp

                                    @if ($fundCodes->isNotEmpty())
                                        @foreach ($fundCodes as $fundCodeId => $fundCode)
                                            <h6 style="color: #2c3d50; font-weight: 600;">{{ $fundCode->activity }} -
                                                {{ $fundCode->code }} - ({{ $fundCode->fundType->name }})</h6>

                                            <div class="table-responsive">
                                                <table class="table table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>#</th>
                                                            <th>Cost Item</th>
                                                            <th>Unit Cost</th>
                                                            <th>Units</th>
                                                            <th>Days</th>
                                                            <th>Total</th>
                                                            <th>Description</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @php
                                                            $count = 1;
                                                            $fundTotal = 0;
                                                        @endphp

                                                        @if (isset($budgetItems[$fundCodeId]) && is_array($budgetItems[$fundCodeId]))
                                                            @foreach ($budgetItems[$fundCodeId] as $item)
                                                                @php
                                                                    $unitCost = floatval($item['unit_cost'] ?? 0);
                                                                    $units = floatval($item['units'] ?? 0);
                                                                    $days = floatval($item['days'] ?? 0);
                                                                    $total = $unitCost * $units * $days;
                                                                    $fundTotal += $total;
                                                                    $grandTotal += $total;
                                                                @endphp
                                                                <tr>
                                                                    <td>{{ $count }}</td>
                                                                    <td>{{ $item['cost'] ?? 'N/A' }}</td>
                                                                    <td class="text-end">{{ number_format($unitCost, 2) }}
                                                                    </td>
                                                                    <td class="text-end">{{ $units }}</td>
                                                                    <td class="text-end">{{ $days }}</td>
                                                                    <td class="text-end">{{ number_format($total, 2) }}
                                                                    </td>
                                                                    <td>{{ $item['cost'] ?? ($item['description'] ?? '') }}
                                                                    </td>
                                                                </tr>
                                                                @php
                                                                    $count++;
                                                                @endphp
                                                            @endforeach
                                                        @endif
                                                    </tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <th colspan="5" class="text-end">Fund Total</th>
                                                            <th class="text-end">{{ number_format($fundTotal, 2) }}</th>
                                                            <th></th>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="table-responsive">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Cost Item</th>
                                                        <th>Unit Cost</th>
                                                        <th>Units</th>
                                                        <th>Days</th>
                                                        <th>Total</th>
                                                        <th>Description</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @php
                                                        $count = 1;
                                                    @endphp

                                                    @if (is_array($budgetItems))
                                                        @foreach ($budgetItems as $fundCodeId => $items)
                                                            @if ($fundCodeId !== 'grand_total' && is_array($items))
                                                                @foreach ($items as $item)
                                                                    @php
                                                                        $unitCost = floatval($item['unit_cost'] ?? 0);
                                                                        $units = floatval($item['units'] ?? 0);
                                                                        $days = floatval($item['days'] ?? 0);
                                                                        $total = $unitCost * $units * $days;
                                                                        $grandTotal += $total;
                                                                    @endphp
                                                                    <tr>
                                                                        <td>{{ $count }}</td>
                                                                        <td>{{ $item['cost'] ?? 'N/A' }}</td>
                                                                        <td class="text-end">
                                                                            {{ number_format($unitCost, 2) }}</td>
                                                                        <td class="text-end">{{ $units }}</td>
                                                                        <td class="text-end">{{ $days }}</td>
                                                                        <td class="text-end">
                                                                            {{ number_format($total, 2) }}</td>
                                                                        <td>{{ $item['cost'] ?? ($item['description'] ?? '') }}
                                                                        </td>
                                                                    </tr>
                                                                    @php
                                                                        $count++;
                                                                    @endphp
                                                                @endforeach
                                                            @endif
                                                        @endforeach
                                                    @endif
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <th colspan="5" class="text-end">Grand Total</th>
                                                        <th class="text-end">{{ number_format($grandTotal, 2) }}</th>
                                                        <th></th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @elseif(
                            $requestARF->model_type !== 'App\\Models\\Activity' &&
                                !empty($sourceData['budget_breakdown']) &&
                                is_array($sourceData['budget_breakdown']))
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-white border-0 py-3">
                                    <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                                        <i class="bx bx-calculator text-warning"></i>
                                        Budget Breakdown
                                    </h5>
                                </div>
                                <div class="card-body">
                                    @php
                                        $budgetBreakdown = $sourceData['budget_breakdown'] ?? [];
                                        $fundCodes = $sourceData['fund_codes'] ?? collect();
                                        $isNonTravelMemo = $requestARF->model_type === 'App\\Models\\NonTravelMemo';
                                        // Decode JSON string if needed
                                        if (is_string($budgetBreakdown)) {
                                            $budgetBreakdown = json_decode($budgetBreakdown, true) ?? [];
                                        }
                                        $grandTotal = 0;
                                    @endphp

                                    @if (!empty($budgetBreakdown))
                                        @php
                                            // Check if budget is structured by fund codes (keyed by fund_code_id)
                                            $isFundCodeStructure = false;
                                            foreach ($budgetBreakdown as $key => $value) {
                                                if (is_numeric($key) && is_array($value)) {
                                                    $isFundCodeStructure = true;
                                                    break;
                                                }
                                            }
                                        @endphp
                                        
                                        @if ($isFundCodeStructure && $fundCodes->isNotEmpty())
                                            {{-- Budget structured by fund codes --}}
                                            @foreach ($budgetBreakdown as $fundCodeId => $items)
                                                @if ($fundCodeId !== 'grand_total' && is_array($items) && !empty($items))
                                                    @php
                                                        $fundCode = $fundCodes[$fundCodeId] ?? null;
                                                        $fundTotal = 0;
                                                    @endphp
                                                    
                                                    <div class="mb-4">
                                                        <div class="fund-code-header p-3 mb-3">
                                                            <h6 class="fw-bold mb-0">
                                                                @if($fundCode)
                                                                    {{ $fundCode->activity }} - {{ $fundCode->code }} - ({{ $fundCode->fundType->name ?? 'N/A' }})@if($fundCode->funder) - {{ $fundCode->funder->name }}@endif
                                                                @else
                                                                    Fund Code ID: {{ $fundCodeId }}
                                                                @endif
                                                            </h6>
                                                        </div>
                                                        
                                                        <div class="table-responsive">
                                                            <table class="table table-bordered budget-table {{ $isNonTravelMemo ? 'non-travel-memo' : '' }}">
                                                                <thead class="table-light">
                                                                    <tr>
                                                                        <th class="fw-bold" style="width: 40px;">#</th>
                                                                        <th class="fw-bold cost-item">Description</th>
                                                                        <th class="fw-bold text-end" style="width: 100px;">Unit Cost</th>
                                                                        <th class="fw-bold text-end" style="width: 80px;">Quantity</th>
                                                                        <th class="fw-bold text-end" style="width: 120px;">Total</th>
                                                                        @if(!$isNonTravelMemo)
                                                                            <th class="fw-bold description">Description</th>
                                                                        @endif
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @php $count = 1; @endphp
                                                                    @foreach($items as $item)
                                                                        @php
                                                                            $quantity = $item['quantity'] ?? $item['units'] ?? 1;
                                                                            $unitCost = (float)($item['unit_cost'] ?? 0);
                                                                            $total = $unitCost * (float)$quantity;
                                                                            $fundTotal += $total;
                                                                            $grandTotal += $total;
                                                                        @endphp
                                                                        <tr>
                                                                            <td>{{ $count++ }}</td>
                                                                            <td class="cost-item">
                                                                                @if($isNonTravelMemo)
                                                                                    <div class="text-wrap" style="word-wrap: break-word; overflow-wrap: break-word; white-space: normal; line-height: 1.3;">
                                                                                        {{ $item['cost'] ?? $item['description'] ?? 'N/A' }}
                                                                                    </div>
                                                                                @else
                                                                                    {{ $item['cost'] ?? $item['description'] ?? 'N/A' }}
                                                                                @endif
                                                                            </td>
                                                                            <td class="text-end">${{ number_format($unitCost, 2) }}</td>
                                                                            <td class="text-end">{{ $quantity }}</td>
                                                                            <td class="text-end fw-bold">${{ number_format($total, 2) }}</td>
                                                                            @if(!$isNonTravelMemo)
                                                                                <td class="description">{{ $item['description'] ?? 'N/A' }}</td>
                                                                            @endif
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                                <tfoot class="table-light">
                                                                    <tr>
                                                                        <th colspan="{{ $isNonTravelMemo ? '4' : '5' }}" class="text-end">Total:</th>
                                                                        <th class="text-end text-success">${{ number_format($fundTotal, 2) }}</th>
                                                                        @if(!$isNonTravelMemo)
                                                                            <th></th>
                                                                        @endif
                                                                    </tr>
                                                                </tfoot>
                                                            </table>
                                                        </div>
                                                    </div>
                                                @endif
                                            @endforeach
                                            
                                            @if ($grandTotal > 0)
                                                <div class="grand-total text-end mt-3">
                                                    <h5 class="fw-bold mb-0">
                                                        <i class="bx bx-dollar me-2"></i>
                                                        Grand Total: ${{ number_format($grandTotal, 2) }}
                                                    </h5>
                                                </div>
                                            @endif
                                        @else
                                            {{-- Simple array structure --}}
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-sm budget-table {{ $isNonTravelMemo ? 'non-travel-memo' : '' }}">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 40px;">#</th>
                                                            <th class="cost-item">Description</th>
                                                            <th class="text-end" style="width: 100px;">Unit Cost</th>
                                                            <th class="text-end" style="width: 80px;">Quantity</th>
                                                            <th class="text-end" style="width: 120px;">Total</th>
                                                            @if(!$isNonTravelMemo)
                                                                <th class="description">Description</th>
                                                            @endif
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @php $count = 1; @endphp
                                                        @foreach ($budgetBreakdown as $index => $item)
                                                            @if (is_array($item))
                                                                @php
                                                                    $quantity = (float) ($item['quantity'] ?? $item['units'] ?? 1);
                                                                    $unitCost = (float) ($item['unit_cost'] ?? 0);
                                                                    $itemTotal = $unitCost * $quantity;
                                                                    $grandTotal += $itemTotal;
                                                                @endphp
                                                                <tr>
                                                                    <td>{{ $count++ }}</td>
                                                                    <td class="cost-item">
                                                                        @if($isNonTravelMemo)
                                                                            <div class="text-wrap" style="word-wrap: break-word; overflow-wrap: break-word; white-space: normal; line-height: 1.3;">
                                                                                {{ $item['cost'] ?? $item['description'] ?? 'Item ' . $index }}
                                                                            </div>
                                                                        @else
                                                                            {{ $item['cost'] ?? $item['description'] ?? 'Item ' . $index }}
                                                                        @endif
                                                                    </td>
                                                                    <td class="text-end">${{ number_format($unitCost, 2) }}</td>
                                                                    <td class="text-end">{{ $quantity }}</td>
                                                                    <td class="text-end fw-bold">${{ number_format($itemTotal, 2) }}</td>
                                                                    @if(!$isNonTravelMemo)
                                                                        <td class="description">{{ $item['description'] ?? 'N/A' }}</td>
                                                                    @endif
                                                                </tr>
                                                            @endif
                                                        @endforeach
                                                    </tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <th colspan="{{ $isNonTravelMemo ? '4' : '5' }}" class="text-end">Grand Total</th>
                                                            <th class="text-end">${{ number_format($grandTotal, 2) }}</th>
                                                            @if(!$isNonTravelMemo)
                                                                <th></th>
                                                            @endif
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        @endif
                                    @else
                                        <div class="text-center text-muted py-4">
                                            <i class="bx bx-calculator display-4 mb-3"></i>
                                            <p class="mb-0">No budget breakdown available</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Internal Participants - Only show for Activities and Special Memos, not for Non-Travel Memos -->
                        @if ($requestARF->model_type !== 'App\\Models\\NonTravelMemo' && !empty($sourceData['internal_participants']))
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-white border-0 py-3">
                                    <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                                        <i class="bx bx-user text-info"></i>
                                        Internal Participants
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover budget-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Participant Name</th>
                                                    <th>Division</th>
                                                    <th>Duty Station</th>
                                                    <th>No. of Days</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $internalParticipants = $sourceData['internal_participants'] ?? [];
                                                    if (is_string($internalParticipants)) {
                                                        $internalParticipants =
                                                            json_decode($internalParticipants, true) ?? [];
                                                    }
                                                    if (!is_array($internalParticipants)) {
                                                        $internalParticipants = [];
                                                    }
                                                @endphp
                                                @php
                                                    $participantCount = 1;
                                                @endphp
                                                @forelse($internalParticipants as $participantId => $participantData)
                                                    @php
                                                        $staff = App\Models\Staff::where('staff_id', $participantId)
                                                            ->with(['division'])
                                                            ->first();
                                                        $participantName = $staff
                                                            ? $staff->fname . ' ' . $staff->lname
                                                            : 'Unknown Staff';
                                                        $division =
                                                            $staff && $staff->division
                                                                ? $staff->division->division_name
                                                                : 'N/A';
                                                        $dutyStation = $staff
                                                            ? $staff->duty_station_name ??
                                                                ($staff->duty_station ?? 'N/A')
                                                            : 'N/A';
                                                        $days = is_array($participantData)
                                                            ? $participantData['days'] ?? 1
                                                            : 1;
                                                    @endphp
                                                    <tr>
                                                        <td class="fw-medium">{{ $participantCount }}.
                                                            {{ $participantName }}</td>
                                                        <td>{{ $division }}</td>
                                                        <td>{{ $dutyStation }}</td>
                                                        <td class="text-center">
                                                            <span class="badge bg-primary">{{ $days }}
                                                                {{ $days == 1 ? 'day' : 'days' }}</span>
                                                        </td>
                                                    </tr>
                                                    @php
                                                        $participantCount++;
                                                    @endphp
                                                @empty
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted py-4">
                                                            <i class="bx bx-user-x display-6 mb-2"></i>
                                                            <p class="mb-0">No internal participants found</p>
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Request for Approval -->
                        @if ($sourceData['activity_request_remarks'] && $sourceData['activity_request_remarks'] !== 'N/A')
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-white border-0 py-3">
                                    <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                                        <i class="bx bx-message-detail text-success"></i>
                                        Request for Approval
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-0 lh-lg text-dark">{!! $sourceData['activity_request_remarks'] !!}</div>
                                </div>
                            </div>
                        @endif

                        @include('partials.parent-based-disclaimer', ['disclaimerData' => $disclaimerData ?? [], 'documentType' => 'arf'])
                    </div>

                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Quick Approval Status -->
                        <div class="card sidebar-card border-0 mb-4">
                            <div class="card-header border-0 py-3"
                                style="background: linear-gradient(135deg, #FFFFFF 0%, #FFFFFF 100%);">

                                <div class="d-flex gap-3" style="float: right;">
                                    @if($requestARF->overall_status === 'approved')

                                    <a href="{{ route('request-arf.print', $requestARF) }}"
                                        class="btn btn-primary d-flex align-items-center gap-2" target="_blank">
                                        <i class="bx bx-printer"></i>
                                        <span>Print PDF</span>
                                    </a>
                                    @endif
                                </div>
                                <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                                    <i class="bx bx-trending-up text-success"></i>
                                    Approval Progress
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted small">Current Level</span>
                                        @php
                                            $currentStepNumber = 1;
                                            if (!empty($approvalLevels) && is_array($approvalLevels)) {
                                                foreach ($approvalLevels as $index => $level) {
                                                    if ($level['is_current']) {
                                                        $currentStepNumber = $index + 1;
                                                        break;
                                                    }
                                                }
                                            }
                                        @endphp
                                        <span class="badge bg-primary fs-6">Step {{ $currentStepNumber }}</span>
                                    </div>
                                    @if ($requestARF->workflow_definition)
                                        <div class="mb-2">
                                            <small class="text-muted">Role:</small><br>
                                            <strong>{{ $requestARF->workflow_definition->role ?? 'Not specified' }}</strong>
                                        </div>
                                    @endif
                                    @if ($requestARF->current_actor)
                                        <div class="mb-2">
                                            <small class="text-muted">Current Approver:</small><br>
                                            <strong>{{ $requestARF->current_actor->fname }}
                                                {{ $requestARF->current_actor->lname }}</strong>
                                        </div>
                                    @endif
                                    
                                    @php
                                        $nextApprover = null;
                                        if (!empty($approvalLevels) && is_array($approvalLevels)) {
                                            foreach ($approvalLevels as $index => $level) {
                                                if ($level['is_current'] && isset($approvalLevels[$index + 1])) {
                                                    $nextLevel = $approvalLevels[$index + 1];
                                                    $nextApprover = $nextLevel['approver'] ?? null;
                                                    break;
                                                }
                                            }
                                        }
                                    @endphp
                                    
                                    @if($nextApprover)
                                        <div class="mb-2">
                                            <small class="text-muted">Next Approver:</small><br>
                                            <strong>{{ $nextApprover->fname ?? '' }} {{ $nextApprover->lname ?? '' }}</strong>
                                        </div>
                                    @endif
                                </div>

                                @if (!empty($approvalLevels))
                                    <div class="mt-3">
                                        <small class="text-muted d-block mb-2">Approval Levels:</small>
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach ($approvalLevels as $index => $level)
                                                <span
                                                    class="badge bg-{{ $level['is_completed'] ? 'success' : ($level['is_current'] ? 'primary' : 'secondary') }} small">
                                                    Step {{ $index + 1 }}. {{ $level['role'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Enhanced Approval Actions -->
                        @if (can_take_action_generic($requestARF) || is_with_creator_generic($requestARF))
                            <div class="card border-0 shadow-lg mb-4"
                                style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
                                <div class="card-header bg-transparent border-0 py-4"
                                    style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 12px 12px 0 0;">
                                    <h6 class="mb-0 fw-bold text-gray-800 d-flex align-items-center gap-2"
                                        style="color: #1f2937;">
                                        <i class="bx bx-check-circle" style="color: #059669;"></i>
                                        Approval Actions
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <form action="{{ route('request-arf.approve', $requestARF) }}" method="POST"
                                        id="approvalForm">
                                        @csrf
                                        <div class="row">
                                            <div class="col-12">
                                                <div class="mb-3">
                                                    <label for="comment" class="form-label">Comments (Optional)</label>
                                                    <textarea class="form-control" id="comment" name="comment" rows="3"
                                                        placeholder="Add any comments about your decision..."></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <button type="button" class="btn btn-success" data-bs-toggle="modal"
                                                data-bs-target="#approvalModal">
                                                <i class="bx bx-check me-1"></i> Proceed
                                            </button>
                                            <button type="submit" name="action" value="rejected"
                                                class="btn btn-danger">
                                                <i class="bx bx-x me-1"></i> Rejected
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endif

                        <!-- Approval Trail -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-transparent border-0 py-3">
                                <h6 class="mb-0 fw-bold text-success d-flex align-items-center gap-2">
                                    <i class="bx bx-history"></i>
                                    Approval Trail
                                </h6>
                            </div>
                            <div class="card-body">
                                @if ($requestARF->approvalTrails->count() > 0)
                                    @include('partials.approval-trail', ['resource' => $requestARF])
                                @else
                                    <div class="text-center text-muted py-4">
                                        <i class="bx bx-time bx-lg mb-3"></i>
                                        <p class="mb-0">No approval actions have been taken yet.</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Submit for Approval -->
                        @if (in_array($requestARF->overall_status, ['draft', 'returned']) && is_with_creator_generic($requestARF))
                    </div>
    @endif
    </div>
    </div>
    </div>
    </div>
    @endif

    <!-- Approval Modal -->
    <div class="modal fade" id="approvalModal" tabindex="-1" aria-labelledby="approvalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #119A48 0%, #0d7a3a 100%);">
                    <h5 class="modal-title text-white" id="approvalModalLabel">
                        <i class="bx bx-check-circle me-2"></i>Approve Activity Request
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                @php
                    $fcForModal = $sourceData['fund_codes'] ?? collect();
                    $modalPartners = $fcForModal->pluck('partner')->filter()->unique('id')->values();
                    $defaultFunderId = $requestARF->funder_id ?? ($fcForModal->isNotEmpty() ? optional($fcForModal->first())->funder_id : null);
                    $defaultExtramuralCode = $requestARF->extramural_code ?? ($fcForModal->isNotEmpty() ? optional($fcForModal->first())->code : '');
                @endphp
                <form action="{{ route('request-arf.approve', $requestARF) }}" method="POST" id="approvalModalForm">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="modal_partner_display" class="form-label">
                                        <i class="bx bx-handshake text-success me-1"></i>Partner
                                    </label>
                                    <select class="form-select w-100 bg-light" id="modal_partner_display" style="width: 100%;" disabled>
                                        <option value="">—</option>
                                        @foreach ($modalPartners as $p)
                                            <option value="{{ $p->id }}" {{ $loop->first ? 'selected' : '' }}>{{ $p->name ?? 'N/A' }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">From budget / fund code (display only)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="modal_funder_id" class="form-label">
                                        <i class="bx bx-building text-success me-1"></i>Funder <span class="text-danger">*</span>
                                    </label>
                                    <input type="hidden" name="funder_id" value="{{ $defaultFunderId }}">
                                    <select class="form-select w-100 bg-light" id="modal_funder_id" disabled style="width: 100%;">
                                        <option value="">—</option>
                                        @foreach (\App\Models\Funder::where('is_active', true)->orderBy('name', 'asc')->get() as $funder)
                                            <option value="{{ $funder->id }}" {{ (int) $defaultFunderId === (int) $funder->id ? 'selected' : '' }}>{{ $funder->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="modal_extramural_code" class="form-label">
                                        <i class="bx bx-hash text-success me-1"></i>Extramural Code <span
                                            class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control bg-light" id="modal_extramural_code"
                                        name="extramural_code" value="{{ old('extramural_code', $defaultExtramuralCode) }}"
                                        placeholder="Enter extramural code" required readonly>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="modal_comment" class="form-label">
                                <i class="bx bx-message-detail text-success me-1"></i>Comments (Optional)
                            </label>
                            <textarea class="form-control" id="modal_comment" name="comment" rows="3"
                                placeholder="Add any comments about your approval decision...">{{ old('comment') }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bx bx-x me-1"></i>Cancel
                        </button>
                        <button type="submit" name="action" value="approved" class="btn btn-success">
                            <i class="bx bx-check me-1"></i>Approve ARF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Funder and Partner are readonly in approval modal (no Select2 needed)

            // Copy comment from main form to modal when opening
            $('#approvalModal').on('show.bs.modal', function() {
                const mainComment = $('#comment').val();
                $('#modal_comment').val(mainComment);
            });

            // Copy comment from modal to main form when closing
            $('#approvalModal').on('hide.bs.modal', function() {
                const modalComment = $('#modal_comment').val();
                $('#comment').val(modalComment);
            });

            // Approval form handling
            $('#approvalForm').on('submit', function(e) {
                const action = $('button[type="submit"]:focus').val();
                if (!action) {
                    e.preventDefault();
                    alert('Please select an action (Proceed or Rejected)');
                    return false;
                }

                if (action === 'rejected' && !confirm(
                        'Are you sure you want to reject this ARF request? A new ARF will need to be created.'
                        )) {
                    e.preventDefault();
                    return false;
                }
            });

            // Modal form handling
            $('#approvalModalForm').on('submit', function(e) {
                // No confirmation dialog needed
            });
        });
    </script>
@endpush
