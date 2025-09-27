@extends('layouts.app')

@section('title', 'View Service Request - ' . ($serviceRequest->service_title ?? 'Untitled'))

@section('styles')
<style>
    .status-badge {
        font-size: 0.875rem;
        font-weight: 600;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        text-transform: capitalize;
    }
    
    .status-approved { @apply bg-green-100 text-green-800 border border-green-200; }
    .status-rejected { @apply bg-red-100 text-red-800 border border-red-200; }
    .status-pending { @apply bg-yellow-100 text-yellow-800 border border-yellow-200; }
    .status-draft { @apply bg-gray-100 text-gray-800 border border-gray-200; }
    .status-returned { @apply bg-blue-100 text-blue-800 border border-blue-200; }
    
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
        border-radius: 0.75rem;
        margin-bottom: 1.5rem;
    }
    
    .content-section.bg-blue { border-left-color: #3b82f6; }
    .content-section.bg-green { border-left-color: #10b981; }
    .content-section.bg-purple { border-left-color: #8b5cf6; }
    .content-section.bg-orange { border-left-color: #f59e0b; }
    
    .sidebar-card {
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        border-radius: 0.75rem;
        overflow: hidden;
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
        padding: 1rem;
        margin-bottom: 1rem;
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
        border-color: #f59e0b;
        color: #f59e0b;
    }

    .timeline-badge.submitted {
        border-color: #3b82f6;
        color: #3b82f6;
    }

    .timeline-content {
        background: #fff;
        border-radius: 0.75rem;
        padding: 1rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        border: 1px solid #e5e7eb;
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .btn-action {
        border-radius: 0.5rem;
        font-weight: 600;
        padding: 0.75rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="h3 mb-2 text-gray-800">
                                <i class="fas fa-concierge-bell me-2 text-success"></i>
                                Service Request Details
                            </h1>
                            @if($serviceRequest->document_number)
                                <p class="text-muted mb-0">{{ $serviceRequest->document_number }}</p>
                            @endif
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="d-flex align-items-center justify-content-end gap-3">
                                <span class="status-badge status-{{ $serviceRequest->overall_status ?? 'draft' }}">
                                    {{ ucfirst($serviceRequest->overall_status ?? 'draft') }}
                                </span>
                                @if($serviceRequest->approval_level)
                                    <span class="badge bg-success">
                                        Level {{ $serviceRequest->approval_level }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Activity Details -->
            <div class="card content-section bg-blue">
                <div class="card-header bg-transparent border-0 py-3">
                    <h5 class="mb-0 text-success">
                        <i class="fas fa-calendar-alt me-2 info-icon"></i>Activity Details
                    </h5>
                </div>
                <div class="card-body">
                    @if($sourceData)
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small fw-semibold">Activity Title</label>
                                <p class="mb-0 fw-semibold">{{ $sourceData->activity_title ?? $sourceData->title ?? 'Not specified' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small fw-semibold">Activity Type</label>
                                <p class="mb-0">
                                    @if(isset($sourceData->is_special_memo) && $sourceData->is_special_memo)
                                        Special Memo
                                    @elseif(isset($sourceData->requestType))
                                        {{ $sourceData->requestType->name ?? 'Service Request' }}
                                    @elseif(isset($sourceData->fundType))
                                        {{ $sourceData->fundType->name ?? 'Service Request' }}
                                    @else
                                        Regular Activity
                                    @endif
                                </p>
                            </div>
                            @if(isset($sourceData->is_special_memo) && $sourceData->is_special_memo)
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small fw-semibold">Memo Date</label>
                                    <p class="mb-0">{{ $sourceData->memo_date ? \Carbon\Carbon::parse($sourceData->memo_date)->format('M d, Y') : 'Not specified' }}</p>
                                </div>
                            @else
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small fw-semibold">Activity Start Date</label>
                                    <p class="mb-0">{{ $sourceData->date_from ? \Carbon\Carbon::parse($sourceData->date_from)->format('M d, Y') : 'Not specified' }}</p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small fw-semibold">Activity End Date</label>
                                    <p class="mb-0">{{ $sourceData->date_to ? \Carbon\Carbon::parse($sourceData->date_to)->format('M d, Y') : 'Not specified' }}</p>
                                </div>
                            @endif
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small fw-semibold">Key Result Area</label>
                                <p class="mb-0">{{ $sourceData->key_result_area ?? 'Not specified' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small fw-semibold">Total Participants</label>
                                <p class="mb-0">{{ $sourceData->total_participants ?? 'Not specified' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small fw-semibold">External Participants</label>
                                <p class="mb-0">{{ $sourceData->total_external_participants ?? 'Not specified' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small fw-semibold">Location</label>
                                <p class="mb-0">{{ $sourceData->location ?? 'Not specified' }}</p>
                            </div>
                        </div>
                        
                        @if($sourceData->background)
                        <div class="mt-3">
                            <label class="form-label text-muted small fw-semibold">Background</label>
                            <p class="mb-0">{!! $sourceData->background !!}</p>
                        </div>
                        @endif
                        
                        @if($sourceData->objectives)
                        <div class="mt-3">
                            <label class="form-label text-muted small fw-semibold">Objectives</label>
                            <p class="mb-0">{{ $sourceData->objectives }}</p>
                        </div>
                        @endif
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-info-circle me-2"></i>No activity information available
                        </div>
                    @endif
                </div>
            </div>

            <!-- Budget Information -->
            @if($serviceRequest->budget_breakdown || $serviceRequest->internal_participants_cost || $serviceRequest->external_participants_cost || $serviceRequest->other_costs)
            <div class="card content-section bg-green">
                <div class="card-header bg-transparent border-0 py-3">
                    <h5 class="mb-0 text-success">
                        <i class="fas fa-calculator me-2"></i>Budget Information
                    </h5>
                </div>
                <div class="card-body">
                    @php
                        // Parse the budget breakdown JSON from the service request
                        $budgetData = null;
                        if ($serviceRequest->budget_breakdown) {
                            $budgetData = is_string($serviceRequest->budget_breakdown) 
                                ? json_decode($serviceRequest->budget_breakdown, true) 
                                : $serviceRequest->budget_breakdown;
                        }
                    @endphp
                    
                    <!-- Budget Summary Cards -->
                    @if($serviceRequest->original_total_budget || $serviceRequest->new_total_budget)
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="meta-card text-center">
                                <label class="form-label text-muted small fw-semibold">Original Memo Budget</label>
                                <p class="h4 mb-0 text-success">${{ number_format($serviceRequest->original_total_budget ?? 0, 2) }}</p>
                            </div>
                        </div>
                        @if($sourceData && isset($sourceData->available_budget) && $sourceData->available_budget)
                        <div class="col-md-3">
                            <div class="meta-card text-center">
                                <label class="form-label text-muted small fw-semibold">Allocated Budget (Finance)</label>
                                <p class="h4 mb-0 text-dark fw-bold">${{ number_format($sourceData->available_budget, 2) }}</p>
                            </div>
                        </div>
                        @endif
                        <div class="col-md-3">
                            <div class="meta-card text-center">
                                <label class="form-label text-muted small fw-semibold">Total Requested Funds</label>
                                <p class="h4 mb-0 text-success">${{ number_format($serviceRequest->new_total_budget ?? 0, 2) }}</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="meta-card text-center">
                                <label class="form-label text-muted small fw-semibold">Difference</label>
                                @php
                                    // Use allocated budget if available, otherwise fall back to original budget
                                    $allocatedBudget = ($sourceData && isset($sourceData->available_budget) && $sourceData->available_budget) 
                                        ? $sourceData->available_budget 
                                        : ($serviceRequest->original_total_budget ?? 0);
                                    $difference = $allocatedBudget - ($serviceRequest->new_total_budget ?? 0);
                                @endphp
                                <p class="h4 mb-0 {{ $difference >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $difference >= 0 ? '+' : '' }}${{ number_format($difference, 2) }}
                                </p>
                                @if($difference >= 0)
                                    <small class="text-success fw-semibold">
                                        <i class="fas fa-check-circle me-1"></i>Within Allocated Budget
                                    </small>
                                @else
                                    <small class="text-danger fw-semibold">
                                        <i class="fas fa-exclamation-triangle me-1"></i>Exceeds Allocated Budget
                                    </small>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Service Request Budget Breakdown -->
                    @if($budgetData && (isset($budgetData['internal_participants']) || isset($budgetData['external_participants']) || isset($budgetData['other_costs'])))
                    <div class="mb-4">
                        <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                            <i class="fas fa-calculator me-2"></i>Budget Breakdown
                        </h6>
                        
                        <!-- Internal Participants -->
                        @if(isset($budgetData['internal_participants']) && is_array($budgetData['internal_participants']) && count($budgetData['internal_participants']) > 0)
                        <div class="mb-4">
                            <h6 class="fw-bold text-success mb-3">
                                <i class="fas fa-users me-2"></i>Internal Participants
                            </h6>
                            <div class="table-responsive">
                                <table class="table budget-table table-hover table-bordered">
                                    <thead class="table-primary">
                                        <tr>
                                            <th class="text-center">#</th>
                                            <th>Staff Member</th>
                                            <th>Costs Breakdown</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($budgetData['internal_participants'] as $index => $participant)
                                        @php
                                            $staff = null;
                                            if (isset($participant['staff_id'])) {
                                                $staff = \App\Models\Staff::find($participant['staff_id']);
                                            }
                                        @endphp
                                        <tr>
                                            <td class="text-center">{{ $index + 1 }}</td>
                                            <td>
                                                @if($staff)
                                                    <div>
                                                        <strong>{{ $staff->fname }} {{ $staff->lname }}</strong>
                                                        <br><small class="text-muted">{{ $staff->position ?? 'Staff' }}</small>
                                                    </div>
                                                @else
                                                    <span class="text-muted">Staff ID: {{ $participant['staff_id'] ?? 'Unknown' }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if(isset($participant['costs']) && is_array($participant['costs']))
                                                    @foreach($participant['costs'] as $costName => $costValue)
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <span><strong>{{ $costName }}:</strong></span>
                                                            <span class="text-success fw-bold">${{ number_format($costValue, 2) }}</span>
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <span class="text-muted">No costs specified</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <strong class="text-success h6">${{ number_format($participant['total'] ?? 0, 2) }}</strong>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    @if(isset($budgetData['internal_total']))
                                    <tfoot class="table-group-divider">
                                        <tr class="budget-total-row">
                                            <th colspan="3" class="text-end">Internal Total:</th>
                                            <th class="text-end">${{ number_format($budgetData['internal_total'], 2) }}</th>
                                        </tr>
                                    </tfoot>
                                    @endif
                                </table>
                            </div>
                            
                            <!-- Internal Participants Comments -->
                            @if($serviceRequest->internal_participants_comment)
                            <div class="mt-3 p-3 bg-light rounded border-start border-success border-4">
                                <h6 class="fw-bold text-success mb-2">
                                    <i class="fas fa-comment me-2"></i>Internal Participants Comments
                                </h6>
                                <p class="mb-0 text-muted">{{ $serviceRequest->internal_participants_comment }}</p>
                            </div>
                            @endif
                        </div>
                        @endif

                        <!-- External Participants -->
                        @if(isset($budgetData['external_participants']) && is_array($budgetData['external_participants']) && count($budgetData['external_participants']) > 0)
                        <div class="mb-4">
                            <h6 class="fw-bold text-warning mb-3">
                                <i class="fas fa-user-friends me-2"></i>External Participants
                            </h6>
                            <div class="table-responsive">
                                <table class="table budget-table table-hover table-bordered">
                                    <thead class="table-warning">
                                        <tr>
                                            <th class="text-center">#</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Costs Breakdown</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($budgetData['external_participants'] as $index => $participant)
                                        <tr>
                                            <td class="text-center">{{ $index + 1 }}</td>
                                            <td><strong>{{ $participant['name'] ?? 'N/A' }}</strong></td>
                                            <td><small class="text-muted">{{ $participant['email'] ?? 'N/A' }}</small></td>
                                            <td>
                                                @if(isset($participant['costs']) && is_array($participant['costs']))
                                                    @foreach($participant['costs'] as $costName => $costValue)
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <span><strong>{{ $costName }}:</strong></span>
                                                            <span class="text-success fw-bold">${{ number_format($costValue, 2) }}</span>
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <span class="text-muted">No costs specified</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <strong class="text-success h6">${{ number_format($participant['total'] ?? 0, 2) }}</strong>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    @if(isset($budgetData['external_total']))
                                    <tfoot class="table-group-divider">
                                        <tr class="budget-total-row">
                                            <th colspan="4" class="text-end">External Total:</th>
                                            <th class="text-end">${{ number_format($budgetData['external_total'], 2) }}</th>
                                        </tr>
                                    </tfoot>
                                    @endif
                                </table>
                            </div>
                            
                            <!-- External Participants Comments -->
                            @if($serviceRequest->external_participants_comment)
                            <div class="mt-3 p-3 bg-light rounded border-start border-warning border-4">
                                <h6 class="fw-bold text-warning mb-2">
                                    <i class="fas fa-comment me-2"></i>External Participants Comments
                                </h6>
                                <p class="mb-0 text-muted">{{ $serviceRequest->external_participants_comment }}</p>
                            </div>
                            @endif
                        </div>
                        @endif

                        <!-- Other Costs -->
                        @if(isset($budgetData['other_costs']) && is_array($budgetData['other_costs']) && count($budgetData['other_costs']) > 0)
                        <div class="mb-4">
                            <h6 class="fw-bold text-info mb-3">
                                <i class="fas fa-list me-2"></i>Other Costs
                            </h6>
                            <div class="table-responsive">
                                <table class="table budget-table table-hover table-bordered">
                                    <thead class="table-info">
                                        <tr>
                                            <th class="text-center">#</th>
                                            <th>Item</th>
                                            <th>Unit Cost</th>
                                            <th>Days</th>
                                            <th>Description</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($budgetData['other_costs'] as $index => $cost)
                                        <tr>
                                            <td class="text-center">{{ $index + 1 }}</td>
                                            <td><strong>{{ $cost['cost_type'] ?? 'N/A' }}</strong></td>
                                            <td>${{ number_format($cost['unit_cost'] ?? 0, 2) }}</td>
                                            <td>{{ $cost['days'] ?? 0 }}</td>
                                            <td>{{ $cost['description'] ?? 'N/A' }}</td>
                                            <td class="text-end">
                                                <strong class="text-success h6">${{ number_format(($cost['unit_cost'] ?? 0) * ($cost['days'] ?? 0), 2) }}</strong>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    @if(isset($budgetData['other_total']))
                                    <tfoot class="table-group-divider">
                                        <tr class="budget-total-row">
                                            <th colspan="5" class="text-end">Other Total:</th>
                                            <th class="text-end">${{ number_format($budgetData['other_total'], 2) }}</th>
                                        </tr>
                                    </tfoot>
                                    @endif
                                </table>
                            </div>
                            
                            <!-- Other Costs Comments -->
                            @if($serviceRequest->other_costs_comment)
                            <div class="mt-3 p-3 bg-light rounded border-start border-info border-4">
                                <h6 class="fw-bold text-info mb-2">
                                    <i class="fas fa-comment me-2"></i>Other Costs Comments
                                </h6>
                                <p class="mb-0 text-muted">{{ $serviceRequest->other_costs_comment }}</p>
                            </div>
                            @endif
                        </div>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Specifications -->
            @if($serviceRequest->specifications && is_array($serviceRequest->specifications) && count($serviceRequest->specifications) > 0)
            <div class="card content-section bg-purple">
                <div class="card-header bg-transparent border-0 py-3">
                    <h5 class="mb-0 text-purple">
                        <i class="fas fa-list-alt me-2"></i>Specifications
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        @foreach($serviceRequest->specifications as $spec)
                        <li class="mb-3 p-3 bg-white rounded border">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            {{ $spec }}
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif

            <!-- Attachments -->
            @if($serviceRequest->attachments && is_array($serviceRequest->attachments) && count($serviceRequest->attachments) > 0)
            <div class="card content-section bg-blue">
                <div class="card-header bg-transparent border-0 py-3">
                    <h5 class="mb-0 text-success">
                        <i class="fas fa-paperclip me-2"></i>Attachments
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($serviceRequest->attachments as $attachment)
                        <div class="col-md-6 mb-3">
                            <div class="attachment-item d-flex align-items-center">
                                <i class="fas fa-file me-3 text-success fs-4"></i>
                                <div class="flex-grow-1">
                                    <p class="mb-1 fw-semibold">{{ $attachment['name'] ?? 'Unknown File' }}</p>
                                    <small class="text-muted">{{ $attachment['size'] ?? 'Unknown size' }}</small>
                                </div>
                                <a href="{{ $attachment['url'] ?? '#' }}" class="btn btn-outline-primary btn-sm" target="_blank">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Remarks -->
            @if($serviceRequest->remarks)
            <div class="card content-section bg-orange">
                <div class="card-header bg-transparent border-0 py-3">
                    <h5 class="mb-0 text-warning">
                        <i class="fas fa-comment me-2"></i>Remarks
                    </h5>
                </div>
                <div class="card-body">
                    <p class="mb-0 p-3 bg-white rounded border">{{ $serviceRequest->remarks }}</p>
                </div>
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Action Buttons -->
            <div class="card sidebar-card mb-4">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0 text-white">
                        <i class="fas fa-cogs me-2"></i>Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="action-buttons">
                        @if(can_print_memo($serviceRequest))
                        <a href="{{ route('service-requests.print', $serviceRequest) }}" target="_blank" class="btn btn-primary btn-action">
                            <i class="fas fa-print"></i>
                            Print PDF
                        </a>
                        @endif
                        
                    </div>
                </div>
            </div>

              
            <!-- Sidebar -->
            <div>
                <!-- Quick Approval Status -->
                <div class="card sidebar-card border-0 mb-4">
                    <div class="card-header border-0 py-3" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                        <h6 class="mb-0 fw-bold d-flex align-items-center gap-2">
                            <i class="bx bx-trending-up text-success"></i>
                            Approval Progress
                        </h6>
            </div>
            <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Current Level</span>
                                <span class="badge bg-success fs-6">{{ $serviceRequest->approval_level ?? 0 }}</span>
                            </div>
                            @if($serviceRequest->forwardWorkflow)
                                <div class="mb-2">
                                    <small class="text-muted">Workflow:</small><br>
                                    <strong class="text-success">{{ $serviceRequest->forwardWorkflow->name ?? 'Service Request Workflow' }}</strong>
                                </div>
                            @endif
                            @if($serviceRequest->workflow_definition)
                                <div class="mb-2">
                                    <small class="text-muted">Role:</small><br>
                                    <strong>{{ $serviceRequest->workflow_definition->role ?? 'Not specified' }}</strong>
                                </div>
                            @endif
                            @if($serviceRequest->current_actor)
                                <div class="mb-2">
                                    <small class="text-muted">Current Approver:</small><br>
                                    <strong>{{ $serviceRequest->current_actor->fname }} {{ $serviceRequest->current_actor->lname }}</strong>
                    </div>
                @endif
            </div>
                        <div class="progress mb-2" style="height: 8px;">
                            @php
                                $totalLevels = $approvalLevels ? count($approvalLevels) : 0;
                                $currentLevel = $serviceRequest->approval_level ?? 0;
                                $progressPercentage = $totalLevels > 0 ? min(($currentLevel / $totalLevels) * 100, 100) : 0;
                            @endphp
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: {{ $progressPercentage }}%"></div>
                        </div>
                        <small class="text-muted">
                            Level {{ max(0, ($serviceRequest->approval_level ?? 0) - 1) }} of {{ $totalLevels }}
                        </small>
                        
                        @if(!empty($approvalLevels) && is_array($approvalLevels))
                            <div class="mt-3">
                                <small class="text-muted d-block mb-2">Approval Levels:</small>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($approvalLevels as $level)
                                        <span class="badge bg-{{ $level['is_completed'] ? 'success' : ($level['is_current'] ? 'success' : 'light') }} small">
                                            {{ $level['order'] }}. {{ $level['role'] }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
            </div>
        </div>
        
                <!-- Enhanced Approval Actions -->
                @if(can_take_action_generic($serviceRequest) || is_with_creator_generic($serviceRequest))
                    <div class="card border-0 shadow-lg mb-4" style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
                        <div class="card-header bg-transparent border-0 py-4" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 12px 12px 0 0;">
                            <h6 class="mb-0 fw-bold text-gray-800 d-flex align-items-center gap-2" style="color: #1f2937;">
                                <i class="bx bx-check-circle" style="color: #059669;"></i>
                                Approval Actions
                            </h6>
                        </div>
                <div class="card-body">
                                <form action="{{ route('service-requests.update-status', $serviceRequest) }}" method="POST" id="approvalForm">
                                @csrf
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approvalModal">
                                        <i class="bx bx-check me-1"></i> Proceed
                                    </button>
                                    <button type="submit" name="action" value="rejected" class="btn btn-danger">
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
                                @if($serviceRequest->approvalTrails->count() > 0)
                            @include('partials.approval-trail', ['resource' => $serviceRequest])
                        @else
                            <div class="text-center text-muted py-4">
                                <i class="bx bx-time bx-lg mb-3"></i>
                                <p class="mb-0">No approval actions have been taken yet.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Submit for Approval -->


        
        </div>
    </div>
</div>
@endsection


<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1" aria-labelledby="approvalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #119A48 0%, #0d7a3a 100%);">
                <h5 class="modal-title text-white" id="approvalModalLabel">
                    <i class="bx bx-check-circle me-2"></i> Approve Service Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('service-requests.update-status', $serviceRequest) }}" method="POST" id="approvalModalForm">
                @csrf
                <div class="modal-body">
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
                        <i class="bx bx-check me-1"></i>Approve Service Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>