@extends('layouts.app')

@section('title', 'View Service Request - ' . ($serviceRequest->service_title ?? 'Untitled'))

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

    .sidebar-card {
        box-shadow: 0 4px 24px rgba(17, 154, 72, 0.08);
        border-radius: 1.25rem;
        overflow: hidden;
        border: none;
    }

    .budget-card {
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        border: 1px solid #bae6fd;
        border-radius: 1rem;
        padding: 1.5rem;
        margin-bottom: 1rem;
    }

    .budget-item {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        padding: 1rem;
        margin-bottom: 0.75rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .fund-code-card {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border: 1px solid #f59e0b;
        border-radius: 0.75rem;
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .content-section {
        border-left: 4px solid;
        background: #fafafa;
        border-radius: 0.75rem;
        margin-bottom: 1.5rem;
    }
    
    .content-section.bg-blue { 
        border-left-color: #3b82f6; 
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    }
    .content-section.bg-green { 
        border-left-color: #10b981; 
        background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    }
    .content-section.bg-purple { 
        border-left-color: #8b5cf6; 
        background: linear-gradient(135deg, #faf5ff 0%, #e9d5ff 100%);
    }
    .content-section.bg-orange { 
        border-left-color: #f59e0b; 
        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    }

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
        border-radius: 0.75rem;
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

    .info-icon {
        transition: transform 0.2s;
    }
    
    .card:hover .info-icon {
        transform: scale(1.1);
    }

    .budget-icon {
        transition: transform 0.2s;
    }
    
    .card:hover .budget-icon {
        transform: scale(1.1);
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card matrix-card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="h3 mb-2 text-gray-800">
                                <i class="fas fa-concierge-bell me-2 text-primary info-icon"></i>
                                Service Request Details
                            </h1>
                            @if($serviceRequest->request_number)
                                <p class="text-muted mb-0">{{ $serviceRequest->request_number }}</p>
                            @endif
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="d-flex align-items-center justify-content-end gap-3">
                                <span class="status-badge status-{{ $serviceRequest->overall_status ?? 'draft' }}">
                                    {{ ucfirst($serviceRequest->overall_status ?? 'draft') }}
                                </span>
                                @if($serviceRequest->approval_level)
                                    <span class="approval-level-badge">
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
                    <h5 class="mb-0 text-primary">
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
                        <i class="fas fa-calculator me-2 budget-icon"></i>Budget Information
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Original Budget Breakdown -->
                    @if($serviceRequest->budget_breakdown && is_array($serviceRequest->budget_breakdown))
                    <div class="mb-4">
                        <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                            <i class="fas fa-file-invoice-dollar me-2"></i> Original Budget Breakdown
                        </h6>
                        
                        @php
                            $budgetByFundCode = [];
                            $fundCodes = [];
                            $grandTotal = 0;
                            
                            // Process budget structure and organize by fund codes
                            foreach($serviceRequest->budget_breakdown as $key => $item) {
                                if ($key === 'grand_total') {
                                    $grandTotal = floatval($item);
                                    continue;
                                }
                                
                                if (is_array($item)) {
                                    $fundCodeId = $key;
                                    $budgetByFundCode[$fundCodeId] = $item;
                                }
                            }
                        @endphp
                        
                        @if (!empty($budgetByFundCode))
                            @php
                                $count = 1;
                                $grandTotal = 0;
                            @endphp

                            @foreach ($budgetByFundCode as $fundCodeId => $items)
                                @php
                                    $groupTotal = 0;
                                    $itemCount = 1; // Reset counter for each budget code
                                @endphp

                                {{-- Budget Code Title --}}
                                <div class="budget-code-header bg-light p-3 rounded-top mb-0">
                                    <h6 class="mb-0" style="color: #911C39; font-weight: 600;">
                                        Budget Code: {{ $fundCodeId }}
                                    </h6>
                                </div>

                                {{-- Individual Table for this Budget Code --}}
                                <div class="table-responsive mb-4">
                                    <table class="table table-hover table-bordered mb-0">
                                        <thead class="table-secondary">
                                            <tr>
                                                <th class="text-center">#</th>
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
                                                    <td class="text-center">{{ $itemCount }}</td>
                                                    <td>{{ $item['cost'] ?? 'N/A' }}</td>
                                                    <td class="text-end">${{ number_format($unitCost, 2) }}</td>
                                                    <td class="text-end">{{ $units }}</td>
                                                    <td class="text-end">{{ $days }}</td>
                                                    <td class="text-end fw-bold">${{ number_format($total, 2) }}</td>
                                                    <td>{{ $item['description'] ?? '' }}</td>
                                                </tr>
                                                @php
                                                    $itemCount++;
                                                @endphp
                                            @endforeach
                                        </tbody>
                                        <tfoot class="table-group-divider">
                                            <tr class="table-secondary">
                                                <th colspan="5" class="text-end">Sub Total</th>
                                                <th class="text-end">${{ number_format($groupTotal, 2) }}</th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @endforeach

                            {{-- Overall Grand Total --}}
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="total-card text-white p-3 rounded text-center" style="background-color: #2c3d50;">
                                        <h5 class="mb-0 text-white"><strong>Grand Total: ${{ number_format($grandTotal, 2) }} USD</strong></h5>
                                    </div>
                                </div>
                            </div>
                        @else
                            <!-- Fallback: Show budget as key-value pairs if structure is different -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm mb-0">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>Budget Item</th>
                                            <th>Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($serviceRequest->budget_breakdown as $key => $value)
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
                    </div>
                    @endif

                    <!-- Budget Summary -->
                    @if($serviceRequest->original_total_budget || $serviceRequest->new_total_budget)
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="budget-card text-center">
                                <label class="form-label text-muted small fw-semibold">Original Budget</label>
                                <p class="h4 mb-0 text-primary">${{ number_format($serviceRequest->original_total_budget ?? 0, 2) }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="budget-card text-center">
                                <label class="form-label text-muted small fw-semibold">New Total Budget</label>
                                <p class="h4 mb-0 text-success">${{ number_format($serviceRequest->new_total_budget ?? 0, 2) }}</p>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Service Request Budget Breakdown -->
                    @php
                        // Parse the budget breakdown JSON from the service request
                        $budgetData = null;
                        if ($serviceRequest->budget_breakdown) {
                            $budgetData = is_string($serviceRequest->budget_breakdown) 
                                ? json_decode($serviceRequest->budget_breakdown, true) 
                                : $serviceRequest->budget_breakdown;
                        }
                    @endphp
                    
                    @if($budgetData && (isset($budgetData['internal_participants']) || isset($budgetData['external_participants']) || isset($budgetData['other_costs'])))
                    <div class="mb-4">
                        <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                            <i class="fas fa-calculator me-2"></i>Service Request Budget Breakdown
                        </h6>
                        
                        <!-- Internal Participants -->
                        @if(isset($budgetData['internal_participants']) && is_array($budgetData['internal_participants']) && count($budgetData['internal_participants']) > 0)
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary mb-3">
                                <i class="fas fa-users me-2"></i>Internal Participants
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
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
                                                        <div class="budget-item">
                                                            <div class="d-flex justify-content-between">
                                                                <span><strong>{{ $costName }}:</strong></span>
                                                                <span class="text-success fw-bold">${{ number_format($costValue, 2) }}</span>
                                                            </div>
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
                                        <tr class="table-secondary">
                                            <th colspan="3" class="text-end">Internal Total:</th>
                                            <th class="text-end">${{ number_format($budgetData['internal_total'], 2) }}</th>
                                        </tr>
                                    </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>
                        @endif

                        <!-- External Participants -->
                        @if(isset($budgetData['external_participants']) && is_array($budgetData['external_participants']) && count($budgetData['external_participants']) > 0)
                        <div class="mb-4">
                            <h6 class="fw-bold text-warning mb-3">
                                <i class="fas fa-user-friends me-2"></i>External Participants
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
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
                                                        <div class="budget-item">
                                                            <div class="d-flex justify-content-between">
                                                                <span><strong>{{ $costName }}:</strong></span>
                                                                <span class="text-success fw-bold">${{ number_format($costValue, 2) }}</span>
                                                            </div>
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
                                        <tr class="table-secondary">
                                            <th colspan="4" class="text-end">External Total:</th>
                                            <th class="text-end">${{ number_format($budgetData['external_total'], 2) }}</th>
                                        </tr>
                                    </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>
                        @endif

                        <!-- Other Costs -->
                        @if(isset($budgetData['other_costs']) && is_array($budgetData['other_costs']) && count($budgetData['other_costs']) > 0)
                        <div class="mb-4">
                            <h6 class="fw-bold text-info mb-3">
                                <i class="fas fa-list me-2"></i>Other Costs
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
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
                                        <tr class="table-secondary">
                                            <th colspan="5" class="text-end">Other Total:</th>
                                            <th class="text-end">${{ number_format($budgetData['other_total'], 2) }}</th>
                                        </tr>
                                    </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>
                        @endif

                        <!-- Budget Summary -->
                        @if(isset($budgetData['new_total']) || isset($budgetData['original_total']) || isset($budgetData['difference']))
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="budget-summary-card p-4 rounded" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 2px solid #007e33;">
                                    <h5 class="text-center mb-4 text-primary">
                                        <i class="fas fa-calculator me-2"></i>Budget Summary
                                    </h5>
                                    <div class="row text-center">
                                        @if(isset($budgetData['original_total']))
                                        <div class="col-md-4">
                                            <div class="budget-item">
                                                <h6 class="text-muted mb-2">Original Budget</h6>
                                                <h4 class="text-primary mb-0">${{ number_format($budgetData['original_total'], 2) }}</h4>
                                            </div>
                                        </div>
                                        @endif
                                        @if(isset($budgetData['new_total']))
                                        <div class="col-md-4">
                                            <div class="budget-item">
                                                <h6 class="text-muted mb-2">Requested Funds</h6>
                                                <h4 class="text-success mb-0">${{ number_format($budgetData['new_total'], 2) }}</h4>
                                            </div>
                                        </div>
                                        @endif
                                        @if(isset($budgetData['difference']))
                                        <div class="col-md-4">
                                            <div class="budget-item">
                                                <h6 class="text-muted mb-2">Difference</h6>
                                                <h4 class="mb-0 {{ $budgetData['difference'] >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ $budgetData['difference'] >= 0 ? '+' : '' }}${{ number_format($budgetData['difference'], 2) }}
                                                </h4>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
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
                        <i class="fas fa-list-alt me-2 info-icon"></i>Specifications
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
                    <h5 class="mb-0 text-primary">
                        <i class="fas fa-paperclip me-2 info-icon"></i>Attachments
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($serviceRequest->attachments as $attachment)
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center p-3 bg-white border rounded shadow-sm">
                                <i class="fas fa-file me-3 text-primary fs-4"></i>
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
                        <i class="fas fa-comment me-2 info-icon"></i>Remarks
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
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-cogs me-2"></i>Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="action-buttons">
                        @if(function_exists('can_print_service_request') && can_print_service_request($serviceRequest))
                        <a href="{{ route('service-requests.print', $serviceRequest) }}" target="_blank" class="btn btn-primary btn-action">
                            <i class="fas fa-print"></i>
                            Print PDF
                        </a>
                        @endif
                        
                        @if($serviceRequest->overall_status === 'draft' || $serviceRequest->overall_status === 'returned')
                        <a href="{{ route('service-requests.edit', $serviceRequest) }}" class="btn btn-warning btn-action">
                            <i class="fas fa-edit"></i>
                            Edit
                        </a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Status Card -->
            <div class="card sidebar-card mb-4">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Request Status
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <span class="status-badge status-{{ $serviceRequest->overall_status ?? 'draft' }}">
                            {{ ucfirst($serviceRequest->overall_status ?? 'draft') }}
                        </span>
                    </div>
                    
                    @if($serviceRequest->approval_level)
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Approval Level</label>
                        <p class="mb-0">Level {{ $serviceRequest->approval_level }} of {{ $serviceRequest->next_approval_level ?? $serviceRequest->approval_level }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Request Details -->
            <div class="card sidebar-card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-calendar me-2"></i>Request Details
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Request Date</label>
                        <p class="mb-0">{{ \Carbon\Carbon::parse($serviceRequest->request_date)->format('M d, Y') }}</p>
                    </div>
                    
                    @if($serviceRequest->staff)
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Requested By</label>
                        <p class="mb-0 fw-semibold">{{ $serviceRequest->staff->fname }} {{ $serviceRequest->staff->lname }}</p>
                        <small class="text-muted">{{ $serviceRequest->staff->position ?? 'Staff' }}</small>
                    </div>
                    @endif
                    
                    @if($serviceRequest->division)
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Division</label>
                        <p class="mb-0">{{ $serviceRequest->division->name ?? $serviceRequest->division->division_name }}</p>
                    </div>
                    @endif
                    
                    @if($serviceRequest->activity)
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Related Activity</label>
                        <p class="mb-0">{{ $serviceRequest->activity->title ?? 'Untitled Activity' }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Approval Actions -->
            @if(!is_with_creator_generic($serviceRequest) && in_array($serviceRequest->overall_status, ['pending', 'returned']))
            <div class="card sidebar-card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0">
                        <i class="fas fa-gavel me-2"></i>Approval Actions
                    </h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('service-requests.update-status', $serviceRequest) }}" method="POST" id="approvalForm">
                        @csrf
                        <div class="mb-3">
                            <label for="comment" class="form-label">Comments (Optional)</label>
                            <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Add any comments about your decision..."></textarea>
                        </div>
                        <div class="d-grid gap-2">
                            @if(!is_with_creator_generic($serviceRequest))
                                <button type="submit" name="action" value="approved" class="btn btn-success btn-action">
                                    <i class="fas fa-check"></i>
                                    Approve
                                </button>
                            @endif
                            <button type="submit" name="action" value="returned" class="btn btn-warning btn-action">
                                <i class="fas fa-undo"></i>
                                Return
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endif

            <!-- Submit for Approval -->
            @if(in_array($serviceRequest->overall_status,['draft','returned']) && is_with_creator_generic($serviceRequest))
            <div class="card sidebar-card mb-4" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);">
                <div class="card-header bg-transparent border-0 py-3">
                    <h6 class="mb-0 text-primary">
                        <i class="fas fa-paper-plane me-2"></i>Submit for Approval
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Ready to submit this service request for approval?</p>
                    <form action="{{ route('service-requests.submit-for-approval', $serviceRequest) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-action w-100">
                            <i class="fas fa-send"></i>
                            Submit for Approval
                        </button>
                    </form>
                    <div class="mt-3 p-3 bg-light rounded">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Note:</strong> Once submitted, you won't be able to edit this request until it's returned for revision.
                        </small>
                    </div>
                </div>
            </div>
            @endif

            <!-- Approval Trail -->
            @if($serviceRequest->serviceRequestApprovalTrails && $serviceRequest->serviceRequestApprovalTrails->count() > 0)
            <div class="card sidebar-card mb-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-history me-2"></i>Approval Trail
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="timeline">
                        @foreach($serviceRequest->serviceRequestApprovalTrails as $trail)
                        <li class="timeline-item">
                            <div class="timeline-badge {{ $trail->action }}"></div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <strong class="text-sm">{{ ucfirst($trail->action) }}</strong>
                                    <small class="text-muted">{{ \Carbon\Carbon::parse($trail->created_at)->format('M d, Y H:i') }}</small>
                                </div>
                                <p class="text-sm mb-1">{{ $trail->comments }}</p>
                                @if($trail->staff)
                                <small class="text-muted">By: {{ $trail->staff->fname }} {{ $trail->staff->lname }}</small>
                                @endif
                            </div>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
