@extends('layouts.app')

@section('title', 'View Special Memo')

@section('styles')
<style>
    .status-badge {
        font-size: 0.875rem;
        font-weight: 600;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        text-transform: capitalize;
    }
    
    .status-approved { background: #d1fae5; color: #059669; border: 1px solid #a7f3d0; }
    .status-rejected { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
    .status-pending { background: #fef3c7; color: #d97706; border: 1px solid #fcd34d; }
    .status-draft { background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db; }
    .status-returned { background: #dbeafe; color: #2563eb; border: 1px solid #93c5fd; }
    
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
    
    .content-section.bg-blue { border-left-color: #3b82f6; }
    .content-section.bg-green { border-left-color: #10b981; }
    .content-section.bg-purple { border-left-color: #8b5cf6; }
    
    .sidebar-card {
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        border-radius: 0.75rem;
        overflow: hidden;
    }
    
    .location-badge, .budget-item {
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
    
    .budget-table td {
        vertical-align: middle;
        font-size: 0.9rem;
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
    
    .matrix-card {
        background: #fff;
        border-radius: 1.25rem;
        box-shadow: 0 4px 24px rgba(17,154,72,0.08);
        border: none;
    }
    .matrix-card .card-header {
        border-radius: 1.25rem 1.25rem 0 0;
        background: linear-gradient(90deg, #e9f7ef 0%, #fff 100%);
        border-bottom: 1px solid #e9f7ef;
    }
    .matrix-card .card-body {
        border-radius: 0 0 1.25rem 1.25rem;
    }
</style>
@endsection

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Enhanced Header -->
    <div class="bg-white border-b border-gray-200 shadow-sm">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center py-4">
                <div>
                    <h1 class="h2 fw-bold text-dark mb-0">View Special Memo</h1>
                    <p class="text-muted mb-0">Review and manage special memo details</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="{{ route('special-memo.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                        <i class="bx bx-arrow-back"></i>
                        <span>Back to List</span>
                    </a>
                    @if($specialMemo->overall_status === 'draft' && $specialMemo->staff_id == user_session('staff_id'))
                        <a href="{{ route('special-memo.edit', $specialMemo) }}" class="btn btn-warning d-flex align-items-center gap-2">
                            <i class="bx bx-edit"></i>
                            <span>Edit Memo</span>
                        </a>
                    @endif
                    @if($specialMemo->overall_status === 'pending')
                        <a href="{{ route('special-memo.status', $specialMemo) }}" class="btn btn-info d-flex align-items-center gap-2">
                            <i class="bx bx-info-circle"></i>
                            <span>Approval Status</span>
                        </a>
                    @endif
                    @if($specialMemo->overall_status === 'approved')
                        <a href="{{ route('special-memo.print', $specialMemo) }}" target="_blank" class="btn btn-primary d-flex align-items-center gap-2">
                            <i class="bx bx-printer"></i>
                            <span>Print PDF</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid py-4">
        @php
            // Decode JSON fields if they are strings
            $budget = is_string($specialMemo->budget) 
                ? json_decode(stripslashes($specialMemo->budget), true) 
                : $specialMemo->budget;
            
            // Handle double-encoded JSON (sometimes happens with form submissions)
            if (is_string($budget) && !is_array($budget)) {
                $budget = json_decode($budget, true);
            }

            $attachments = is_string($specialMemo->attachment) 
                ? json_decode($specialMemo->attachment, true) 
                : $specialMemo->attachment;

            $internalParticipants = is_string($specialMemo->internal_participants)
                ? json_decode($specialMemo->internal_participants, true)
                : $specialMemo->internal_participants;

            // Ensure variables are arrays
            $budget = is_array($budget) ? $budget : [];
            $attachments = is_array($attachments) ? $attachments : [];
            $internalParticipants = is_array($internalParticipants) ? $internalParticipants : [];

            // Parse budget structure and organize by fund codes
            $budgetByFundCode = [];
            $totalBudget = 0;
            
            if (!empty($budget)) {
                foreach ($budget as $key => $item) {
                    if ($key === 'grand_total') {
                        $totalBudget = floatval($item);
                    } elseif (is_array($item)) {
                        // Handle array of budget items (like "29" => [{...}])
                        $fundCodeId = $key;
                        $budgetByFundCode[$fundCodeId] = $item;
                    } elseif (is_numeric($item)) {
                        $totalBudget += floatval($item);
                    }
                }
            }
            
            // Fetch fund code details for display
            $fundCodes = [];
            if (!empty($budgetByFundCode)) {
                $fundCodeIds = array_keys($budgetByFundCode);
                $fundCodes = \App\Models\FundCode::whereIn('id', $fundCodeIds)->get()->keyBy('id');
            }
            
            // If no grand_total found, calculate from items
            if ($totalBudget == 0 && !empty($budgetByFundCode)) {
                foreach ($budgetByFundCode as $fundCodeId => $items) {
                    foreach ($items as $item) {
                        if (isset($item['unit_cost']) && isset($item['units'])) {
                            $totalBudget += floatval($item['unit_cost']) * floatval($item['units']);
                        }
                    }
                }
            }
            
            // Debug logging
            if (config('app.debug')) {
                \Log::info('Budget parsing debug:', [
                    'raw_budget' => $specialMemo->budget,
                    'parsed_budget' => $budget,
                    'budget_by_fund_code' => $budgetByFundCode,
                    'fund_codes' => $fundCodes,
                    'total_budget' => $totalBudget
                ]);
            }
        @endphp

        <!-- Summary Table -->
        <div class="summary-table mb-4">
            <div class="card-header bg-light border-0 py-3">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bx bx-table me-2 text-primary"></i>Special Memo Summary
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <tbody>
                        <!-- Basic Information -->
                        <tr>
                            <td class="field-label">Memo ID</td>
                            <td class="field-value">#{{ $specialMemo->id }}</td>
                            <td class="field-label">Status</td>
                            <td class="field-value">
                                @php
                                    $statusBadgeClass = [
                                        'draft' => 'status-draft',
                                        'pending' => 'status-pending',
                                        'approved' => 'status-approved',
                                        'rejected' => 'status-rejected',
                                        'returned' => 'status-returned',
                                    ][$specialMemo->overall_status] ?? 'status-draft';
                                @endphp
                                <span class="status-badge {{ $statusBadgeClass }}">
                                    {{ ucfirst($specialMemo->overall_status ?? 'draft') }}
                                </span>
                                @if($specialMemo->overall_status === 'pending')
                                    <a href="{{ route('special-memo.status', $specialMemo) }}" class="btn btn-sm btn-outline-info ms-2">
                                        <i class="bx bx-info-circle me-1"></i>View Status
                                    </a>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="field-label">Activity Title</td>
                            <td class="field-value" colspan="3">{{ $specialMemo->activity_title ?? 'Not specified' }}</td>
                        </tr>
                        <tr>
                            <td class="field-label">Requestor</td>
                            <td class="field-value">
                                {{ optional($specialMemo->staff)->first_name }} {{ optional($specialMemo->staff)->last_name ?? 'Not assigned' }}
                            </td>
                            <td class="field-label">Division</td>
                            <td class="field-value">
                                {{ optional($specialMemo->division)->division_name ?? 'Not assigned' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="field-label">Date Range</td>
                            <td class="field-value">
                                {{ $specialMemo->formatted_dates ?? 'Not specified' }}
                            </td>
                            <td class="field-label">Request Type</td>
                            <td class="field-value">
                                {{ optional($specialMemo->requestType)->name ?? 'Not specified' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="field-label">Key Result Area</td>
                            <td class="field-value" colspan="3">{{ $specialMemo->key_result_area ?? 'Not specified' }}</td>
                        </tr>
                        
                        <!-- Location Information -->
                        <tr>
                            <td class="field-label">Location(s)</td>
                            <td class="field-value" colspan="3">
                                @if($specialMemo->locations)
                                    <span class="badge bg-primary me-1">{{ $specialMemo->locations }}</span>
                                @else
                                    <span class="text-muted">No locations specified</span>
                                @endif
                            </td>
                        </tr>
                        
                        <!-- Participants Information -->
                        <tr>
                            <td class="field-label">Total Participants</td>
                            <td class="field-value fw-bold text-success">{{ $specialMemo->total_participants ?? 0 }}</td>
                            <td class="field-label">Internal Participants</td>
                            <td class="field-value">
                                @if(is_array($internalParticipants) && count($internalParticipants) > 0)
                                    {{ count($internalParticipants) }} staff member(s)
                                @else
                                    <span class="text-muted">No internal participants</span>
                                @endif
                            </td>
                        </tr>
                        
                        <!-- Budget Information -->
                        <tr>
                            <td class="field-label">Total Budget</td>
                            <td class="field-value fw-bold text-success">${{ number_format($totalBudget, 2) }}</td>
                            <td class="field-label">Fund Codes</td>
                            <td class="field-value">
                                @if(!empty($budgetByFundCode))
                                    @foreach($budgetByFundCode as $fundCodeId => $items)
                                        @php
                                            $fundCode = $fundCodes[$fundCodeId] ?? null;
                                        @endphp
                                        <div class="mb-1">
                                            @if($fundCode)
                                                <span class="badge bg-primary me-1">{{ $fundCode->code }}</span>
                                                @if($fundCode->fundType)
                                                    <small class="text-muted">({{ $fundCode->fundType->name ?? 'N/A' }})</small>
                                                @endif
                                            @else
                                                <span class="badge bg-secondary me-1">ID: {{ $fundCodeId }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                @else
                                    <span class="text-muted">No budget codes</span>
                                @endif
                            </td>
                        </tr>
                        
                        <!-- Attachments -->
                        <tr>
                            <td class="field-label">Attachments</td>
                            <td class="field-value" colspan="3">
                                @if(!empty($attachments) && count($attachments) > 0)
                                    {{ count($attachments) }} file(s) attached
                                @else
                                    <span class="text-muted">No attachments</span>
                                @endif
                            </td>
                        </tr>
                        
                        <!-- Last Updated -->
                        <tr>
                            <td class="field-label">Created Date</td>
                            <td class="field-value">
                                {{ $specialMemo->created_at ? $specialMemo->created_at->format('M d, Y H:i') : 'Not available' }}
                            </td>
                            <td class="field-label">Last Updated</td>
                            <td class="field-value">
                                {{ $specialMemo->updated_at ? $specialMemo->updated_at->format('M d, Y H:i') : 'Not available' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <!-- Enhanced Memo Information Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">
                            <i class="bx bx-info-circle me-2 text-primary"></i>Special Memo Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="memo-meta-row">
                            <div class="memo-meta-item">
                                <i class="bx bx-calendar-alt"></i>
                                <span class="memo-meta-label">Date Range:</span>
                                <span class="memo-meta-value">{{ $specialMemo->formatted_dates ?? 'Not set' }}</span>
                            </div>
                            <div class="memo-meta-item">
                                <i class="bx bx-user"></i>
                                <span class="memo-meta-label">Requestor:</span>
                                <span class="memo-meta-value">{{ optional($specialMemo->staff)->first_name }} {{ optional($specialMemo->staff)->last_name ?? 'Not assigned' }}</span>
                            </div>
                            <div class="memo-meta-item">
                                <i class="bx bx-building"></i>
                                <span class="memo-meta-label">Division:</span>
                                <span class="memo-meta-value">{{ optional($specialMemo->division)->division_name ?? 'Not assigned' }}</span>
                            </div>
                            <div class="memo-meta-item">
                                <i class="bx bx-cube"></i>
                                <span class="memo-meta-label">Request Type:</span>
                                <span class="memo-meta-value">{{ optional($specialMemo->requestType)->name ?? 'Not specified' }}</span>
                            </div>
                        </div>
                        
                        @if($specialMemo->overall_status !== 'approved')
                            <div class="mt-3 p-3" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-radius: 0.5rem; border: 1px solid #bfdbfe;">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="bx bx-user-check text-blue-600"></i>
                                    <span class="fw-semibold text-blue-900">Current Status</span>
                                </div>
                                <div class="memo-meta-row">
                                    <div class="memo-meta-item">
                                        <i class="bx bx-badge-check"></i>
                                        <span class="memo-meta-value">{{ ucfirst($specialMemo->overall_status ?? 'draft') }}</span>
                                    </div>
                                    @if($specialMemo->overall_status === 'pending')
                                        <div class="memo-meta-item">
                                            <i class="bx bx-time"></i>
                                            <span class="memo-meta-value">Awaiting Approval</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Activity Details -->
                <div class="card content-section bg-blue border-0 mb-4">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h6 class="mb-0 fw-bold text-primary d-flex align-items-center gap-2">
                            <i class="bx bx-detail"></i>
                            Activity Details
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-semibold">Activity Title</label>
                            <h5 class="fw-bold text-dark mb-0">{{ $specialMemo->activity_title ?? 'Not specified' }}</h5>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-semibold">Key Result Area</label>
                            <div class="bg-light rounded p-3 border">{{ $specialMemo->key_result_area ?? 'Not specified' }}</div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-semibold">Background</label>
                            <div class="bg-light rounded p-3 border" style="white-space: pre-line;">{{ $specialMemo->background ?? 'Not specified' }}</div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-semibold">Justification</label>
                            <div class="bg-light rounded p-3 border" style="white-space: pre-line;">{{ $specialMemo->justification ?? 'Not specified' }}</div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-semibold">Supporting Reasons</label>
                            <div class="bg-light rounded p-3 border" style="white-space: pre-line;">{{ $specialMemo->supporting_reasons ?? 'Not specified' }}</div>
                        </div>
                        
                        @if($specialMemo->remarks)
                            <div class="mb-4">
                                <label class="form-label text-muted small fw-semibold">Remarks</label>
                                <div class="bg-light rounded p-3 border" style="white-space: pre-line;">{{ $specialMemo->remarks }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Participants & Location -->
                <div class="card content-section bg-green border-0 mb-4">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h6 class="mb-0 fw-bold text-success d-flex align-items-center gap-2">
                            <i class="bx bx-group"></i>
                            Participants & Location
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label text-muted small fw-semibold">Location(s)</label>
                                <div class="location-badge">
                                    <i class="bx bx-map text-primary"></i>
                                    <span>{{ $specialMemo->locations ?? 'No locations specified' }}</span>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <label class="form-label text-muted small fw-semibold">Total Participants</label>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bx bx-user text-success"></i>
                                    <span class="fw-bold">{{ $specialMemo->total_participants ?? 0 }}</span>
                                    <span class="text-muted">participants</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label text-muted small fw-semibold">Internal Participants</label>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-info">{{ count($internalParticipants) }}</span>
                                    <span class="text-muted">staff members</span>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <label class="form-label text-muted small fw-semibold">External Participants</label>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-secondary">{{ $specialMemo->total_external_participants ?? 0 }}</span>
                                    <span class="text-muted">external</span>
                                </div>
                            </div>
                        </div>
                        
                        @if(!empty($internalParticipants))
                            <div class="mt-4">
                                <label class="form-label text-muted small fw-semibold">Internal Participants Details</label>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Staff</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Days</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($internalParticipants as $participant)
                                                <tr>
                                                    <td>
                                                        @if(isset($participant['staff']) && $participant['staff'])
                                                            {{ $participant['staff']->fname ?? '' }} {{ $participant['staff']->lname ?? '' }}
                                                        @else
                                                            <span class="text-muted">Unknown Staff</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $participant['participant_start'] ?? '-' }}</td>
                                                    <td>{{ $participant['participant_end'] ?? '-' }}</td>
                                                    <td>{{ $participant['participant_days'] ?? '-' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Activity Request Remarks -->
                @if($specialMemo->activity_request_remarks)
                    <div class="card content-section bg-purple border-0 mb-4">
                        <div class="card-header bg-transparent border-0 py-3">
                            <h6 class="mb-0 fw-bold text-purple d-flex align-items-center gap-2">
                                <i class="bx bx-comment-detail"></i>
                                Activity Request Remarks
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="bg-light rounded p-3 border" style="white-space: pre-line;">{{ $specialMemo->activity_request_remarks }}</div>
                        </div>
                    </div>
                @endif

                <!-- Budget Information -->
                <div class="card content-section bg-blue border-0 mb-4">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h6 class="mb-0 fw-bold text-primary d-flex align-items-center gap-2">
                            <i class="bx bx-money"></i>
                            Budget Information
                        </h6>
                    </div>
                    <div class="card-body">
                        @if(!empty($budget))
                          
                            
                          
                            
                            @if(!empty($budgetByFundCode))
                                @foreach($budgetByFundCode as $fundCodeId => $items)
                                    @php
                                        $fundCode = $fundCodes[$fundCodeId] ?? null;
                                        $fundCodeTotal = 0;
                                        foreach ($items as $item) {
                                            if (isset($item['unit_cost']) && isset($item['units'])) {
                                                $fundCodeTotal += floatval($item['unit_cost']) * floatval($item['units']);
                                            }
                                        }
                                    @endphp
                                    
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-3 p-3 fund-code-header">
                                            <div>
                                                <h6 class="mb-1 fw-bold text-primary">
                                                    @if($fundCode)
                                                        Fund Code: {{ $fundCode->code }}
                                                     
                                                    @else
                                                        Fund Code ID: {{ $fundCodeId }}
                                                    @endif
                                                </h6>
                                                @if($fundCode)
                                                    <div class="small text-muted">
                                                        @if($fundCode->fundType)
                                                            <span class="me-3">Type: {{ $fundCode->fundType->name ?? 'N/A' }}</span>
                                                        @endif
                                                        @if($fundCode->funder)
                                                            <span>Funder: {{ $fundCode->funder->name ?? 'N/A' }}</span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="text-end">
                                                <span class="fw-bold text-success fs-6">${{ number_format($fundCodeTotal, 2) }}</span>
                                                <small class="text-muted d-block">Fund Total</small>
                                            </div>
                                        </div>
                                        
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-sm mb-0 budget-table">
                                                <thead>
                                                    <tr>
                                                        <th>Cost Item</th>
                                                        <th>Description</th>
                                                        <th>Unit Cost</th>
                                                        <th>Units</th>
                                                        <th>Days</th>
                                                        <th>Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($items as $item)
                                                        @php
                                                            $itemTotal = 0;
                                                            if (isset($item['unit_cost']) && isset($item['units'])) {
                                                                $itemTotal = floatval($item['unit_cost']) * floatval($item['units']);
                                                            }
                                                        @endphp
                                                        <tr>
                                                            <td>{{ $item['cost'] ?? 'N/A' }}</td>
                                                            <td>{{ $item['description'] ?? 'N/A' }}</td>
                                                            <td class="text-end">${{ number_format(floatval($item['unit_cost'] ?? 0), 2) }}</td>
                                                            <td class="text-end">{{ $item['units'] ?? 'N/A' }}</td>
                                                            <td class="text-end">{{ $item['days'] ?? 'N/A' }}</td>
                                                            <td class="text-end fw-bold">${{ number_format($itemTotal, 2) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endforeach
                                
                                <!-- Grand Total Row -->
                                <div class="mt-4 p-3 budget-total-row rounded">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 fw-bold">Grand Total (All Fund Codes)</h6>
                                        <span class="fw-bold fs-5">${{ number_format($totalBudget, 2) }}</span>
                                    </div>
                                </div>
                            @else
                                <!-- Fallback: Show budget as key-value pairs if structure is different -->
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>Budget Item</th>
                                                <th>Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($budget as $key => $value)
                                                @if($key !== 'grand_total')
                                                    <tr>
                                                        <td>{{ $key }}</td>
                                                        <td>
                                                            @if(is_array($value))
                                                                <pre class="mb-0">{{ json_encode($value, JSON_PRETTY_PRINT) }}</pre>
                                                            @else
                                                                {{ $value }}
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @else
                            <div class="text-center text-muted py-4">
                                <i class="bx bx-money bx-lg mb-3"></i>
                                <p class="mb-0">No budget details</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">

                <!-- Attachments Card -->
                <div class="card sidebar-card border-0 mb-4">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h6 class="mb-0 fw-bold text-info d-flex align-items-center gap-2">
                            <i class="bx bx-paperclip"></i>
                            Attachments
                        </h6>
                    </div>
                    <div class="card-body">
                        @if(!empty($attachments) && count($attachments) > 0)
                            <div class="d-flex flex-column gap-2">
                                @foreach($attachments as $attachment)
                                    <a href="{{ asset('storage/' . ($attachment['path'] ?? '')) }}" target="_blank" class="attachment-item text-decoration-none">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bx bx-paperclip text-purple"></i>
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold text-dark">{{ $attachment['name'] ?? 'File' }}</div>
                                                <small class="text-muted">
                                                    {{ isset($attachment['size']) ? round($attachment['size'] / 1024, 2) . ' KB' : 'Unknown size' }}
                                                </small>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center text-muted py-4">
                                <i class="bx bx-paperclip bx-lg mb-3"></i>
                                <p class="mb-0">No attachments</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Approval Trail Section -->
                @if(isset($specialMemo->approvalTrails) && $specialMemo->approvalTrails->count() > 0)
                    @include('partials.approval-trail', ['resource' => $specialMemo])
                @else
                    <div class="card sidebar-card border-0 mb-4">
                        <div class="card-header bg-transparent border-0 py-3">
                            <h6 class="mb-0 fw-bold text-warning d-flex align-items-center gap-2">
                                <i class="bx bx-history"></i>
                                Approval Trail
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center text-muted py-4">
                                <i class="bx bx-time bx-lg mb-3"></i>
                                <p class="mb-0">No approval actions have been taken yet.</p>
                                @if($specialMemo->overall_status === 'draft')
                                    <small>Submit this special memo for approval to start the approval trail.</small>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Enhanced Approval Actions -->
                @if(can_take_action_generic($specialMemo))
                    <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                        <div class="card-header bg-transparent border-0 py-3">
                            <h6 class="mb-0 fw-bold text-success d-flex align-items-center gap-2">
                                <i class="bx bx-check-circle"></i>
                                Approval Actions - Level {{ $specialMemo->approval_level ?? 0 }}
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-3">
                                <i class="bx bx-info-circle me-2"></i>
                                <strong>Current Level:</strong> {{ $specialMemo->approval_level ?? 0 }}
                                @if($specialMemo->workflow_definition)
                                    - <strong>Role:</strong> {{ $specialMemo->workflow_definition->role ?? 'Not specified' }}
                                @endif
                            </div>
                            
                            <form action="{{ route('special-memo.update-status', $specialMemo) }}" method="POST" id="approvalForm">
                                @csrf
                                <input type="hidden" name="debug_approval" value="1">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="comment" class="form-label">Comments (Optional)</label>
                                            <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Add any comments about your decision..."></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-grid gap-2">
                                            <button type="submit" name="action" value="approved" class="btn btn-success w-100 d-flex align-items-center justify-content-center gap-2">
                                                <i class="bx bx-check"></i>
                                                Approve
                                            </button>
                                            <button type="submit" name="action" value="returned" class="btn btn-warning w-100 d-flex align-items-center justify-content-center gap-2">
                                                <i class="bx bx-undo"></i>
                                                Return
                                            </button>
                                            <button type="submit" name="action" value="rejected" class="btn btn-danger w-100 d-flex align-items-center justify-content-center gap-2">
                                                <i class="bx bx-x"></i>
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                <!-- Submit for Approval Section -->
                @if($specialMemo->overall_status === 'draft' && $specialMemo->staff_id == user_session('staff_id'))
                    <div class="card sidebar-card border-0" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);">
                        <div class="card-header bg-transparent border-0 py-3">
                            <h6 class="mb-0 fw-bold text-primary d-flex align-items-center gap-2">
                                <i class="bx bx-send"></i>
                                Submit for Approval
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Ready to submit this special memo for approval?</p>
                            <form action="{{ route('special-memo.submit-for-approval', $specialMemo) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                                    <i class="bx bx-send"></i>
                                    Submit for Approval
                                </button>
                            </form>
                            <div class="mt-3 p-3 bg-light rounded">
                                <small class="text-muted">
                                    <i class="bx bx-info-circle me-1"></i>
                                    <strong>Note:</strong> Once submitted, you won't be able to edit this memo until it's returned for revision.
                                </small>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Setup delete confirmation
        $('#deleteMemoForm').on('submit', function(e) {
            e.preventDefault();
            const form = this;
            
            Swal.fire({
                title: 'Are you sure?',
                text: "This will permanently delete this special memo.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bx bx-trash me-1"></i> Yes, delete it!',
                cancelButtonText: '<i class="bx bx-x me-1"></i> Cancel',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-secondary'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
</script>
@endpush