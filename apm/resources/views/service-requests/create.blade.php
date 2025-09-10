@extends('layouts.app')

@section('title', 'Create Service Request')

@section('header', 'Service Request Form')

@section('header-actions')
    <a href="{{ route('service-requests.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back me-1 text-success"></i> Back to List
    </a>
@endsection

@section('content')
@php
    // Initialize budget variables
    $totalOriginal = 0;
    $budgetBreakdown = null;
        $budgetByFundCode = [];
        $fundCodes = [];
    
    // Process source data if available
    if ($sourceData && isset($sourceData->budget_breakdown)) {
        $budgetBreakdown = is_string($sourceData->budget_breakdown) 
                ? json_decode(stripslashes($sourceData->budget_breakdown), true)
            : $sourceData->budget_breakdown;
            
            // Handle double-encoded JSON (sometimes happens with form submissions)
            if (is_string($budgetBreakdown) && !is_array($budgetBreakdown)) {
                $budgetBreakdown = json_decode($budgetBreakdown, true);
            }

        if (is_array($budgetBreakdown) && !empty($budgetBreakdown)) {
                // Parse budget structure and organize by fund codes (same logic as single memo show)
                foreach ($budgetBreakdown as $key => $item) {
                    if ($key === 'grand_total') {
                        $totalOriginal = floatval($item);
                    } elseif (is_array($item)) {
                        // Handle array of budget items (like "29" => [{...}])
                        $fundCodeId = $key;
                        $budgetByFundCode[$fundCodeId] = $item;
                    } elseif (is_numeric($item)) {
                        $totalOriginal += floatval($item);
                    }
                }

                // Fetch fund code details for display
                if (!empty($budgetByFundCode)) {
                    $fundCodeIds = array_keys($budgetByFundCode);
                    $fundCodes = \App\Models\FundCode::whereIn('id', $fundCodeIds)->get()->keyBy('id');
                }

                // If no grand_total found, calculate from items with proper days logic
                if ($totalOriginal == 0 && !empty($budgetByFundCode)) {
                    foreach ($budgetByFundCode as $fundCodeId => $items) {
                        foreach ($items as $item) {
                            if (isset($item['unit_cost']) && isset($item['units'])) {
                                $unitCost = floatval($item['unit_cost']);
                                $units = floatval($item['units']);
                                $days = floatval($item['days'] ?? 1);

                                // Use days when greater than 1, otherwise just unit_cost * units
                                if ($days > 1) {
                                    $totalOriginal += $unitCost * $units * $days;
                                } else {
                                    $totalOriginal += $unitCost * $units;
                                }
                            }
                        }
                    }
            }
        }
    }
@endphp

