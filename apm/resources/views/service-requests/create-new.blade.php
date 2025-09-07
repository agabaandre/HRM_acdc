@extends('layouts.app')

@section('title', 'Create Service Request')

@section('content')
<div class="container-fluid p-0">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bx bx-file me-1"></i> Create Service Request
                    </h6>
                    <a href="{{ route('service-requests.index') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bx bx-arrow-back me-1"></i> Back to List
                    </a>
                </div>
                <div class="card-body">
                    <form action="{{ route('service-requests.store') }}" method="POST" enctype="multipart/form-data" id="serviceRequestForm">
                        @csrf
                        
                        <!-- Hidden fields for source data -->
                        <input type="hidden" name="source_type" value="{{ $sourceType }}">
                        <input type="hidden" name="source_id" value="{{ $sourceId }}">
                        <input type="hidden" name="model_type" value="{{ $sourceType ? 'App\\Models\\' . ucfirst(str_replace('_', '', $sourceType)) : '' }}">
                        <input type="hidden" name="fund_type_id" value="{{ $sourceData->fund_type_id ?? 1 }}">
                        <input type="hidden" name="responsible_person_id" value="{{ $sourceData->staff_id ?? auth()->user()->staff_id }}">
                        <input type="hidden" name="budget_id" value="{{ json_encode($sourceData->budget_id ?? []) }}">
                        <input type="hidden" name="original_total_budget" id="originalTotalBudget" value="0">
                        <input type="hidden" name="new_total_budget" id="newTotalBudget" value="0">
                        <input type="hidden" name="budget_breakdown" id="budgetBreakdown" value="">
                        <input type="hidden" name="internal_participants_cost" id="internalParticipantsCost" value="">
                        <input type="hidden" name="external_participants_cost" id="externalParticipantsCost" value="">
                        <input type="hidden" name="other_costs" id="otherCosts" value="">

                        <div class="budget-form">
                            <h1 class="form-title">Budget Request Form</h1>
                            
                            <!-- Basic Request Information -->
                            <div class="section">
                                <h2 class="section-title"><i class="fas fa-info-circle"></i> Request Information</h2>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Request Number</label>
                                            <input type="text" name="request_number" class="form-control" value="{{ $requestNumber }}" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Request Date</label>
                                            <input type="date" name="request_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Required By Date</label>
                                            <input type="date" name="required_by_date" class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Service Title</label>
                                            <input type="text" name="service_title" class="form-control" value="{{ $sourceData->title ?? $sourceData->activity_title ?? '' }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Location</label>
                                            <input type="text" name="location" class="form-control" placeholder="Where is this service needed?">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="3" required>{{ $sourceData->description ?? $sourceData->background ?? '' }}</textarea>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Justification</label>
                                    <textarea name="justification" class="form-control" rows="3" required>{{ $sourceData->justification ?? $sourceData->activity_request_remarks ?? '' }}</textarea>
                                </div>
                            </div>

                            <!-- Original Budget Breakdown -->
                            <div class="section">
                                <h2 class="section-title"><i class="fas fa-file-invoice-dollar"></i> Original Budget Breakdown</h2>
                                <table class="budget-table">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Item</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody id="originalBudgetTable">
                                        @if($sourceData && isset($sourceData->budget_breakdown))
                                            @php
                                                $budgetBreakdown = is_string($sourceData->budget_breakdown) 
                                                    ? json_decode($sourceData->budget_breakdown, true) 
                                                    : $sourceData->budget_breakdown;
                                                $totalOriginal = 0;
                                            @endphp
                                            @if(is_array($budgetBreakdown) && !empty($budgetBreakdown))
                                                @foreach($budgetBreakdown as $fundCodeId => $items)
                                                    @if($fundCodeId !== 'grand_total' && is_array($items))
                                                        @foreach($items as $item)
                                                            @php
                                                                $amount = ($item['unit_cost'] ?? 0) * ($item['units'] ?? 0) * ($item['days'] ?? 1);
                                                                $totalOriginal += $amount;
                                                            @endphp
                                                            <tr>
                                                                <td>Fund Code {{ $fundCodeId }}</td>
                                                                <td>{{ $item['cost'] ?? $item['description'] ?? 'N/A' }}</td>
                                                                <td>${{ number_format($amount, 2) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    @endif
                                                @endforeach
                                                @if(isset($budgetBreakdown['grand_total']))
                                                    @php $totalOriginal = $budgetBreakdown['grand_total']; @endphp
                                                @endif
                                            @endif
                                        @endif
                                        <tr>
                                            <td colspan="2" style="text-align: right; font-weight: bold;">Total Budget:</td>
                                            <td style="font-weight: bold;">${{ number_format($totalOriginal, 2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Individual Costs (Internal Participants) -->
                            <div class="section">
                                <h2 class="section-title"><i class="fas fa-users"></i> Individual Costs (Internal Participants)</h2>
                                <div class="participant-controls">
                                    <button type="button" class="btn btn-sm btn-primary" id="addInternal">
                                        <i class="fas fa-plus"></i> Add Participant
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="removeInternal">
                                        <i class="fas fa-minus"></i> Remove Participant
                                    </button>
                                </div>
                                <table class="budget-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Daily Rate</th>
                                            <th>Days</th>
                                            <th>Expenses</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="internalParticipants">
                                        <tr>
                                            <td>
                                                <select name="internal_participants[0][staff_id]" class="form-control">
                                                    <option value="">Select Staff Member</option>
                                                    @foreach($staff as $member)
                                                        <option value="{{ $member->staff_id }}">
                                                            {{ $member->fname }} {{ $member->lname }} ({{ $member->position ?? 'Staff' }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td><input type="number" name="internal_participants[0][daily_rate]" class="form-control" value="250" step="0.01"></td>
                                            <td><input type="number" name="internal_participants[0][days]" class="form-control" value="5" min="1"></td>
                                            <td><input type="number" name="internal_participants[0][expenses]" class="form-control" value="200" step="0.01"></td>
                                            <td><span class="readonly">$1,450.00</span></td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4" style="text-align: right; font-weight: bold;">Subtotal:</td>
                                            <td style="font-weight: bold;" id="internalSubtotal">$1,450.00</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Individual Costs (External Participants) -->
                            <div class="section">
                                <h2 class="section-title"><i class="fas fa-user-friends"></i> Individual Costs (External Participants)</h2>
                                <div class="participant-controls">
                                    <button type="button" class="btn btn-sm btn-primary" id="addExternal">
                                        <i class="fas fa-plus"></i> Add Participant
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="removeExternal">
                                        <i class="fas fa-minus"></i> Remove Participant
                                    </button>
                                </div>
                                <table class="budget-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Daily Rate</th>
                                            <th>Days</th>
                                            <th>Expenses</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="externalParticipants">
                                        <tr>
                                            <td><input type="text" name="external_participants[0][name]" class="form-control" value="Consulting Experts Inc."></td>
                                            <td><input type="email" name="external_participants[0][email]" class="form-control" value="contact@experts.com"></td>
                                            <td><input type="number" name="external_participants[0][daily_rate]" class="form-control" value="350" step="0.01"></td>
                                            <td><input type="number" name="external_participants[0][days]" class="form-control" value="3" min="1"></td>
                                            <td><input type="number" name="external_participants[0][expenses]" class="form-control" value="300" step="0.01"></td>
                                            <td><span class="readonly">$1,350.00</span></td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="5" style="text-align: right; font-weight: bold;">Subtotal:</td>
                                            <td style="font-weight: bold;" id="externalSubtotal">$1,350.00</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Other Costs -->
                            <div class="section">
                                <h2 class="section-title"><i class="fas fa-receipt"></i> Other Costs</h2>
                                <table class="budget-table">
                                    <thead>
                                        <tr class="cost-item-header">
                                            <th>Cost Type</th>
                                            <th>Unit Cost</th>
                                            <th>No. of Days</th>
                                            <th>Description</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="otherCosts">
                                        @php
                                            $otherCostItems = \App\Models\CostItem::where('cost_type', 'other_cost')->get();
                                        @endphp
                                        @foreach($otherCostItems as $index => $costItem)
                                            <tr>
                                                <td>
                                                    <select name="other_costs[{{ $index }}][cost_type]" class="form-control">
                                                        <option value="{{ $costItem->name }}">{{ $costItem->name }}</option>
                                                        @foreach($otherCostItems as $item)
                                                            @if($item->id != $costItem->id)
                                                                <option value="{{ $item->name }}">{{ $item->name }}</option>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td><input type="number" name="other_costs[{{ $index }}][unit_cost]" class="form-control" value="120" step="0.01"></td>
                                                <td><input type="number" name="other_costs[{{ $index }}][days]" class="form-control" value="10" min="1"></td>
                                                <td><textarea name="other_costs[{{ $index }}][description]" class="form-control" rows="2">Projector and audio equipment</textarea></td>
                                                <td><span class="readonly">$1,200.00</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4" style="text-align: right; font-weight: bold;">Subtotal:</td>
                                            <td style="font-weight: bold;" id="otherSubtotal">$1,200.00</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Budget Summary -->
                            <div class="budget-summary">
                                <div class="budget-box">
                                    <div class="budget-title">Original Budget</div>
                                    <div class="budget-amount" id="originalBudgetAmount">${{ number_format($totalOriginal, 2) }}</div>
                                </div>
                                <div class="budget-box">
                                    <div class="budget-title">New Budget</div>
                                    <div class="budget-amount" id="newBudgetAmount">$4,000.00</div>
                                </div>
                                <div class="budget-box">
                                    <div class="budget-title">Budget Difference</div>
                                    <div class="budget-amount" id="budgetDifference">-$6,500.00</div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="action-buttons">
                                <a href="{{ route('service-requests.index') }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Submit Request
                                </button>
                            </div>

                            <!-- Footer -->
                            <footer>
                                <p>© 2025 AC/DC Budget Management System | Workflow ID: 3 | Fund Type: {{ $sourceData->fund_type_id ?? 1 }} | Status: Approved</p>
                            </footer>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.budget-form {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.form-title {
    text-align: center;
    color: #2c3e50;
    margin-bottom: 30px;
    font-size: 2.5rem;
    font-weight: 300;
}

.section {
    margin-bottom: 40px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.section-title {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    margin: 0;
    padding: 20px;
    font-size: 1.3rem;
    font-weight: 500;
}

.section-title i {
    margin-right: 10px;
}

.participant-controls {
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.participant-controls .btn {
    margin-right: 10px;
}

.budget-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.budget-table th,
.budget-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #dee2e6;
}

.budget-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.budget-table tbody tr:hover {
    background: #f8f9fa;
}

.budget-table tfoot tr {
    background: #e9ecef;
    font-weight: bold;
}

.readonly {
    color: #6c757d;
    font-weight: 500;
}

.budget-summary {
    display: flex;
    justify-content: space-around;
    margin: 30px 0;
    gap: 20px;
}

.budget-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    flex: 1;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.budget-title {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-bottom: 5px;
}

.budget-amount {
    font-size: 1.8rem;
    font-weight: bold;
}

.budget-amount.positive {
    color: #28a745;
}

.budget-amount.negative {
    color: #dc3545;
}

.action-buttons {
    text-align: center;
    margin: 30px 0;
}

.action-buttons .btn {
    margin: 0 10px;
    padding: 12px 30px;
    font-size: 1.1rem;
}

footer {
    text-align: center;
    margin-top: 40px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    color: #6c757d;
}

footer p {
    margin: 0;
    font-size: 0.9rem;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 5px;
}

.form-control {
    border: 1px solid #ced4da;
    border-radius: 4px;
    padding: 8px 12px;
    font-size: 0.9rem;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.btn {
    border-radius: 4px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
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
                input.name = input.name.replace('[0]', '[' + internalParticipantCount + ']');
            }
            if (input.type === 'number' || input.type === 'text' || input.type === 'email') {
                input.value = '';
            } else if (input.tagName === 'SELECT') {
                input.selectedIndex = 0;
            }
        });
        
        tbody.appendChild(newRow);
        internalParticipantCount++;
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
            input.value = '';
        });
        
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
    
    // Update totals function
    function updateTotals() {
        let internalTotal = 0;
        let externalTotal = 0;
        let otherTotal = 0;
        
        // Calculate internal participants total
        const internalRows = document.querySelectorAll('#internalParticipants tr');
        internalRows.forEach(row => {
            const dailyRate = parseFloat(row.querySelector('input[name*="[daily_rate]"]')?.value || 0);
            const days = parseFloat(row.querySelector('input[name*="[days]"]')?.value || 0);
            const expenses = parseFloat(row.querySelector('input[name*="[expenses]"]')?.value || 0);
            const total = (dailyRate * days) + expenses;
            
            const totalCell = row.querySelector('.readonly');
            if (totalCell) {
                totalCell.textContent = '$' + total.toFixed(2);
            }
            
            internalTotal += total;
        });
        
        // Calculate external participants total
        const externalRows = document.querySelectorAll('#externalParticipants tr');
        externalRows.forEach(row => {
            const dailyRate = parseFloat(row.querySelector('input[name*="[daily_rate]"]')?.value || 0);
            const days = parseFloat(row.querySelector('input[name*="[days]"]')?.value || 0);
            const expenses = parseFloat(row.querySelector('input[name*="[expenses]"]')?.value || 0);
            const total = (dailyRate * days) + expenses;
            
            const totalCell = row.querySelector('.readonly');
            if (totalCell) {
                totalCell.textContent = '$' + total.toFixed(2);
            }
            
            externalTotal += total;
        });
        
        // Calculate other costs total
        const otherRows = document.querySelectorAll('#otherCosts tr');
        otherRows.forEach(row => {
            const unitCost = parseFloat(row.querySelector('input[name*="[unit_cost]"]')?.value || 0);
            const days = parseFloat(row.querySelector('input[name*="[days]"]')?.value || 0);
            const total = unitCost * days;
            
            const totalCell = row.querySelector('.readonly');
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
        const originalTotal = parseFloat(document.getElementById('originalBudgetAmount').textContent.replace('$', '').replace(',', ''));
        const difference = newTotal - originalTotal;
        
        // Update budget summary
        document.getElementById('newBudgetAmount').textContent = '$' + newTotal.toFixed(2);
        const differenceElement = document.getElementById('budgetDifference');
        differenceElement.textContent = '$' + difference.toFixed(2);
        differenceElement.className = 'budget-amount ' + (difference < 0 ? 'negative' : 'positive');
        
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
