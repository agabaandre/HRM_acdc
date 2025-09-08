@extends('layouts.app')

@section('title', 'Create Service Request')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endpush
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
    
    // Process source data if available
    if ($sourceData && isset($sourceData->budget_breakdown)) {
        $budgetBreakdown = is_string($sourceData->budget_breakdown) 
            ? json_decode($sourceData->budget_breakdown, true) 
            : $sourceData->budget_breakdown;
            
        if (is_array($budgetBreakdown) && !empty($budgetBreakdown)) {
            // Calculate total from individual items
            foreach ($budgetBreakdown as $fundCodeId => $items) {
                if ($fundCodeId !== 'grand_total' && is_array($items)) {
                    foreach ($items as $item) {
                        $amount = ($item['unit_cost'] ?? 0) * ($item['units'] ?? 0) * ($item['days'] ?? 1);
                        $totalOriginal += $amount;
                    }
                }
            }
            
            // Use grand_total if available
            if (isset($budgetBreakdown['grand_total'])) {
                $totalOriginal = $budgetBreakdown['grand_total'];
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
                    <form action="{{ route('service-requests.store') }}" method="POST" enctype="multipart/form-data" id="serviceRequestForm">
                        @csrf
            
            <!-- Hidden fields for source data -->
            <input type="hidden" name="source_type" value="{{ $sourceType }}">
            <input type="hidden" name="source_id" value="{{ $sourceId }}">
            <input type="hidden" name="model_type" value="{{ $sourceType ? 'App\\Models\\' . ucfirst(str_replace('_', '', $sourceType)) : '' }}">
            <input type="hidden" name="fund_type_id" value="{{ $sourceData ? $sourceData->fund_type_id : 1 }}">
            <input type="hidden" name="responsible_person_id" value="{{ $sourceData ? $sourceData->staff_id : (auth()->check() ? auth()->user()->staff_id : 1) }}">
            <input type="hidden" name="budget_id" value="{{ json_encode($sourceData ? $sourceData->budget_id : []) }}">
            <input type="hidden" name="original_total_budget" id="originalTotalBudget" value="{{ $totalOriginal ?? 0 }}">
            <input type="hidden" name="new_total_budget" id="newTotalBudget" value="0">
            <input type="hidden" name="budget_breakdown" id="budgetBreakdown" value="">
            <input type="hidden" name="internal_participants_cost" id="internalParticipantsCost" value="">
            <input type="hidden" name="external_participants_cost" id="externalParticipantsCost" value="">
            <input type="hidden" name="other_costs" id="otherCosts" value="">
            
            <!-- Additional required fields -->
            <input type="hidden" name="request_date" value="{{ date('Y-m-d') }}">
            <input type="hidden" name="required_by_date" value="{{ date('Y-m-d', strtotime('+30 days')) }}">
            <input type="hidden" name="location" value="{{ $sourceData ? ($sourceData->location ?? 'N/A') : 'N/A' }}">
            <input type="hidden" name="priority" value="medium">
            <input type="hidden" name="service_type" value="other">
            <input type="hidden" name="status" value="draft">
            <input type="hidden" name="service_title" value="{{ $sourceData ? ($sourceData->title ?? $sourceData->activity_title) : 'Service Request' }}">
            <input type="hidden" name="description" value="{{ $sourceData ? ($sourceData->description ?? $sourceData->background) : 'Service Request Description' }}">
            <input type="hidden" name="justification" value="{{ $sourceData ? ($sourceData->justification ?? $sourceData->activity_request_remarks) : 'Service Request Justification' }}">

            <!-- Section 1: Request Information -->
            <div class="mb-5">
                <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                    <i class="fas fa-info-circle me-2"></i> Request Information
                </h6>
                        
                        <div class="row g-4">
                    <!-- Service Request Number -->
                    <div class="col-md-12">
                                                <div class="form-group">
                            <label class="form-label fw-semibold">
                                <i class="bx bx-hash me-1 text-success"></i> Service Request Number
                            </label>
                            <input type="text" name="request_number" class="form-control border-success" value="{{ $requestNumber }}" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
            <!-- Section 2: Original Budget Breakdown -->
            <div class="mb-5">
                <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                    <i class="fas fa-file-invoice-dollar me-2"></i> Original Budget Breakdown
                </h6>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-success">
                            <tr>
                                <th>Category</th>
                                <th>Item</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                        
                            @if($budgetBreakdown && is_array($budgetBreakdown) && !empty($budgetBreakdown))
                                @foreach($budgetBreakdown as $fundCodeId => $items)
                                    @if($fundCodeId !== 'grand_total' && is_array($items))
                                        @foreach($items as $item)
                                            @php
                                                $amount = ($item['unit_cost'] ?? 0) * ($item['units'] ?? 0) * ($item['days'] ?? 1);
                                            @endphp
                                            <tr>
                                                <td>Fund Code {{ $fundCodeId }}</td>
                                                <td>{{ $item['cost'] ?? $item['description'] ?? 'N/A' }}</td>
                                                <td class="text-end">${{ number_format($amount, 2) }}</td>
                                            </tr>
                                                @endforeach
                                            @endif
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="3" class="text-center text-muted">
                                        <i class="fas fa-info-circle me-2"></i>No budget data available from source
                                    </td>
                                </tr>
                            @endif
                            <tr class="table-success fw-bold">
                                <td colspan="2" class="text-end">Total Budget:</td>
                                <td class="text-end">${{ number_format($totalOriginal, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                                    </div>
                                </div>
                                
            <!-- Section 3: Individual Costs (Internal Participants) -->
            <div class="mb-5">
                <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                    <i class="fas fa-users me-2"></i> Individual Costs (Internal Participants)
                </h6>
                
                <div class="d-flex gap-2 mb-3">
                    <button type="button" class="btn btn-success btn-sm" id="addInternal">
                        <i class="fas fa-plus me-1"></i> Add Participant
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="removeInternal">
                        <i class="fas fa-minus me-1"></i> Remove Participant
                    </button>
                            </div>
                            
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-success">
                            <tr>
                                <th style="width: 25%;">Name</th>
                                @foreach($costItems as $costItem)
                                    <th style="width: {{ 75 / count($costItems) }}%;">{{ $costItem->name }}</th>
                                                        @endforeach
                                <th style="width: 25%;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="internalParticipants">
                            <tr>
                                <td>
                                    <select name="internal_participants[0][staff_id]" class="form-select border-success participant-select" style="width: 100%;">
                                        <option value="">Select Participant</option>
                                        @if(!empty($participantNames))
                                            @foreach($participantNames as $participant)
                                                <option value="{{ $participant['id'] }}">
                                                    {{ $participant['text'] }}
                                                </option>
                                            @endforeach
                                        @else
                                            @foreach($staff as $member)
                                                <option value="{{ $member->staff_id }}">
                                                    {{ $member->fname }} {{ $member->lname }} ({{ $member->position ?? 'Staff' }})
                                                            </option>
                                                        @endforeach
                                        @endif
                                                    </select>
                                </td>
                                @foreach($costItems as $index => $costItem)
                                    <td>
                                        <input type="number" 
                                               name="internal_participants[0][costs][{{ $costItem->id }}]" 
                                               class="form-control border-success cost-input" 
                                               value="0" 
                                               step="0.01" 
                                               data-cost-item="{{ $costItem->name }}"
                                               placeholder="Enter {{ $costItem->name }}">
                                    </td>
                                                        @endforeach
                                <td class="text-end fw-bold">$0.00</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-success">
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
                <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                    <i class="fas fa-user-friends me-2"></i> Individual Costs (External Participants)
                </h6>
                
                <div class="d-flex gap-2 mb-3">
                    <button type="button" class="btn btn-success btn-sm" id="addExternal">
                        <i class="fas fa-plus me-1"></i> Add Participant
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="removeExternal">
                        <i class="fas fa-minus me-1"></i> Remove Participant
                    </button>
                                        </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-success">
                            <tr>
                                <th style="width: 13%;">Name</th>
                                <th style="width: 13%;">Email</th>
                                @foreach($costItems as $costItem)
                                    <th style="width: {{ 74 / count($costItems) }}%;">{{ $costItem->name }}</th>
                                @endforeach
                                <th style="width: 13%;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="externalParticipants">
                            <tr>
                                <td><input type="text" name="external_participants[0][name]" class="form-control border-success" value="Consulting Experts Inc."></td>
                                <td><input type="email" name="external_participants[0][email]" class="form-control border-success" value="contact@experts.com"></td>
                                @foreach($costItems as $index => $costItem)
                                    <td>
                                        <input type="number" 
                                               name="external_participants[0][costs][{{ $costItem->id }}]" 
                                               class="form-control border-success cost-input" 
                                               value="0" 
                                               step="0.01" 
                                               data-cost-item="{{ $costItem->name }}"
                                               placeholder="Enter {{ $costItem->name }}">
                                    </td>
                                @endforeach
                                <td class="text-end fw-bold">$0.00</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-success">
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
                        <thead class="table-success">
                            <tr>
                                <th>Cost Type</th>
                                <th>Unit Cost</th>
                                <th>No. of Days</th>
                                <th>Description</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="otherCosts">
                            @if($otherCostItems->isNotEmpty())
                                @foreach($otherCostItems as $index => $costItem)
                                    <tr>
                                        <td>
                                            <select name="other_costs[{{ $index }}][cost_type]" class="form-select border-success">
                                                <option value="{{ $costItem->name }}">{{ $costItem->name }}</option>
                                                @foreach($otherCostItems as $item)
                                                    @if($item->id != $costItem->id)
                                                        <option value="{{ $item->name }}">{{ $item->name }}</option>
                                                    @endif
                                                        @endforeach
                                                    </select>
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   name="other_costs[{{ $index }}][unit_cost]" 
                                                   class="form-control border-success cost-input" 
                                                   value="0" 
                                                   step="0.01" 
                                                   placeholder="Enter unit cost">
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   name="other_costs[{{ $index }}][days]" 
                                                   class="form-control border-success" 
                                                   value="1" 
                                                   min="1" 
                                                   placeholder="Enter days">
                                        </td>
                                        <td>
                                            <textarea name="other_costs[{{ $index }}][description]" 
                                                      class="form-control border-success" 
                                                      rows="2" 
                                                      placeholder="Enter description"></textarea>
                                        </td>
                                        <td class="text-end fw-bold">$0.00</td>
                                    </tr>
                                                        @endforeach
                            @else
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No Other Cost items available</td>
                                </tr>
                            @endif
                        </tbody>
                        <tfoot class="table-success">
                            <tr>
                                <td colspan="4" class="text-end fw-bold">Subtotal:</td>
                                <td class="text-end fw-bold" id="otherSubtotal">$0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                                    </div>
                                </div>
                                
            <!-- Section 6: Budget Summary -->
            <div class="mb-5">
                <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                    <i class="fas fa-calculator me-2"></i> Budget Summary
                </h6>
                
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <h6 class="card-title text-success">Original Budget</h6>
                                <h4 class="text-success" id="originalBudgetAmount">${{ number_format($totalOriginal, 2) }}</h4>
                                    </div>
                                                </div>
                                            </div>
                    <div class="col-md-4">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <h6 class="card-title text-primary">New Budget</h6>
                                <h4 class="text-primary" id="newBudgetAmount">$4,000.00</h4>
                                                    </div>
                                                </div>
                                            </div>
                    <div class="col-md-4">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <h6 class="card-title text-warning">Budget Difference</h6>
                                <h4 class="text-warning" id="budgetDifference">-$6,500.00</h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
            <!-- Action Buttons -->
            <div class="d-flex justify-content-end gap-3">
                                    <a href="{{ route('service-requests.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-paper-plane me-1"></i> Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

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
                input.name = input.name.replace('[0]', '[' + internalParticipantCount + ']');
            }
            if (input.type === 'number' || input.type === 'text' || input.type === 'email') {
                input.value = '';
            } else if (input.tagName === 'SELECT') {
                input.selectedIndex = 0;
            }
        });
        
        // Clear the total cell
        const totalCell = newRow.querySelector('td:last-child');
        if (totalCell) {
            totalCell.textContent = '$0.00';
        }
        
        tbody.appendChild(newRow);
        internalParticipantCount++;
        
        // Initialize Select2 for the new row
        initializeSelect2();
        
        updateTotals();
    });
    
    // Remove internal participant
    document.getElementById('removeInternal').addEventListener('click', function() {
        const tbody = document.getElementById('internalParticipants');
        if (tbody.rows.length > 1) {
            tbody.deleteRow(tbody.rows.length - 1);
            internalParticipantCount--;
            updateTotals();
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
                input.name = input.name.replace('[0]', '[' + externalParticipantCount + ']');
            }
            if (input.type === 'number' || input.type === 'text' || input.type === 'email') {
                input.value = '';
            }
        });
        
        // Clear the total cell
        const totalCell = newRow.querySelector('td:last-child');
        if (totalCell) {
            totalCell.textContent = '$0.00';
        }
        
        tbody.appendChild(newRow);
        externalParticipantCount++;
        updateTotals();
    });
    
    // Remove external participant
    document.getElementById('removeExternal').addEventListener('click', function() {
        const tbody = document.getElementById('externalParticipants');
        if (tbody.rows.length > 1) {
            tbody.deleteRow(tbody.rows.length - 1);
            externalParticipantCount--;
            updateTotals();
        }
    });
    
    // Initialize Select2 for participant dropdowns
    function initializeSelect2() {
        $('.participant-select').select2({
            placeholder: 'Select Participant',
            allowClear: true,
            width: '100%'
        });
    }
    
    // Initialize Select2 on page load
    initializeSelect2();
    
    // Add event listeners for cost inputs
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('cost-input')) {
            // Format number with thousand separators
            formatNumberInput(e.target);
            updateTotals();
        }
    });
    
    // Function to format number input with thousand separators
    function formatNumberInput(input) {
        let value = input.value.replace(/,/g, ''); // Remove existing commas
        if (value && !isNaN(value)) {
            let number = parseFloat(value);
            if (number >= 0) {
                input.value = number.toLocaleString('en-US', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 2
                });
            }
        }
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
            
            const totalCell = row.querySelector('td:last-child');
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
            
            const totalCell = row.querySelector('td:last-child');
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
            
            const unitCost = parseFloat(row.querySelector('input[name*="[unit_cost]"]')?.value.replace(/,/g, '') || 0);
            const days = parseFloat(row.querySelector('input[name*="[days]"]')?.value.replace(/,/g, '') || 0);
            const total = unitCost * days;
            
            const totalCell = row.querySelector('td:last-child');
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
        const originalTotal = parseFloat(document.getElementById('originalBudgetAmount').textContent.replace('$', '').replace(/,/g, ''));
        const difference = newTotal - originalTotal;
        
        // Update budget summary
        document.getElementById('newBudgetAmount').textContent = '$' + newTotal.toFixed(2);
        const differenceElement = document.getElementById('budgetDifference');
        differenceElement.textContent = '$' + difference.toFixed(2);
        
        // Update colors based on difference
        if (difference < 0) {
            differenceElement.className = 'text-success';
        } else if (difference > 0) {
            differenceElement.className = 'text-danger';
        } else {
            differenceElement.className = 'text-warning';
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
    });
</script>
@endsection