@extends('layouts.app')

@section('title', 'View Activity')

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
                    <h1 class="h2 fw-bold text-dark mb-0">View Activity</h1>
                    <p class="text-muted mb-0">Review and manage activity details</p>
</div>
                <div class="d-flex gap-3">
                    <a href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                        <i class="bx bx-arrow-back"></i>
                        <span>Back to Matrix</span>
                    </a>
                    
                    @if (can_edit_memo($activity))
                        <a href="{{ route('matrices.activities.edit', [$matrix, $activity]) }}" class="btn btn-warning d-flex align-items-center gap-2">
                            <i class="bx bx-edit"></i>
                            <span>Edit Activity</span>
                        </a>
                    @endif
                    
                    @if(can_request_arf($activity))
                        @php
                            // Check if ARF already exists for this activity
                            $existingArf = \App\Models\RequestARF::where('source_id', $activity->id)
                                ->where('model_type', 'App\\Models\\Activity')
                                ->first();
                        @endphp
                        
                        @if(!$existingArf)
                            <button type="button" class="btn btn-success d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createArfModal">
                                <i class="bx bx-file-plus"></i>
                                <span>Create ARF Request</span>
                            </button>
                        @else
                            <a href="{{ route('request-arf.show', $existingArf) }}" class="btn btn-outline-success d-flex align-items-center gap-2">
                                <i class="bx bx-show"></i>
                                <span>View ARF Request</span>
                            </a>
                        @endif
                    @endif
                                
                         
                    {{-- Service Request Button --}}
                    @if(can_request_services($activity))
                        @php
                            // Check if Service Request already exists for this activity
                            $existingServiceRequest = \App\Models\ServiceRequest::where('source_id', $activity->id)
                                ->where('model_type', 'App\\Models\\Activity')
                                ->first();
                        @endphp
                        
                        @if(!$existingServiceRequest)
                            <a href="{{ route('service-requests.create') }}?source_type=activity&source_id={{ $activity->id }}" 
                               class="btn btn-info d-flex align-items-center gap-2">
                                <i class="fas fa-tools"></i>
                                <span>Create Service Request</span>
                            </a>
                        @else
                            <a href="{{ route('service-requests.show', $existingServiceRequest) }}" class="btn btn-outline-info d-flex align-items-center gap-2">
                                <i class="fas fa-eye"></i>
                                <span>View Service Request</span>
                            </a>
                        @endif
                    @endif
                            
                    @if(can_print_memo($activity))
                        <a href="{{ route('matrices.activities.memo-pdf', [$matrix, $activity]) }}" 
                           class="btn btn-primary d-flex align-items-center gap-2" target="_blank">
                            <i class="bx bx-printer"></i>
                            <span>Print Activity</span>
                        </a>
                    @endif
                            </div>
                        </div>
                    </div>
                </div>

    <div class="container-fluid py-4">

        @php
            // Decode JSON fields if they are strings
            $budget = is_string($activity->budget_breakdown) 
                ? json_decode(stripslashes($activity->budget_breakdown), true) 
                : $activity->budget_breakdown;
            
            // Handle double-encoded JSON (sometimes happens with form submissions)
            if (is_string($budget) && !is_array($budget)) {
                $budget = json_decode($budget, true);
            }

            $attachments = is_string($activity->attachment) 
                ? json_decode($activity->attachment, true) 
                : $activity->attachment;

            $internalParticipants = is_string($activity->internal_participants)
                ? json_decode($activity->internal_participants, true)
                : $activity->internal_participants;

            // Ensure variables are arrays
            $budget = is_array($budget) ? $budget : [];
            $attachments = is_array($attachments) ? $attachments : [];
            $internalParticipants = is_array($internalParticipants) ? $internalParticipants : [];
            
            // Process internal participants to load staff details
            $processedInternalParticipants = [];
            if (!empty($internalParticipants)) {
                $staffDetails = \App\Models\Staff::whereIn('staff_id', array_keys($internalParticipants))
                    ->get()
                    ->keyBy('staff_id');

                foreach ($internalParticipants as $staffId => $participantData) {
                    if (isset($staffDetails[$staffId])) {
                        $processedInternalParticipants[] = [
                            'staff' => $staffDetails[$staffId],
                            'participant_start' => $participantData['participant_start'] ?? null,
                            'participant_end' => $participantData['participant_end'] ?? null,
                            'participant_days' => $participantData['participant_days'] ?? null,
                        ];
                    }
                }
            }

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
                        @endphp

        <!-- Summary Table -->
        <div class="summary-table mb-4">
            <div class="card-header bg-light border-0 py-3">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bx bx-file-text me-2 text-primary"></i>{{ $activity->activity_title ?? 'Activity Summary' }}
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
                        <strong>Status: </strong>
                                
                        @php
                            // Determine the status and badge color
                            $statusText = '';
                            $badgeClass = 'bg-secondary';

                            if (can_approve_activity($activity)) {
                                if ($activity->status == 'passed') {
                                    $statusText = ucwords($activity->status);
                                    $badgeClass = 'bg-success';
                                } else {
                                    $statusText = ucwords($activity->status);
                                    $badgeClass = 'bg-danger';
                                }
                            }
                        @endphp

                        @if(can_approve_activity($activity))
                            <span class="badge {{ $badgeClass }}">{{ $statusText }}</span>
                        @else
                            <span class="badge bg-success">No Action Required</span>
                        @endif
                            </td>
                        </tr>
                @if($activity->document_number)
                        <tr>
                            <td class="field-label">
                                <i class="bx bx-hash me-2 text-info"></i>Document Number
                            </td>
                            <td class="field-value" colspan="3">
                                <span class="text-success fw-bold">{{ $activity->document_number }}</span>
                            </td>
                        </tr>
                @endif
                        @if($activity->workplan_activity_code && $activity->fund_type_id == 1)
                        <tr>
                            <td class="field-label">
                                <i class="bx bx-code-block me-2 text-warning"></i>World Bank Activity Code
                            </td>
                            <td class="field-value" colspan="3">
                                <span class="text-dark fw-bold">{{ ucwords($activity->workplan_activity_code) }}</span>
                            </td>
                        </tr>
                @endif
                        <tr>
                            <td class="field-label">
                                <i class="bx bx-user me-2 text-primary"></i>Creator
                            </td>
                            <td class="field-value">
                                {{ optional($activity->staff)->fname }}
                                {{ optional($activity->staff)->lname ?? 'Not assigned' }}
                            </td>
                            <td class="field-label">
                                <i class="bx bx-building me-2 text-secondary"></i>Division
                            </td>
                            <td class="field-value">
                                {{ optional($activity->matrix->division)->division_name ?? 'Not assigned' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="field-label">
                                <i class="bx bx-user-check me-2 text-success"></i>Responsible Person
                            </td>
                            <td class="field-value">
                                {{ optional($activity->focalPerson)->fname }}
                                {{ optional($activity->focalPerson)->lname ?? 'Not assigned' }}
                            </td>
                            <td class="field-label">
                                <i class="bx bx-briefcase me-2 text-info"></i>Job Title
                            </td>
                            <td class="field-value">
                                {{ optional($activity->focalPerson)->job_name ?? 'Not specified' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="field-label">
                                <i class="bx bx-calendar me-2 text-danger"></i>Date Range
                            </td>
                            <td class="field-value">
                                {{ $activity->date_from ? \Carbon\Carbon::parse($activity->date_from)->format('M d, Y') : 'N/A' }}
                                -
                                {{ $activity->date_to ? \Carbon\Carbon::parse($activity->date_to)->format('M d, Y') : 'N/A' }}
                            </td>
                            <td class="field-label">
                                <i class="bx bx-category me-2 text-purple"></i>Request Type
                            </td>
                            <td class="field-value">
                                {{ optional($activity->requestType)->name ?? 'Not specified' }}
                            </td>
                        </tr>
                        
                        <!-- Location Information -->
                        <tr>
                            <td class="field-label">
                                <i class="bx bx-map me-2 text-success"></i>Location(s)
                            </td>
                            <td class="field-value" colspan="3">
                                @if($activity->locations && $activity->locations->count() > 0)
                                    @foreach($activity->locations as $location)
                                        <span class="badge bg-success me-1">{{ $location->name }}</span>
                                    @endforeach
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
                                {{ optional($activity->fundType)->name ?? 'Not specified' }}
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
                                {{ $activity->created_at ? $activity->created_at->format('M d, Y H:i') : 'Not available' }}
                            </td>
                            <td class="field-label">
                                <i class="bx bx-edit me-2 text-secondary"></i>Last Updated
                            </td>
                            <td class="field-value">
                                {{ $activity->updated_at ? $activity->updated_at->format('M d, Y H:i') : 'Not available' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                    </div>
                </div>

        <!-- Key Result Area Card -->
        @if($activity->key_result_area && $matrix->key_result_area)
            <div class="card content-section border-0 mb-4">
                <div class="card-header bg-transparent border-0 py-3">
                    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                        <i class="bx bx-target-lock"></i>
                        Key Result Area
                    </h6>
                    </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            <p>{{ $matrix->key_result_area[intval($activity->key_result_area)]['description'] ?? '' }}</p>
                    </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Background Card -->
        @if($activity->background)
            <div class="card content-section border-0 mb-4">
                <div class="card-header bg-transparent border-0 py-3">
                    <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                        <i class="bx bx-info-circle"></i>
                        Background
                    </h6>
                </div>
                <div class="card-body">
                    <div class="html-content">{!! $activity->background !!}</div>
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
                <div class="card-body">
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
                                <span class="badge bg-secondary">{{ $activity->total_external_participants ?? 0 }}</span>
                                <span class="text-muted">external</span>
                            </div>
                        </div>
                </div>

                    @if (!empty($processedInternalParticipants))
                        <div class="mt-4">
                            <label class="form-label text-muted small fw-semibold">Internal Participants Details</label>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Staff</th>
                                    <th>Job Title</th>
                                            <th>Division</th>
                                    <th>Duty Station</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                    <th>Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                        @foreach ($processedInternalParticipants as $index => $participant)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>
                                                    @if (isset($participant['staff']) && $participant['staff'])
                                                        {{ $participant['staff']->fname ?? '' }} {{ $participant['staff']->lname ?? '' }}
                                                    @else
                                                        <span class="text-muted">Unknown Staff</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if (isset($participant['staff']) && $participant['staff'])
                                                        {{ $participant['staff']->job_name ?? '-' }}
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if (isset($participant['staff']) && $participant['staff'])
                                                        {{ $participant['staff']->division_name ?? '-' }}
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if (isset($participant['staff']) && $participant['staff'])
                                                        {{ $participant['staff']->duty_station_name ?? '-' }}
                                                    @else
                                                        <span class="text-muted">-</span>
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
                                        <td class="text-end fw-bold">{{ $activity->total_external_participants ?? 0 }}</td>
                                    </tr>
                                    <tr class="table-success">
                                        <td class="fw-bold">Total Participants</td>
                                        <td class="text-end fw-bold">{{ count($processedInternalParticipants) + ($activity->total_external_participants ?? 0) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

               

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
            @if($activity->activity_request_remarks)
                <div class="card content-section bg-purple border-0 mb-4">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h6 class="mb-0 fw-bold text-success d-flex align-items-center gap-2">
                            <i class="bx bx-file-text"></i>
                            Request For Approval
                        </h6>
                        </div>
                    <div class="card-body">
                        <div class="html-content">{!! $activity->activity_request_remarks !!}</div>
                            </div>
                            </div>
                                @endif
                            </div>

            @if((can_take_action($matrix ) && can_approve_activity($activity))  && !done_approving_activty($activity))
            <div class="col-md-4 mb-2 px-2 ms-auto">
              @include('activities.partials.approval-actions',['activity'=>$activity,'matrix'=>$matrix])
                        </div>
            @endif


    @include('matrices.partials.approval-trail',['trails'=>$activity->activityApprovalTrails])

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

@endsection

@push('styles')
<style>
/* Activity Summary Table Styles */
.table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(0, 0, 0, 0.02);
}

.table tbody tr:hover {
    background-color: rgba(17, 154, 72, 0.05);
}

.table tbody td {
    vertical-align: middle;
    padding: 1rem;
}

.table tbody td:first-child {
    background-color: #f8f9fa !important;
    border-right: 2px solid #119a48;
    font-weight: 600;
    color: #2c3e50;
    width: 30%;
}

.table tbody td:last-child {
    background-color: #ffffff;
}

/* Badge styling in table */
.table .badge {
    font-size: 0.8rem;
    padding: 0.4em 0.8em;
}

/* Background section styling */
.bg-light.rounded.border {
    border: 1px solid #dee2e6 !important;
    background-color: #f8f9fa !important;
}

/* Enhanced card styling */
.card-header.bg-light {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
    border-bottom: 2px solid #119a48;
}

.card-header h5 {
    color: #2c3e50;
    font-weight: 700;
}

/* Status badges enhancement */
.badge.fs-6 {
    font-size: 0.9rem !important;
    padding: 0.5em 1em;
    font-weight: 600;
}

/* Background content styling */
.bg-light.rounded {
    border: 1px solid #e9ecef;
    background-color: #f8f9fa !important;
}

/* Table enhancements for data tables */
.table-hover tbody tr:hover {
    background-color: rgba(17, 154, 72, 0.05);
}

.table thead th {
    border-bottom: 2px solid #119a48;
    font-weight: 600;
    color: #2c3e50;
}

/* Button group styling */
.btn-group .btn {
    border-radius: 0;
}

.btn-group .btn:first-child {
    border-top-left-radius: 0.375rem;
    border-bottom-left-radius: 0.375rem;
}

.btn-group .btn:last-child {
    border-top-right-radius: 0.375rem;
    border-bottom-right-radius: 0.375rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .info-item {
        margin-bottom: 1.5rem;
    }
    
    .info-label {
        font-size: 0.8rem;
    }
    
    .info-value {
        font-size: 0.9rem;
    }
    
    .table-responsive {
        font-size: 0.85rem;
    }
    
    .btn-group .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
}

/* Attachment Preview Modal Styles */
#attachmentPreviewModal .modal-dialog {
    max-width: 90vw;
    margin: 1.75rem auto;
}

#attachmentPreviewModal .modal-body {
    min-height: 500px;
    max-height: 80vh;
    overflow: hidden;
}

#previewContainer {
    min-height: 500px;
    max-height: 80vh;
    overflow: auto;
}

/* Image preview styles */
#previewContainer img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* PDF iframe styles */
#previewContainer iframe {
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Text preview styles */
#previewContainer pre {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    font-family: 'Courier New', monospace;
    line-height: 1.5;
}

/* HTML content styling */
.html-content {
    line-height: 1.6;
    word-wrap: break-word;
}

.html-content p {
    margin-bottom: 1rem;
}

.html-content ul, .html-content ol {
    margin-bottom: 1rem;
    padding-left: 1.5rem;
}

.html-content table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1rem;
}

.html-content table th,
.html-content table td {
    border: 1px solid #dee2e6;
    padding: 8px;
    text-align: left;
}

.html-content table th {
    background-color: #f8f9fa;
    font-weight: bold;
}

.html-content strong {
    font-weight: bold;
}

.html-content em {
    font-style: italic;
}

.html-content u {
    text-decoration: underline;
}

/* Loading animation */
.spinner-border {
    width: 3rem;
    height: 3rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    #attachmentPreviewModal .modal-dialog {
        max-width: 95vw;
        margin: 0.5rem auto;
    }
    
    #attachmentPreviewModal .modal-body {
        min-height: 400px;
        max-height: 70vh;
    }
    
    #previewContainer {
        min-height: 400px;
        max-height: 70vh;
    }
}
</style>
@endpush

