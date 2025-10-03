@extends('layouts.app')

@section('title', 'View Special Memo')

@section('styles')
<style>
    .matrix-card {
        background: #fff;
        border-radius: 1.25rem;
        box-shadow: 0 4px 24px rgba(17, 154, 72, 0.08);
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

    .approval-level-badge {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-weight: 600;
        font-size: 0.875rem;
    }

    .status-badge {
        font-size: 0.875rem;
        font-weight: 600;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        text-transform: capitalize;
    }
    
    .status-approved {
        background: #d1fae5;
        color: #059669;
        border: 1px solid #a7f3d0;
    }

    .status-rejected {
        background: #fee2e2;
        color: #dc2626;
        border: 1px solid #fca5a5;
    }

    .status-pending {
        background: #fef3c7;
        color: #d97706;
        border: 1px solid #fcd34d;
    }

    .status-draft {
        background: #f3f4f6;
        color: #6b7280;
        border: 1px solid #d1d5db;
    }

    .status-returned {
        background: #dbeafe;
        color: #2563eb;
        border: 1px solid #93c5fd;
    }
    
    .gradient-header {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    }

    /* Timeline Styles */
    .timeline {
        position: relative;
        margin: 0;
        padding: 0;
        list-style: none;
        max-height: 50vh;
        overflow-y: auto;
    }

    .timeline:before {
        content: '';
        position: absolute;
        left: 30px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e9ecef;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 30px;
        padding-left: 60px;
    }

    .timeline-item:last-child {
        margin-bottom: 0;
    }

    .timeline-badge {
        position: absolute;
        left: 18px;
        top: 0;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #fff;
        border: 2px solid #28a745;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }

    .timeline-badge.approved {
        border-color: #28a745;
        color: #28a745;
    }

    .timeline-badge.rejected {
        border-color: #dc3545;
        color: #dc3545;
    }

    .timeline-badge.returned {
        border-color: rgb(217, 136, 15);
        color: rgb(208, 149, 12);
    }

    .timeline-badge.submitted {
        border-color: rgb(17, 166, 211);
        color: rgb(27, 143, 216);
    }

    .timeline-time {
        font-size: 0.9rem;
        color: #888;
        margin-bottom: 2px;
    }

    .timeline-title {
        font-weight: 600;
        margin-bottom: 2px;
    }

    .timeline-remarks {
        color: #555;
        font-size: 0.95rem;
    }

    /* Hover effects */
    .card:hover {
        transform: translateY(-2px);
        transition: all 0.3s ease;
    }

    /* Button hover effects */
    .btn:hover {
        transform: translateY(-1px);
        transition: all 0.2s ease;
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
    
    /* Attachment Preview Modal Styles */
    #previewModal .modal-dialog {
        max-width: 90vw;
        margin: 1.75rem auto;
    }

    #previewModal .modal-body {
        min-height: 500px;
        max-height: 80vh;
        overflow: hidden;
    }

    #previewModal .modal-content {
        border-radius: 0.75rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    #previewModal .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 0.75rem 0.75rem 0 0;
        border: none;
    }

    #previewModal .btn-close {
        filter: invert(1);
    }

    #previewModal iframe {
        border-radius: 0.5rem;
    }

    #previewModal img {
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .preview-attachment {
        transition: all 0.2s ease;
    }

    .preview-attachment:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .table th {
        background-color: #f8f9fa;
        font-weight: 600;
        border-bottom: 2px solid #dee2e6;
    }

    .table td {
        vertical-align: middle;
    }

    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
        line-height: 1.5;
        border-radius: 0.375rem;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        #previewModal .modal-dialog {
            max-width: 95vw;
            margin: 0.5rem auto;
        }
        
        #previewModal .modal-body {
            min-height: 400px;
            max-height: 70vh;
        }
        
        #previewModalBody {
            padding: 1rem;
        }
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
    
    /* Enhanced Summary Table Styling */
    .summary-table .field-label {
        font-weight: 600;
        color: #374151;
        min-width: 150px;
        padding: 1rem 1rem 1rem 1.5rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-right: 3px solid #e2e8f0;
        position: relative;
    }

    .summary-table .field-label i {
        font-size: 1.1rem;
        margin-right: 0.5rem;
        vertical-align: middle;
    }

    .summary-table .field-value {
        color: #1f2937;
        font-weight: 500;
        padding: 1rem;
        vertical-align: middle;
    }

    .summary-table .table tr:hover .field-label {
        background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
        transition: all 0.3s ease;
    }

    .summary-table .table tr:hover .field-value {
        background-color: #f9fafb;
        transition: all 0.3s ease;
    }

    .summary-table .table tr {
        border-bottom: 1px solid #e5e7eb;
    }

    .summary-table .table tr:last-child {
        border-bottom: none;
    }

    .summary-table .table tr:nth-child(even) .field-label {
        background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
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
                    <h1 class="h2 fw-bold text-dark mb-0">Special Memo Details: {{ $specialMemo->document_number }}</h1>
                    <p class="text-muted mb-0">Review and manage special memo details</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="{{ route('special-memo.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                        <i class="bx bx-arrow-back"></i>
                        <span>Back to List</span>
                    </a>
                    @if(can_edit_memo($specialMemo))
                        <a href="{{ route('special-memo.edit', $specialMemo) }}" class="btn btn-warning d-flex align-items-center gap-2">
                            <i class="bx bx-edit"></i>
                            <span>Edit Memo</span>
                        </a>
                    @endif
                    
                 
                        <a href="{{ route('special-memo.status', $specialMemo) }}" class="btn btn-info d-flex align-items-center gap-2">
                            <i class="bx bx-info-circle"></i>
                            <span>Approval Status</span>
                        </a>
                
                    @if(can_print_memo($specialMemo))
                        <a href="{{ route('special-memo.print', $specialMemo) }}" target="_blank" class="btn btn-primary d-flex align-items-center gap-2">
                            <i class="bx bx-printer"></i>
                            <span>Print PDF</span>
                        </a>
                    @endif
                  
                    @php
                        // Check if ARF already exists for this special memo
                        $existingArfTop = \App\Models\RequestARF::where('source_id', $specialMemo->id)
                            ->where('model_type', 'App\\Models\\SpecialMemo')
                            ->first();
                    @endphp
                    
                    @if($existingArfTop)
                        {{-- Show View ARF button if ARF exists --}}
                        <a href="{{ route('request-arf.show', $existingArfTop) }}" class="btn btn-outline-success d-flex align-items-center gap-2">
                            <i class="bx bx-show"></i>
                            <span>View Activity Request</span>
                        </a>
                    @elseif(can_request_arf($specialMemo))
                        {{-- Show Create ARF button if memo is approved and no ARF exists --}}
                        <button type="button" class="btn btn-success d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createArfModal">
                            <i class="bx bx-file-plus"></i>
                            <span>Create Activity Request</span>
                        </button>
                    @endif
                    
                    {{-- Service Request Button --}}
                    @php
                        // Check if Service Request already exists for this memo
                        $existingServiceRequest = \App\Models\ServiceRequest::where('source_id', $specialMemo->id)
                            ->where('model_type', 'App\\Models\\SpecialMemo')
                            ->first();
                    @endphp
                    
                    @if($existingServiceRequest)
                        {{-- Show View Service Request button if Service Request exists --}}
                        <a href="{{ route('service-requests.show', $existingServiceRequest) }}" class="btn btn-outline-info d-flex align-items-center gap-2">
                            <i class="fas fa-eye"></i>
                            <span>View Request</span>
                        </a>
                    @elseif(can_request_services($specialMemo))
                        {{-- Show Create Service Request button if memo is approved and no Service Request exists --}}
                        <a href="{{ route('service-requests.create') }}?source_type=special_memo&source_id={{ $specialMemo->id }}" 
                           class="btn btn-info d-flex align-items-center gap-2">
                            <i class="fas fa-tools"></i>
                            <span>Request Services</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid py-4">
        @php
            // Decode JSON fields if they are strings
            $budget = is_string($specialMemo->budget_breakdown) 
                ? json_decode(stripslashes($specialMemo->budget_breakdown), true) 
                : $specialMemo->budget_breakdown;
            
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
            
            // Process internal participants to load staff details
            $processedInternalParticipants = [];
            if (!empty($internalParticipants)) {
                // Check if participants are already processed (have staff objects) or raw JSON
                if (isset($internalParticipants[0]) && isset($internalParticipants[0]['staff'])) {
                    // Participants are already processed - use them directly
                    $processedInternalParticipants = $internalParticipants;
                } else {
                    // Participants are raw JSON - need to process them
                    // Get staff IDs from the keys of the internal participants array
                    $staffIds = array_map('intval', array_keys($internalParticipants));
                    
                    // Fetch staff details using the converted integer IDs
                    $staffDetails = \App\Models\Staff::whereIn('staff_id', $staffIds)->get()->keyBy('staff_id');
                    
                    foreach ($internalParticipants as $staffId => $participantData) {
                        // Convert string key to integer for database lookup
                        $staffIdInt = (int) $staffId;
                        
                        // Check if staff exists in our fetched data
                        if (isset($staffDetails[$staffIdInt])) {
                            $processedInternalParticipants[] = [
                                'staff' => $staffDetails[$staffIdInt],
                                'participant_start' => $participantData['participant_start'] ?? null,
                                'participant_end' => $participantData['participant_end'] ?? null,
                                'participant_days' => $participantData['participant_days'] ?? null,
                            ];
                        }
                    }
                }
            }
            
            // Debug attachments
            // dd('Attachments debug:', $specialMemo->attachment, $attachments);

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
            
            // If no grand_total found, calculate from items with proper days logic
            if ($totalBudget == 0 && !empty($budgetByFundCode)) {
                foreach ($budgetByFundCode as $fundCodeId => $items) {
                    foreach ($items as $item) {
                        if (isset($item['unit_cost']) && isset($item['units'])) {
                            $unitCost = floatval($item['unit_cost']);
                            $units = floatval($item['units']);
                            $days = floatval($item['days'] ?? 1);
                            
                            // Use days when greater than 1, otherwise just unit_cost * units
                            if ($days > 1) {
                                $totalBudget += $unitCost * $units * $days;
                            } else {
                                $totalBudget += $unitCost * $units;
                            }
                        }
                    }
                }
            }
            
            // Debug logging
            if (config('app.debug')) {
                \Log::info('Budget parsing debug:', [
                    'raw_budget' => $specialMemo->budget_breakdown,
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
                    <i class="bx bx-file-text me-2 text-primary"></i>{{ $specialMemo->activity_title ?? 'Special Memo Summary' }}
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <tbody>
                        <!-- Basic Information -->
                        <tr>
                            <td class="field-label">
                                <i class="bx bx-check-circle me-2 text-success"></i>Status
                            </td>
                            <td class="field-value" colspan="3">
                               {!!display_memo_status_auto($specialMemo,'special')!!} 
                                @if($specialMemo->overall_status === 'pending')
                                    <a href="{{ route('special-memo.status', $specialMemo) }}" class="btn btn-sm btn-outline-info ms-2">
                                        <i class="bx bx-info-circle me-1"></i>View Status
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @if($specialMemo->document_number)
                        <tr>
                            <td class="field-label">
                                <i class="bx bx-hash me-2 text-info"></i>Document Number
                            </td>
                            <td class="field-value" colspan="3">
                                <span class="text-success fw-bold">{{ $specialMemo->document_number }}</span>
                            </td>
                        </tr>
                        @endif
                        @if($specialMemo->workplan_activity_code && $specialMemo->fund_type_id == 1)
                        <tr>
                            <td class="field-label">
                                <i class="bx bx-code-block me-2 text-warning"></i>AMERT Code
                            </td>
                            <td class="field-value" colspan="3">
                                <span class="text-warning fw-bold">{{ $specialMemo->workplan_activity_code }}</span>
                            </td>
                        </tr>
                        @endif
                        <tr>
                            <td class="field-label">
                                <i class="bx bx-user me-2 text-primary"></i>Creator
                            </td>
                            <td class="field-value">
                                {{ optional($specialMemo->staff)->fname }}
                                {{ optional($specialMemo->staff)->lname ?? 'Not assigned' }}
                            </td>
                            <td class="field-label">
                                <i class="bx bx-building me-2 text-secondary"></i>Division
                            </td>
                            <td class="field-value">
                                {{ optional($specialMemo->division)->division_name ?? 'Not assigned' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="field-label">
                                <i class="bx bx-user-check me-2 text-success"></i>Responsible Person
                            </td>
                            <td class="field-value">
                                {{ optional($specialMemo->responsiblePerson)->fname }}
                                {{ optional($specialMemo->responsiblePerson)->lname ?? 'Not assigned' }}
                            </td>
                            <td class="field-label">
                                <i class="bx bx-briefcase me-2 text-info"></i>Job Title
                            </td>
                            <td class="field-value">
                                {{ optional($specialMemo->responsiblePerson)->job_name ?? 'Not specified' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="field-label">
                                <i class="bx bx-calendar me-2 text-danger"></i>Date Range
                            </td>
                            <td class="field-value">
                                {{ $specialMemo->formatted_dates ?? 'Not specified' }}
                            </td>
                            <td class="field-label">
                                <i class="bx bx-category me-2 text-purple"></i>Request Type
                            </td>
                            <td class="field-value">
                                {{ optional($specialMemo->requestType)->name ?? 'Not specified' }}
                            </td>
                        </tr>
                        
                        <!-- Location Information -->
                        <tr>
                            <td class="field-label">
                                <i class="bx bx-map me-2 text-success"></i>Location(s)
                            </td>
                            <td class="field-value" colspan="3">
                                @if($specialMemo->locations)
                                    <span class="badge bg-success me-1">{{ $specialMemo->locations }}</span>
                                @else
                                    <span class="text-muted">No locations specified</span>
                                @endif
                            </td>
                        </tr>
                        
                        <!-- Budget Information -->
                        <tr>
                            <td class="field-label">
                                <i class="bx bx-dollar me-2 text-success"></i>Total Budget
                            </td>
                            <td class="field-value fw-bold text-success">${{ number_format($totalBudget, 2) }}</td>
                            <td class="field-label">
                                <i class="bx bx-credit-card me-2 text-warning"></i>Fund Type
                            </td>
                            <td class="field-value">
                                 @if($specialMemo->fundType)
                                    <span class="badge bg-success">
                                        {{ $specialMemo->fundType->name }}
                                    </span>
                                @else
                                    <span class="text-muted">Not specified</span>
                                @endif
                            </td>
                        </tr>
                        
                        <!-- Attachments -->
                        <tr>
                            <td class="field-label">
                                <i class="bx bx-paperclip me-2 text-info"></i>Attachments
                            </td>
                            <td class="field-value" colspan="3">
                                @if(!empty($attachments) && count($attachments) > 0)
                                    <span class="badge bg-info">{{ count($attachments) }} file(s) attached</span>
                                @else
                                    <span class="text-muted">No attachments</span>
                                @endif
                            </td>
                        </tr>
                        
                        <!-- Last Updated -->
                        <tr>
                            <td class="field-label">
                                <i class="bx bx-plus-circle me-2 text-primary"></i>Created Date
                            </td>
                            <td class="field-value">
                                {{ $specialMemo->created_at ? $specialMemo->created_at->format('M d, Y H:i') : 'Not available' }}
                            </td>
                            <td class="field-label">
                                <i class="bx bx-edit me-2 text-secondary"></i>Last Updated
                            </td>
                            <td class="field-value">
                                {{ $specialMemo->updated_at ? $specialMemo->updated_at->format('M d, Y H:i') : 'Not available' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>


        <!-- Background Card -->
        @if($specialMemo->background)
            <div class="card content-section border-0 mb-4">
                    <div class="card-header bg-transparent border-0 py-3">
                    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                        <i class="bx bx-info-circle"></i>
                        Background
                        </h6>
                    </div>
                    <div class="card-body">
                    <div class="html-content">{!! $specialMemo->background !!}</div>
                        </div>
                        </div>
        @endif

        <div class="row">
         

                <!-- Participants & Location -->
            <div class="card content-section bg-green border-0 mb-4 w-100">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h6 class="mb-0 fw-bold text-success d-flex align-items-center gap-2">
                            <i class="bx bx-group"></i>
                        Participants
                        </h6>
                            </div>
                            
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label text-muted small fw-semibold">Internal Participants</label>
                                <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-info">{{ count($processedInternalParticipants) }}</span>
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
                        
                            <div class="mt-4">
                                <label class="form-label text-muted small fw-semibold">Internal Participants Details</label>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                            <th>#</th>
                                                <th>Staff</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Days</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        @if(!empty($processedInternalParticipants))
                                            @foreach($processedInternalParticipants as $index => $participant)
                                                <tr>
                                                    <td>{{ $index + 1 }}</td>
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
                                        @else
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-3">
                                                    <i class="bx bx-user-x bx-lg mb-2"></i>
                                                    <div>No internal participants</div>
                                                </td>
                                            </tr>
                                        @endif
                                        </tbody>
                                    </table>
                    </div>
                </div>

                    <!-- Participants Summary Table -->
                    <div class="mt-4">
                        <label class="form-label text-muted small fw-semibold">Participants Summary</label>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Participant Type</th>
                                        <th>Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Internal Participants</td>
                                        <td class="text-end fw-bold">{{ count($processedInternalParticipants) }}</td>
                                    </tr>
                                    <tr>
                                        <td>External Participants</td>
                                        <td class="text-end fw-bold">{{ $specialMemo->total_external_participants ?? 0 }}</td>
                                    </tr>
                                    <tr class="table-success">
                                        <td class="fw-bold">Total Participants</td>
                                        <td class="text-end fw-bold">{{ count($processedInternalParticipants) + ($specialMemo->total_external_participants ?? 0) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        </div>
                    </div>
            </div>

                <!-- Budget Information -->
            <div class="card content-section bg-blue border-0 mb-4 w-100">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h6 class="mb-0 fw-bold text-primary d-flex align-items-center gap-2">
                            <i class="bx bx-money"></i>
                            Budget Information
                        </h6>
                    </div>
                    <div class="card-body">
                    @if (!empty($budget))
                        @if (!empty($budgetByFundCode))
                            @php
                                $count = 1;
                                $grandTotal = 0;
                            @endphp

                            @foreach ($budgetByFundCode as $fundCodeId => $items)
                                    @php
                                        $fundCode = $fundCodes[$fundCodeId] ?? null;
                                    $groupTotal = 0;
                                    $itemCount = 1; // Reset counter for each budget code
                                    @endphp
                                    
                                {{-- Budget Code Title --}}
                                <h6 style="color: #2c3d50; font-weight: 600; margin-top: 20px;">
                                    @if ($fundCode)
                                        {{ $fundCode->activity }} - {{ $fundCode->code }} -
                                        ({{ $fundCode->fundType->name ?? 'N/A' }})
                                                    @else
                                        Budget Code: {{ $fundCodeId }}
                                                    @endif
                                                </h6>

                                {{-- Individual Table for this Budget Code --}}
                                <div class="table-responsive mb-4">
                                    <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                <th>#</th>
                                                        <th>Cost Item</th>
                                                <th class="text-end">Unit Cost</th>
                                                <th class="text-end">Units</th>
                                                <th class="text-end">Days</th>
                                                <th class="text-end">Total</th>
                                                        <th>Description</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                            @foreach ($items as $item)
                                                @php
                                                    $unitCost = floatval($item['unit_cost'] ?? 0);
                                                    $units = floatval($item['units'] ?? 0);
                                                    $days = floatval($item['days'] ?? 1);

                                                    // Use days when greater than 1, otherwise just unit_cost * units
                                                    if ($days > 1) {
                                                        $total = $unitCost * $units * $days;
                                                    } else {
                                                        $total = $unitCost * $units;
                                                    }

                                                    $groupTotal += $total;
                                                    $grandTotal += $total;
                                                        @endphp
                                                        <tr>
                                                    <td>{{ $itemCount }}</td>
                                                            <td>{{ $item['cost'] ?? 'N/A' }}</td>
                                                    <td class="text-end">{{ number_format($unitCost, 2) }}</td>
                                                    <td class="text-end">{{ $units }}</td>
                                                    <td class="text-end">{{ $days }}</td>
                                                    <td class="text-end">{{ number_format($total, 2) }}</td>
                                                    <td>{{ $item['description'] ?? '' }}</td>
                                                        </tr>
                                                @php
                                                    $itemCount++;
                                                @endphp
                                                    @endforeach
                                                </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="5" class="text-end">Sub Total</th>
                                                <th class="text-end">{{ number_format($groupTotal, 2) }}</th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                            </table>
                                    </div>
                                @endforeach
                                
                            {{-- Overall Grand Total --}}
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="alert alert-success">
                                        <h6 class="mb-0"><strong>Grand Total: {{ number_format($grandTotal, 2) }}
                                                USD</strong></h6>
                                    </div>
                                </div>
                            </div>
                            
                            {{-- Available Budget --}}
                            @if($specialMemo->available_budget)
                            <div class="row mt-2">
                                <div class="col-md-12">
                                    <div class="alert alert-info">
                                        <h6 class="mb-0"><strong>Available Budget: {{ number_format($specialMemo->available_budget, 2) }}
                                                USD</strong></h6>
                                        <small class="text-muted">Allocated by Finance Officer</small>
                                    </div>
                                </div>
                            </div>
                            @endif
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
                                        @foreach ($budget as $key => $value)
                                            @if ($key !== 'grand_total')
                                                    <tr>
                                                        <td>{{ $key }}</td>
                                                        <td>
                                                        @if (is_array($value))
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

            <div class="container-fluid py-4"> <!-- Reopen container-fluid -->
                <!-- Request for Approval Card -->
                @if($specialMemo->activity_request_remarks)
                    <div class="card content-section bg-purple border-0 mb-4">
                        <div class="card-header bg-transparent border-0 py-3">
                            <h6 class="mb-0 fw-bold text-success d-flex align-items-center gap-2">
                                <i class="bx bx-file-text"></i>
                                Request For Approval
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="html-content">{!! $specialMemo->activity_request_remarks !!}</div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-12">

                <!-- Attachments Card -->
                @if (!empty($attachments) && count($attachments) > 0)
                <div class="card sidebar-card border-0 mb-4">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h6 class="mb-0 fw-bold text-success d-flex align-items-center gap-2">
                            <i class="bx bx-paperclip"></i>
                            Attachments
                        </h6>
                    </div>
                    <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Type</th>
                                            <th>File Name</th>
                                            <th>Size</th>
                                            <th>Uploaded</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($attachments as $index => $attachment)
                                        @php
                                            $originalName =
                                                $attachment['original_name'] ??
                                                ($attachment['filename'] ?? ($attachment['name'] ?? 'Unknown'));
                                            $filePath = $attachment['path'] ?? ($attachment['file_path'] ?? '');
                                            $ext = $filePath
                                                ? strtolower(pathinfo($originalName, PATHINFO_EXTENSION))
                                                : '';
                                            $fileUrl = $filePath ? url('storage/' . $filePath) : '#';
                                            $isOffice = in_array($ext, [
                                                'ppt',
                                                'pptx',
                                                'xls',
                                                'xlsx',
                                                'doc',
                                                'docx',
                                            ]);
                                            @endphp
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $attachment['type'] ?? 'Document' }}</td>
                                                <td>{{ $originalName }}</td>
                                            <td>{{ isset($attachment['size']) ? round($attachment['size'] / 1024, 2) . ' KB' : 'N/A' }}
                                            </td>
                                            <td>{{ isset($attachment['uploaded_at']) ? \Carbon\Carbon::parse($attachment['uploaded_at'])->format('Y-m-d H:i') : 'N/A' }}
                                            </td>
                                            <td>
                                                @if ($filePath)
                                                    <button type="button"
                                                        class="btn btn-sm btn-success preview-attachment"
                                                        data-file-url="{{ $fileUrl }}"
                                                        data-file-ext="{{ $ext }}"
                                                        data-file-office="{{ $isOffice ? '1' : '0' }}">
                                                        <i class="bx bx-show"></i> Preview
                                                    </button>
                                                    <a href="{{ $fileUrl }}" target="_blank"
                                                        class="btn btn-sm btn-success">
                                                        <i class="bx bx-download"></i> Download
                                                    </a>
                                                    @else
                                                    <span class="text-muted">File not found</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                    </div>
                            </div>
                        @endif


                

            
                    </div>
               <div class="col-lg-12">
                <!-- Enhanced Memo Information Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">
                            <i class="bx bx-info-circle me-2 text-success"></i>Approval Information
                        </h6>
                </div>
                    <div class="card-body">

                        @if($specialMemo->overall_status !== 'approved')
                            <div class="mt-3 p-3"
                                style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-radius: 0.5rem; border: 1px solid #bbf7d0;">
                                
                                <!-- Compact Approval Info Row -->
                                <div class="row g-3">
                                    <!-- Status -->
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="p-2" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                                                <i class="bx bx-badge-check text-white"></i>
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-dark small">Status</div>
                                                <div class="fw-bold text-purple">{{ ucfirst($specialMemo->overall_status ?? 'draft') }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Current Approver -->
                                    @if($specialMemo->overall_status !== 'draft' && $specialMemo->current_actor)
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="p-2" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                                                <i class="bx bx-user text-white"></i>
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-dark small">Current Approver</div>
                                                <div class="fw-bold text-info">{{ $specialMemo->current_actor->fname . ' ' . $specialMemo->current_actor->lname }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    <!-- Approval Role -->
                                    @if($specialMemo->workflow_definition)
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="p-2" style="background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);">
                                                <i class="bx bx-crown text-white"></i>
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-dark small">Approval Role</div>
                                                <div class="fw-bold text-orange">{{ $specialMemo->workflow_definition->role ?? 'Not specified' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                </div>

                                <!-- Additional Info (if needed) -->
                                @if($specialMemo->overall_status === 'pending')
                                <div class="mt-3 p-2 bg-info bg-opacity-10 rounded">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bx bx-info-circle text-info"></i>
                                        <span class="text-info fw-medium small">This special memo is currently awaiting approval from the supervisor above.</span>
                                    </div>
                                </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Enhanced Approval Actions -->
                @if(can_take_action_generic($specialMemo) || (is_with_creator_generic($specialMemo) && $specialMemo->overall_status != 'draft') || (isdivision_head($specialMemo) && $specialMemo->overall_status == 'returned'))
                    <div class="card border-0 shadow-lg mt-4" style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
                        <div class="card-header bg-transparent border-0 py-4" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 12px 12px 0 0;">
                            <h6 class="mb-0 fw-bold text-gray-800 d-flex align-items-center gap-2" style="color: #1f2937;">
                                <i class="bx bx-check-circle" style="color: #059669;"></i>
                                Approval Actions - Level {{ $specialMemo->approval_level ?? 0 }}
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-3">
                                <i class="bx bx-info-circle me-2"></i>
                                <strong>Current Level:</strong> {{ $specialMemo->approval_level ?? 0 }}
                                @if ($specialMemo->workflow_definition)
                                    - <strong>Role:</strong>
                                    {{ $specialMemo->workflow_definition->role ?? 'Not specified' }}
                                @endif
                            </div>
                            
                            <form action="{{ route('special-memo.update-status', $specialMemo) }}" method="POST" id="approvalForm">
                                @csrf
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="remarks" class="form-label fw-semibold">
                                                <i class="bx bx-message-square-detail me-1"></i>Comments
                                            </label>
                                            <textarea class="form-control" id="remarks" name="remarks" rows="3"
                                                placeholder="Add your comments here..."></textarea>
                                        </div>
                                        @if($specialMemo->approval_level=='5')
                                        <div class="mb-3">
                                            <label for="available_budget" class="form-label">Available Budget <span class="text-danger">*</span></label>
                                            <input type="number" name="available_budget" class="form-control" placeholder="Available Budget" required>
                                        </div>
                                        @endif
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-grid gap-2 mt-4">
                                            @php
                                                $isHOD = isdivision_head($specialMemo);
                                                $isReturnedToHOD = $isHOD && $specialMemo->overall_status == 'returned' && $specialMemo->approval_level == 1;
                                                $isPendingAtHOD = $isHOD && $specialMemo->overall_status == 'pending' && $specialMemo->approval_level == 1;
                                            @endphp
                                            
                                            {{-- Show Approve button only if not returned to HOD --}}
                                            @if(!$isReturnedToHOD)
                                                <button type="submit" name="action" value="approved" class="btn btn-success w-100 d-flex align-items-center justify-content-center gap-1">
                                                    <i class="bx bx-check"></i>
                                                    Approve
                                                </button>
                                            @endif
                                            
                                            {{-- Always show Return button --}}
                                            <button type="submit" name="action" value="returned" class="btn btn-warning w-100 d-flex align-items-center justify-content-center gap-1">
                                                <i class="bx bx-undo"></i>
                                                Return
                                            </button>
                                            
                                            {{-- Show Cancel button only for HOD at level 1 --}}
                                            @if($isHOD && $specialMemo->approval_level == 1)
                                                <button type="submit" name="action" value="cancelled" class="btn btn-danger w-100 d-flex align-items-center justify-content-center gap-2">
                                                    <i class="bx bx-x"></i>
                                                    Cancel
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                <!-- Submit for Approval Section -->
                @if($specialMemo->overall_status === 'draft' && $specialMemo->staff_id == user_session('staff_id') || $specialMemo->overall_status == 'draft' && $specialMemo->division->division_head == user_session('staff_id'))
                    <div class="card sidebar-card border-0 mt-4"
                        style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                        <div class="card-header bg-transparent border-0 py-3">
                            <h6 class="mb-0 fw-bold text-success d-flex align-items-center gap-2">
                                <i class="bx bx-send"></i>
                                Submit for Approval
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Ready to submit this special memo for approval?</p>
                            <form action="{{ route('special-memo.submit-for-approval', $specialMemo) }}" method="POST">
                                @csrf
                                <button type="submit"
                                    class="btn btn-success w-100 d-flex align-items-center justify-content-center gap-2">
                                    <i class="bx bx-send"></i>
                                    Submit for Approval
                                </button>
                            </form>
                            <div class="mt-3 p-3 bg-light rounded">
                                <small class="text-muted">
                                    <i class="bx bx-info-circle me-1"></i>
                                    <strong>Note:</strong> Once submitted, you won't be able to edit this memo until
                                    it's returned for revision.
                                </small>
                            </div>
                        </div>
                    </div>
                @endif

            </div> 
            <!-- End container-fluid -->

            <div class="col-lg-12">
                <!-- Resubmission Section for HODs when returned -->
                @if(($specialMemo->overall_status === 'returned' || $specialMemo->overall_status === 'pending') && isdivision_head($specialMemo) && $specialMemo->approval_level <= 1)
                    <div class="card sidebar-card border-0 mb-4"
                        style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                        <div class="card-header bg-transparent border-0 py-3">
                            <h6 class="mb-0 fw-bold text-success d-flex align-items-center gap-2">
                                <i class="bx bx-undo"></i>
                                Resubmit for Approval
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">This memo was returned for revision. Ready to resubmit?</p>
                            <button type="button" class="btn btn-success w-100 d-flex align-items-center justify-content-center gap-2" 
                                    data-bs-toggle="modal" data-bs-target="#resubmitModal">
                                <i class="bx bx-undo"></i>
                                Resubmit for Approval
                            </button>
                            <div class="mt-3 p-3 bg-light rounded">
                                <small class="text-muted">
                                    <i class="bx bx-info-circle me-1"></i>
                                    <strong>Note:</strong> This will resubmit the memo to the approver who returned it.
                                </small>
                            </div>
                        </div>
                    </div>
                @endif

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
            </div>
        </div>
    </div>
</div>

{{-- Modal for preview --}}
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="previewModalLabel">Attachment Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="previewModalBody" style="min-height:60vh;display:flex;align-items:center;justify-content:center;">
        <div class="text-center w-100">Loading preview...</div>
      </div>
    </div>
  </div>
</div>

{{-- Resubmit Modal --}}
<div class="modal fade" id="resubmitModal" tabindex="-1" aria-labelledby="resubmitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resubmitModalLabel">Resubmit for Approval</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('special-memo.resubmit', $specialMemo) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                        <strong>Note:</strong> This will resubmit the memo to the approver who returned it for revision.
                    </div>
                    <div class="mb-3">
                        <label for="resubmitComment" class="form-label">Comments (Optional)</label>
                        <textarea class="form-control" id="resubmitComment" name="comment" rows="3" 
                                  placeholder="Add any comments about the changes made..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bx bx-undo me-1"></i>Resubmit for Approval
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


        @if($specialMemo->fund_type_id == 2 && $specialMemo->overall_status == 'approved')
            @php
                // Check if ARF already exists for this special memo
                $existingArf = \App\Models\RequestARF::where('source_id', $specialMemo->id)
                    ->where('model_type', 'App\\Models\\SpecialMemo')
                    ->first();
            @endphp
            
            @if(!$existingArf)
            @include('request-arf.components.create-arf-modal', [
            'sourceType' => 'Special Memo',
            'sourceTitle' => $specialMemo->activity_title,
            'fundTypeId' => $specialMemo->fundType ? $specialMemo->fundType->id : null,
            'fundTypeName' => $specialMemo->fundType ? $specialMemo->fundType->name : 'N/A',
            'divisionName' => $specialMemo->division ? $specialMemo->division->division_name : 'N/A',
            'dateFrom' => $specialMemo->date_from ? \Carbon\Carbon::parse($specialMemo->date_from)->format('M d, Y') : 'N/A',
            'dateTo' => $specialMemo->date_to ? \Carbon\Carbon::parse($specialMemo->date_to)->format('M d, Y') : 'N/A',
            'numberOfDays' => $specialMemo->date_from && $specialMemo->date_to ? 
                \Carbon\Carbon::parse($specialMemo->date_from)->diffInDays(\Carbon\Carbon::parse($specialMemo->date_to)) + 1 : 'N/A',
            'location' => $specialMemo->locations() ? $specialMemo->locations()->pluck('name')->join(', ') : 'N/A',
            'keyResultArea' => 'N/A', // Special memos don't have key result areas
            'quarterlyLinkage' => $specialMemo->quarterly_linkage ?? 'N/A',
            'totalParticipants' => $specialMemo->total_participants ?? 'N/A',
            'internalParticipants' => $specialMemo->internal_participants 
                ? (is_string($specialMemo->internal_participants) 
                    ? count(json_decode($specialMemo->internal_participants, true) ?? []) 
                    : (is_array($specialMemo->internal_participants) 
                        ? count($specialMemo->internal_participants) 
                        : 0))
                : 0,
            'externalParticipants' => $specialMemo->total_external_participants ?? 0,
            'budgetCode' => $specialMemo->fundCodes ? $specialMemo->fundCodes->pluck('code')->join(', ') : 'N/A',
            'background' => $specialMemo->background ?? 'N/A',
            'requestForApproval' => $specialMemo->activity_request_remarks ?? 'N/A',
            'totalBudget' => $specialMemo->total_budget ?? '0.00',
            'headOfDivision' => $specialMemo->division && $specialMemo->division->head ? 
                $specialMemo->division->head->fname . ' ' . $specialMemo->division->head->lname : 'N/A',
            'focalPerson' => $specialMemo->staff ? 
                $specialMemo->staff->fname . ' ' . $specialMemo->staff->lname : 'N/A',
            'budgetBreakdown' => is_string($specialMemo->budget_breakdown) && !empty($specialMemo->budget_breakdown)
                ? (json_decode($specialMemo->budget_breakdown, true) ?? [])
                : (is_array($specialMemo->budget_breakdown) ? $specialMemo->budget_breakdown : []),
            'budgetIds' => is_string($specialMemo->budget_id) && !empty($specialMemo->budget_id)
                ? (json_decode($specialMemo->budget_id, true) ?? [])
                : (is_array($specialMemo->budget_id) ? $specialMemo->budget_id : []),
            'fundCodes' => (function() use ($specialMemo) {
                $ids = is_string($specialMemo->budget_id) && !empty($specialMemo->budget_id)
                    ? (json_decode($specialMemo->budget_id, true) ?? [])
                    : (is_array($specialMemo->budget_id) ? $specialMemo->budget_id : []);
                if (!is_array($ids)) $ids = [];
                return \App\Models\FundCode::whereIn('id', $ids)->with('fundType')->get()->keyBy('id');
            })(),
            'defaultTitle' => to_sentence_case('Activity Request - ' . $specialMemo->title),
            'sourceId' => $specialMemo->id,
            'modelType' => 'App\\Models\\SpecialMemo'
        ])

        
        @elseif(in_array($existingArf->overall_status, ['pending', 'approved', 'returned']))
            <div class="alert alert-info">
                <i class="bx bx-info-circle me-2"></i>
                An ARF request has already been created for this special memo.
                <a href="{{ route('request-arf.show', $existingArf) }}" class="btn btn-sm btn-outline-primary ms-2">
                    <i class="bx bx-show me-1"></i>View Activity Request
                </a>
            </div>
            @endif
            
        @endif
        
@endsection

@push('scripts')
<script>
    // Attachment preview functionality
    $(document).on('click', '.preview-attachment', function() {
        var fileUrl = $(this).data('file-url');
        var ext = $(this).data('file-ext');
        var isOffice = $(this).data('file-office') == '1';
        var modalBody = $('#previewModalBody');
        var content = '';
        
        if(['jpg','jpeg','png'].includes(ext)) {
            content = '<img src="'+fileUrl+'" class="img-fluid" style="max-height:70vh;max-width:100%;margin:auto;display:block;">';
        } else if(ext === 'pdf') {
            content = '<iframe src="'+fileUrl+'#toolbar=1&navpanes=0&scrollbar=1" style="width:100%;height:70vh;border:none;"></iframe>';
        } else if(isOffice) {
            var gdocs = 'https://docs.google.com/viewer?url='+encodeURIComponent(fileUrl)+'&embedded=true';
            content = '<iframe src="'+gdocs+'" style="width:100%;height:70vh;border:none;"></iframe>';
        } else {
            content = '<div class="alert alert-info">Preview not available. <a href="'+fileUrl+'" target="_blank">Download/Open file</a></div>';
        }
        
        modalBody.html(content);
        var modal = new bootstrap.Modal(document.getElementById('previewModal'));
        modal.show();
    });

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