<div class="card shadow-sm border-0 mb-5">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 text-dark">
            <i class="fas fa-tools me-2"></i> Service Request Details
        </h5>
                </div>
    <div class="card-body p-4">
            <form action="{{ route('service-requests.store') }}" method="POST" enctype="multipart/form-data"
                id="serviceRequestForm">
                        @csrf
            
            <!-- Hidden fields for source data -->
            <input type="hidden" name="source_type" value="{{ $sourceType }}">
            <input type="hidden" name="source_id" value="{{ $sourceId }}">
                <input type="hidden" name="model_type"
                    value="{{ $sourceType ? 'App\\Models\\' . ucfirst(str_replace('_', '', $sourceType)) : '' }}">
                <input type="hidden" name="fund_type_id" value="{{ $sourceData->fund_type_id ?? 1 }}">
                <input type="hidden" name="responsible_person_id"
                    value="{{ user_session('staff_id') }}">
                <input type="hidden" name="budget_id" value="{{ $sourceData->budget_id ?? '[]' }}">
                <input type="hidden" name="original_total_budget" id="originalTotalBudget"
                    value="{{ $originalTotalBudget ?? 0 }}">
            <input type="hidden" name="new_total_budget" id="newTotalBudget" value="0">
            <input type="hidden" name="budget_breakdown" id="budgetBreakdown" value="">
            <input type="hidden" name="internal_participants_cost" id="internalParticipantsCost" value="">
            <input type="hidden" name="external_participants_cost" id="externalParticipantsCost" value="">
            <input type="hidden" name="other_costs" id="otherCosts" value="">
            
            <!-- Additional required fields -->
            <input type="hidden" name="request_date" value="{{ date('Y-m-d') }}">
                <input type="hidden" name="location" value="{{ $sourceData ? $sourceData->location ?? 'N/A' : 'N/A' }}">
               
            <input type="hidden" name="status" value="draft">
                <input type="hidden" name="service_title"
                    value="{{ $sourceData ? ($sourceData->activity_title ?? $sourceData->title) : 'Service Request' }}">
                <input type="hidden" name="description"
                    value="{{ $sourceData ? ($sourceData->background ?? $sourceData->description) : 'Service Request Description' }}">
                <input type="hidden" name="justification"
                    value="{{ $sourceData ? ($sourceData->activity_request_remarks ?? $sourceData->justification) : 'Service Request Justification' }}">

    <!-- Section 6: Activity Information -->
            <div class="mb-5">
                <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                        <i class="fas fa-info-circle me-2"></i> Activity Information
                </h6>
                        
                    <!-- Activity Title -->
                    <div class="mb-3">
                        <div class="card border-success">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-file-alt text-success me-3"></i>
                                    <h6 class="text-muted mb-0 me-3">Activity Title:</h6>
                                    <h5 class="fw-bold text-dark mb-0">
                                        {{ $sourceData ? ($sourceData->activity_title ?? $sourceData->title ?? 'Service Request') : 'Service Request' }}
                                    </h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="card border-info">
                                <div class="card-body p-2">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-building text-info me-2"></i>
                                        <small class="text-muted me-2">Division:</small>
                                        <span class="fw-bold text-dark">
                                            {{ $sourceData && isset($sourceData->division) ? $sourceData->division->division_name : 
                                              ($sourceData && isset($sourceData->matrix) && $sourceData->matrix->division ? $sourceData->matrix->division->division_name : 'N/A') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-primary">
                                <div class="card-body p-2">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-tag text-primary me-2"></i>
                                        <small class="text-muted me-2">Activity Type:</small>
                                        <span class="fw-bold text-dark">
                                            {{ $sourceData && isset($sourceData->requestType) ? $sourceData->requestType->name : 
                                              ($sourceData && isset($sourceData->fundType) ? $sourceData->fundType->name : 'Service Request') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

    <!-- Section 7: Budget Summary -->
                <div class="mb-5">
                    <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                        <i class="fas fa-calculator me-2"></i> Budget Summary
                    </h6>

                    <div class="row g-2">
                        <div class="col-md-4">
                            <div class="card border-success">
                                <div class="card-body text-center p-2">
                                    <div class="budget-icon bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-1" style="width: 25px; height: 25px;">
                                        <i class="fas fa-file-invoice-dollar text-success" style="font-size: 12px;"></i>
                                    </div>
                                    <h6 class="card-title text-success mb-1" style="font-size: 0.8rem;">Original Budget</h6>
                                    <h6 class="text-success mb-0" id="originalBudgetAmount" style="font-size: 1.1rem;">
                                        ${{ number_format($totalOriginal, 2) }}</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-primary">
                                <div class="card-body text-center p-2">
                                    <div class="budget-icon bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-1" style="width: 25px; height: 25px;">
                                        <i class="fas fa-calculator text-primary" style="font-size: 12px;"></i>
                                    </div>
                                    <h6 class="card-title text-primary mb-1" style="font-size: 0.8rem;">New Budget</h6>
                                    <h6 class="text-primary mb-0" id="newBudgetAmount" style="font-size: 1.1rem;">$0.00</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-warning">
                                <div class="card-body text-center p-2">
                                    <div class="budget-icon bg-warning bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-1" style="width: 25px; height: 25px;">
                                        <i class="fas fa-balance-scale text-warning" style="font-size: 12px;"></i>
                                    </div>
                                    <h6 class="card-title text-warning mb-1" style="font-size: 0.8rem;">Budget Difference</h6>
                                    <h6 class="text-warning mb-0" id="budgetDifference" style="font-size: 1.1rem;">$0.00</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <!-- Section 2: Original Budget Breakdown -->
            <div class="mb-5">
                <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                    <i class="fas fa-file-invoice-dollar me-2"></i> Original Budget Breakdown
                </h6>
                
                    @if (!empty($budgetBreakdown))
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
                                <div class="budget-code-header bg-light p-3 rounded-top mb-0">
                                    <h6 class="mb-0" style="color: #911C39; font-weight: 600;">
                                        @if ($fundCode)
                                            {{ $fundCode->activity }} - {{ $fundCode->code }} -
                                            ({{ $fundCode->fundType->name ?? 'N/A' }})
                                        @else
                                            Budget Code: {{ $fundCodeId }}
                                        @endif
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
                                    <div class="total-card bg-dark text-white p-3 rounded text-center">
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
                                        @foreach ($budgetBreakdown as $key => $value)
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
                            <i class="fas fa-info-circle me-2"></i>No budget data available from source
                        </div>
                    @endif
                                </div>
                                
            <!-- Section 3: Individual Costs (Internal Participants) -->
            <div class="mb-5">
                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                        <h6 class="fw-bold text-success mb-0">
                    <i class="fas fa-users me-2"></i> Individual Costs (Internal Participants)
                </h6>
                        <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm" id="addInternal">
                        <i class="fas fa-plus me-1"></i> Add Participant
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="removeInternal">
                        <i class="fas fa-minus me-1"></i> Remove Participant
                    </button>
                        </div>
                            </div>
                            
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                            <thead class="table-secondary">
                            <tr>
                                <th style="width: 25%;">Name</th>
                                    @foreach ($costItems as $costItem)
                                    <th style="width: {{ 75 / count($costItems) }}%;">{{ $costItem->name }}</th>
                                                        @endforeach
                                <th style="width: 25%;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="internalParticipants">
                            <tr>
                                <td>
                                        <select name="internal_participants[0][staff_id]"
                                            class="form-select border-success participant-select" style="width: 100%;">
                                        <option value="">Select Participant</option>
                                            @if (!empty($participantNames))
                                                @foreach ($participantNames as $participant)
                                                <option value="{{ $participant['id'] }}">
                                                    {{ $participant['text'] }}
                                                </option>
                                            @endforeach
                                        @else
                                                @foreach ($staff as $member)
                                                <option value="{{ $member->staff_id }}">
                                                        {{ $member->fname }} {{ $member->lname }}
                                                        ({{ $member->position ?? 'Staff' }})
                                                            </option>
                                                        @endforeach
                                        @endif
                                                    </select>
                                </td>
                                    @foreach ($costItems as $index => $costItem)
                                    <td>
                                            <input type="text"
                                               name="internal_participants[0][costs][{{ $costItem->id }}]" 
                                                class="form-control border-success cost-input" value="0"
                                               data-cost-item="{{ $costItem->name }}"
                                                placeholder="Enter {{ $costItem->name }}"
                                                pattern="[0-9]+(\.[0-9]{1,2})?"
                                                title="Enter a valid number (e.g., 1000.50)">
                                    </td>
                                                        @endforeach
                                    <td class="text-end fw-bold total-cell">$0.00</td>
                            </tr>
                        </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <td colspan="{{ count($costItems) + 1 }}" class="text-end fw-bold">Subtotal:</td>
                                <td class="text-end fw-bold" id="internalSubtotal">$0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                                                </div>
                                            </div>

            <!-- Section 4: Individual Costs (External Participants) -->
            <div class="mb-5">
                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                        <h6 class="fw-bold text-success mb-0">
                    <i class="fas fa-user-friends me-2"></i> Individual Costs (External Participants)
                </h6>
                        <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm" id="addExternal">
                        <i class="fas fa-plus me-1"></i> Add Participant
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="removeExternal">
                        <i class="fas fa-minus me-1"></i> Remove Participant
                    </button>
                        </div>
                                        </div>
                
                <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-secondary">
                                <tr>
                                    <th style="width: 13%;">Name</th>
                                <th style="width: 13%;">Email</th>
                                    @foreach ($costItems as $costItem)
                                    <th style="width: {{ 74 / count($costItems) }}%;">{{ $costItem->name }}</th>
                                @endforeach
                                <th style="width: 13%;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="externalParticipants">
                            <tr>
                                    <td><input type="text" name="external_participants[0][name]"
                                            class="form-control border-success" placeholder="Name" value=""></td>
                                    <td><input type="email" name="external_participants[0][email]"
                                            class="form-control border-success" placeholder="Email" value=""></td>
                                    @foreach ($costItems as $index => $costItem)
                                        <td>
                                            <input type="text"
                                               name="external_participants[0][costs][{{ $costItem->id }}]" 
                                                class="form-control border-success cost-input" value="0"
                                               data-cost-item="{{ $costItem->name }}"
                                                placeholder="Enter {{ $costItem->name }}"
                                                pattern="[0-9]+(\.[0-9]{1,2})?"
                                                title="Enter a valid number (e.g., 1000.50)">
                                    </td>
                                @endforeach
                                    <td class="text-end fw-bold total-cell">$0.00</td>
                            </tr>
                        </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <td colspan="{{ count($costItems) + 2 }}" class="text-end fw-bold">Subtotal:</td>
                                <td class="text-end fw-bold" id="externalSubtotal">$0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                                    </div>
                                </div>
                                
            <!-- Section 5: Other Costs -->
            <div class="mb-5">
                <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                    <i class="fas fa-receipt me-2"></i> Other Costs
                </h6>
                
                <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-secondary">
                                <tr>
                                    <th>Cost Type</th>
                                <th>Unit Cost</th>
                                <th>No. of Days</th>
                                <th>Description</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="otherCosts">
                                @if ($otherCostItems->isNotEmpty())
                                    @foreach ($otherCostItems as $index => $costItem)
                                    <tr>
                                        <td>
                                                <select name="other_costs[{{ $index }}][cost_type]"
                                                    class="form-select border-success">
                                                <option value="{{ $costItem->name }}">{{ $costItem->name }}</option>
                                                    @foreach ($otherCostItems as $item)
                                                        @if ($item->id != $costItem->id)
                                                            <option value="{{ $item->name }}">{{ $item->name }}
                                                            </option>
                                                    @endif
                                                        @endforeach
                                                    </select>
                                        </td>
                                        <td>
                                                <input type="text" name="other_costs[{{ $index }}][unit_cost]"
                                                    class="form-control border-success cost-input" value="0"
                                                    placeholder="Enter unit cost"
                                                    pattern="[0-9]+(\.[0-9]{1,2})?"
                                                    title="Enter a valid number (e.g., 1000.50)">
                                        </td>
                                        <td>
                                                <input type="number" name="other_costs[{{ $index }}][days]"
                                                    class="form-control border-success" value="1" min="1"
                                                   placeholder="Enter days">
                                        </td>
                                        <td>
                                                <textarea name="other_costs[{{ $index }}][description]" class="form-control border-success" rows="2"
                                                      placeholder="Enter description"></textarea>
                                        </td>
                                            <td class="text-end fw-bold total-cell">$0.00</td>
                                    </tr>
                                                        @endforeach
                            @else
                                <tr>
                                        <td colspan="5" class="text-center text-muted">No Other Cost items available
                                        </td>
                                </tr>
                            @endif
                        </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <td colspan="4" class="text-end fw-bold">Subtotal:</td>
                                <td class="text-end fw-bold" id="otherSubtotal">$0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                                    </div>
                                </div>
                                
            <!-- Section 6: Participants Summary -->
            <div class="mb-5">
                <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                    <i class="fas fa-users me-2"></i> Participants Summary
                </h6>
                
                <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-secondary">
                                <tr>
                                    <th style="width: 5%;">#</th>
                                <th style="width: 25%;">Name</th>
                                <th style="width: 20%;">Type</th>
                                <th style="width: 25%;">Email/Position</th>
                                <th style="width: 15%;">Role</th>
                                <th style="width: 10%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="participantsSummary">
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                        <i class="fas fa-info-circle me-2"></i>Participants will be automatically added
                                        from the cost sections above
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
                                
             
                                
            <!-- Action Buttons -->
                <div class="d-flex justify-content-end gap-3 mt-4 pt-3 border-top">
                    <a href="{{ route('service-requests.index') }}" class="btn btn-outline-secondary px-4">
                    <i class="fas fa-times me-1"></i> Cancel
                </a>
                    <button type="submit" class="btn btn-success px-4">
                    <i class="fas fa-paper-plane me-1"></i> Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

    <style>
        .table th {
            font-weight: 600;
            background-color: #f8f9fa;
        }
        
         .table-secondary th {
             background-color: #e9ecef;
             color: #495057;
         }
         
         .table-secondary td {
             background-color: #f8f9fa;
         }
        
        .table-hover tbody tr:hover {
            background-color: rgba(25, 135, 84, 0.05);
        }
        
        .budget-code-header {
            border: 1px solid #dee2e6;
            border-bottom: none;
        }
        
        .total-card {
            box-shadow: 0 0.5rem 1rem rgba(25, 135, 84, 0.15);
        }
        
        .budget-icon {
            transition: transform 0.2s;
        }
        
        .card:hover .budget-icon {
            transform: scale(1.1);
        }
        
        .cost-input:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
        }
        
        .table-group-divider {
            border-top-color: #198754;
            border-top-width: 2px;
        }
        
         .total-cell {
             background-color: rgba(25, 135, 84, 0.05);
         }
         
         .info-icon {
             transition: transform 0.2s;
         }
         
         .card:hover .info-icon {
             transform: scale(1.1);
         }
    </style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let internalParticipantCount = 1;
    let externalParticipantCount = 1;
    
    // Add internal participant
    document.getElementById('addInternal').addEventListener('click', function() {
        const tbody = document.getElementById('internalParticipants');
        const newRow = tbody.rows[0].cloneNode(true);
        
        // Update input names and clear values
        const inputs = newRow.querySelectorAll('input, select');
        inputs.forEach(input => {
            if (input.name) {
                        input.name = input.name.replace('[0]', '[' + internalParticipantCount +
                        ']');
            }
                    if (input.type === 'number' || input.type === 'text' || input.type ===
                        'email') {
                input.value = '';
            } else if (input.tagName === 'SELECT') {
                input.selectedIndex = 0;
            }
        });
        
        // Clear the total cell
                const totalCell = newRow.querySelector('.total-cell');
        if (totalCell) {
            totalCell.textContent = '$0.00';
        }
        
        tbody.appendChild(newRow);
        internalParticipantCount++;
        
        updateTotals();
        updateParticipantsSummary();
    });
    
    // Remove internal participant
    document.getElementById('removeInternal').addEventListener('click', function() {
        const tbody = document.getElementById('internalParticipants');
        if (tbody.rows.length > 1) {
            tbody.deleteRow(tbody.rows.length - 1);
            internalParticipantCount--;
            updateTotals();
            updateParticipantsSummary();
        }
    });
    
    // Add external participant
    document.getElementById('addExternal').addEventListener('click', function() {
        const tbody = document.getElementById('externalParticipants');
        const newRow = tbody.rows[0].cloneNode(true);
        
        // Update input names and clear values
        const inputs = newRow.querySelectorAll('input');
        inputs.forEach(input => {
            if (input.name) {
                        input.name = input.name.replace('[0]', '[' + externalParticipantCount +
                        ']');
            }
            if (input.type === 'number') {
                input.value = '';
            } else if (input.type === 'text' && input.name.includes('[name]')) {
                input.value = '';
                input.placeholder = 'Name';
            } else if (input.type === 'email') {
                input.value = '';
                input.placeholder = 'Email';
            }
        });
        
        // Clear the total cell
                const totalCell = newRow.querySelector('.total-cell');
        if (totalCell) {
            totalCell.textContent = '$0.00';
        }
        
        tbody.appendChild(newRow);
        externalParticipantCount++;
        updateTotals();
        updateParticipantsSummary();
    });
    
    // Remove external participant
    document.getElementById('removeExternal').addEventListener('click', function() {
        const tbody = document.getElementById('externalParticipants');
        if (tbody.rows.length > 1) {
            tbody.deleteRow(tbody.rows.length - 1);
            externalParticipantCount--;
            updateTotals();
            updateParticipantsSummary();
        }
    });
    
    // Add event listeners for cost inputs
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('cost-input')) {
                    // Validate and format number with thousand separators
                    validateAndFormatNumberInput(e.target);
            updateTotals();
        }
    });
    
    // Add event listeners for participant changes
    document.addEventListener('change', function(e) {
                if (e.target.matches(
                        'select[name*="[staff_id]"], input[name*="[name]"], input[name*="[email]"]')) {
            updateParticipantsSummary();
        }
    });
    
            // Function to validate and format number input with thousand separators
            function validateAndFormatNumberInput(input) {
        let value = input.value.replace(/,/g, ''); // Remove existing commas
                
                // Allow only numbers and decimal point
                value = value.replace(/[^0-9.]/g, '');
                
                // Ensure only one decimal point
                const parts = value.split('.');
                if (parts.length > 2) {
                    value = parts[0] + '.' + parts.slice(1).join('');
                }
                
                // Limit decimal places to 2
                if (parts.length === 2 && parts[1].length > 2) {
                    value = parts[0] + '.' + parts[1].substring(0, 2);
                }
                
        if (value && !isNaN(value)) {
            let number = parseFloat(value);
            if (number >= 0) {
                        // Format with thousand separators
                input.value = number.toLocaleString('en-US', {
                    minimumFractionDigits: 0,
                            maximumFractionDigits: 2,
                            useGrouping: true
                });
            }
                } else if (value === '') {
                    input.value = '';
        }
    }
    
    // Update participants summary
    function updateParticipantsSummary() {
        const participantsSummary = document.getElementById('participantsSummary');
        participantsSummary.innerHTML = '';
        
        let participantCount = 0;
        
        // Add internal participants
        const internalRows = document.querySelectorAll('#internalParticipants tr');
        internalRows.forEach(row => {
            const select = row.querySelector('select[name*="[staff_id]"]');
            if (select && select.value) {
                participantCount++;
                const selectedOption = select.options[select.selectedIndex];
                const participantName = selectedOption.text;
                const participantId = selectedOption.value;
                
                const newRow = document.createElement('tr');
                newRow.innerHTML = `
                    <td class="text-center">${participantCount}</td>
                    <td>${participantName}</td>
                    <td><span class="badge bg-primary">Internal</span></td>
                    <td>Staff Member</td>
                    <td>Participant</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeParticipant(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                participantsSummary.appendChild(newRow);
            }
        });
        
        // Add external participants
        const externalRows = document.querySelectorAll('#externalParticipants tr');
        externalRows.forEach(row => {
            const nameInput = row.querySelector('input[name*="[name]"]');
            const emailInput = row.querySelector('input[name*="[email]"]');
            
            if (nameInput && nameInput.value.trim()) {
                participantCount++;
                const participantName = nameInput.value.trim();
                const participantEmail = emailInput ? emailInput.value.trim() : '';
                
                const newRow = document.createElement('tr');
                newRow.innerHTML = `
                    <td class="text-center">${participantCount}</td>
                    <td>${participantName}</td>
                    <td><span class="badge bg-warning text-dark">External</span></td>
                    <td>${participantEmail || 'N/A'}</td>
                    <td>Participant</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeParticipant(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                participantsSummary.appendChild(newRow);
            }
        });
        
        // Show message if no participants
        if (participantCount === 0) {
            participantsSummary.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-muted py-3">
                        <i class="fas fa-info-circle me-2"></i>No participants added yet. Add participants in the cost sections above.
                    </td>
                </tr>
            `;
        }
    }
    
    // Function to remove participant (placeholder for future functionality)
            window.removeParticipant = function(button) {
        // This could be enhanced to actually remove the participant from the cost sections
        alert('To remove a participant, please use the remove buttons in the cost sections above.');
    }
    
    // Update totals function
    function updateTotals() {
        let internalTotal = 0;
        let externalTotal = 0;
        let otherTotal = 0;
        
        // Calculate internal participants total
        const internalRows = document.querySelectorAll('#internalParticipants tr');
        internalRows.forEach(row => {
            let rowTotal = 0;
            
            // Calculate total for each cost item
            const costInputs = row.querySelectorAll('input[name*="[costs]"]');
            costInputs.forEach(input => {
                const value = parseFloat(input.value.replace(/,/g, '') || 0);
                rowTotal += value;
            });
            
                    const totalCell = row.querySelector('.total-cell');
            if (totalCell) {
                totalCell.textContent = '$' + rowTotal.toFixed(2);
            }
            
            internalTotal += rowTotal;
        });
        
        // Calculate external participants total
        const externalRows = document.querySelectorAll('#externalParticipants tr');
        externalRows.forEach(row => {
            let rowTotal = 0;
            
            // Calculate total for each cost item
            const costInputs = row.querySelectorAll('input[name*="[costs]"]');
            costInputs.forEach(input => {
                const value = parseFloat(input.value.replace(/,/g, '') || 0);
                rowTotal += value;
            });
            
                    const totalCell = row.querySelector('.total-cell');
            if (totalCell) {
                totalCell.textContent = '$' + rowTotal.toFixed(2);
            }
            
            externalTotal += rowTotal;
        });
        
        // Calculate other costs total
        const otherRows = document.querySelectorAll('#otherCosts tr');
        otherRows.forEach(row => {
            // Skip empty rows (like the "No items available" message)
            if (row.querySelector('td[colspan]')) {
                return;
            }
            
                    const unitCost = parseFloat(row.querySelector('input[name*="[unit_cost]"]')?.value
                        .replace(/,/g, '') || 0);
                    const days = parseFloat(row.querySelector('input[name*="[days]"]')?.value.replace(/,/g,
                        '') || 0);
            const total = unitCost * days;
            
                    const totalCell = row.querySelector('.total-cell');
            if (totalCell) {
                totalCell.textContent = '$' + total.toFixed(2);
            }
            
            otherTotal += total;
        });
        
        // Update subtotals
        document.getElementById('internalSubtotal').textContent = '$' + internalTotal.toFixed(2);
        document.getElementById('externalSubtotal').textContent = '$' + externalTotal.toFixed(2);
        document.getElementById('otherSubtotal').textContent = '$' + otherTotal.toFixed(2);
        
        // Calculate new total
        const newTotal = internalTotal + externalTotal + otherTotal;
                const originalTotal = parseFloat(document.getElementById('originalBudgetAmount').textContent
                    .replace('$', '').replace(/,/g, ''));
        const difference = newTotal - originalTotal;
        
        // Update budget summary
        document.getElementById('newBudgetAmount').textContent = '$' + newTotal.toFixed(2);
        const differenceElement = document.getElementById('budgetDifference');
                differenceElement.textContent = (difference >= 0 ? '+' : '') + '$' + Math.abs(difference).toFixed(2);
        
        // Update colors based on difference
        if (difference < 0) {
                    differenceElement.className = 'text-success mb-0';
        } else if (difference > 0) {
                    differenceElement.className = 'text-danger mb-0';
        } else {
                    differenceElement.className = 'text-warning mb-0';
        }
        
        // Update hidden fields
        document.getElementById('newTotalBudget').value = newTotal;
        document.getElementById('originalTotalBudget').value = originalTotal;
    }
    
    // Add event listeners to all inputs
    document.addEventListener('input', function(e) {
        if (e.target.matches('input[type="number"], select, textarea')) {
            updateTotals();
        }
    });
    
    // Initial calculation
    updateTotals();
    updateParticipantsSummary();
    });
</script>
@endsection