@push('scripts')
<script>
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
</script>

        @if($activity->fundType && strtolower($activity->fundType->name) === 'extramural' && $matrix->overall_status === 'approved')
            @php
                // Check if ARF already exists for this activity
                $existingArf = \App\Models\RequestARF::where('source_id', $activity->id)
                    ->where('model_type', 'App\\Models\\Activity')
                    ->first();
            @endphp
            
            @if(!$existingArf)
            @include('request-arf.components.create-arf-modal', [
            'sourceType' => 'Activity',
            'sourceTitle' => $activity->activity_title,
            'fundTypeId' => $activity->fundType ? $activity->fundType->id : null,
            'fundTypeName' => $activity->fundType ? $activity->fundType->name : 'N/A',
            'divisionName' => $activity->matrix && $activity->matrix->division ? $activity->matrix->division->division_name : 'N/A',
            'dateFrom' => $activity->date_from ? \Carbon\Carbon::parse($activity->date_from)->format('M d, Y') : 'N/A',
            'dateTo' => $activity->date_to ? \Carbon\Carbon::parse($activity->date_to)->format('M d, Y') : 'N/A',
            'numberOfDays' => $activity->date_from && $activity->date_to ? 
                \Carbon\Carbon::parse($activity->date_from)->diffInDays(\Carbon\Carbon::parse($activity->date_to)) + 1 : 'N/A',
            'location' => $activity->locations() ? $activity->locations()->pluck('name')->join(', ') : 'N/A',
            'keyResultArea' => $activity->matrix && $activity->matrix->key_result_area ? 
                collect($activity->matrix->key_result_area)->pluck('description')->join(', ') : 'N/A',
            'quarterlyLinkage' => $activity->quarterly_linkage ?? 'N/A',
            'totalParticipants' => $activity->total_participants ?? 'N/A',
            'internalParticipants' => $activity->internal_participants 
                ? (is_string($activity->internal_participants) 
                    ? count(json_decode($activity->internal_participants, true) ?? []) 
                    : count($activity->internal_participants)) 
                : 0,
            'externalParticipants' => $activity->total_participants ? ($activity->total_participants - ($activity->internal_participants 
                ? (is_string($activity->internal_participants) 
                    ? count(json_decode($activity->internal_participants, true) ?? []) 
                    : count($activity->internal_participants)) 
                : 0)) : 0,
            'budgetCode' => $activity->fundCodes ? $activity->fundCodes->pluck('code')->join(', ') : 'N/A',
            'background' => $activity->background ?? 'N/A',
            'requestForApproval' => $activity->activity_request_remarks ?? 'N/A',
            'totalBudget' => $activity->total_budget ?? '0.00',
            'headOfDivision' => $activity->matrix && $activity->matrix->division && $activity->matrix->division->head ? 
                $activity->matrix->division->head->fname . ' ' . $activity->matrix->division->head->lname : 'N/A',
            'focalPerson' => $activity->staff ? 
                $activity->staff->fname . ' ' . $activity->staff->lname : 'N/A',
            'budgetBreakdown' => $activity->activity_budget ?? [],
            'budgetIds' => is_string($activity->budget_id) 
                ? json_decode($activity->budget_id, true) 
                : ($activity->budget_id ?? []),
            'fundCodes' => \App\Models\FundCode::whereIn('id', is_string($activity->budget_id) 
                ? json_decode($activity->budget_id, true) 
                : ($activity->budget_id ?? []))->with('fundType')->get()->keyBy('id'),
            'defaultTitle' => 'ARF Request - ' . $activity->activity_title,
            'sourceId' => $activity->id,
            'modelType' => 'App\\Models\\Activity'
        ])
            @elseif(in_array($existingArf->overall_status, ['pending', 'approved', 'returned']))
            <div class="alert alert-info">
                <i class="bx bx-info-circle me-2"></i>
                An ARF request has already been created for this activity.
                <a href="{{ route('request-arf.show', $existingArf) }}" class="btn btn-sm btn-outline-primary ms-2">
                    <i class="bx bx-show me-1"></i>View ARF Request
                </a>
            </div>
            @endif
            
        @endif
        
@endpush
