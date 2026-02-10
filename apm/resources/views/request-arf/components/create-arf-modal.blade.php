
    <style>
        :root {
            --primary-green: #119A48;
            --secondary-maroon: #911C39;
            --light-bg: #f8f9fa;
            --border-radius: 0.5rem;
        }
        
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
            padding: 1rem 1.5rem;
        }
        
        .section-header {
            padding: 0.75rem 1rem;
            background-color: var(--light-bg);
            border-left: 4px solid var(--primary-green);
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        
        .info-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 0.5rem;
        }
        
        .info-table td {
            padding: 0.75rem 1rem;
            vertical-align: top;
        }
        
        .info-table .label-cell {
            width: 25%;
            font-weight: 600;
            color: #6c757d;
            background-color: #f8f9fa;
            border-radius: 4px 0 0 4px;
        }
        
        .info-table .value-cell {
            width: 75%;
            background-color: white;
            border: 1px solid #dee2e6;
            border-radius: 0 4px 4px 0;
        }
        
        .budget-table {
            font-size: 0.9rem;
        }
        
        .budget-table th {
            background-color: var(--light-bg);
            padding: 0.75rem;
            font-weight: 600;
        }
        
        .budget-table td {
            padding: 0.75rem;
            vertical-align: middle;
        }
        
        .fund-header {
            background-color: rgba(145, 28, 57, 0.1);
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 0.5rem;
            border-left: 3px solid var(--secondary-maroon);
        }
        
        .grand-total {
            background-color: rgba(17, 154, 72, 0.1);
            border-left: 3px solid var(--primary-green);
            padding: 1rem;
            border-radius: 4px;
        }
        
        .value-badge {
            font-size: 0.85em;
            padding: 0.35em 0.65em;
        }
        
        .modal-body {
            max-height: calc(90vh - 120px);
            overflow-y: auto;
        }
        
        .budget-table td {
            word-wrap: break-word;
            word-break: break-word;
            white-space: normal;
            overflow-wrap: break-word;
        }
        
        .budget-table th.cost-item,
        .budget-table td.cost-item {
            max-width: 200px;
            min-width: 150px;
        }
        
        .budget-table th.description,
        .budget-table td.description {
            max-width: 250px;
            min-width: 200px;
        }
        /* Non-travel memo: Description column at 50% with proper wrapping (match show view) */
        .budget-table.non-travel-memo td.cost-item,
        .budget-table.non-travel-memo th.cost-item {
            width: 50%;
            max-width: none;
        }
        .budget-table.non-travel-memo td.cost-item > div {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }
        
        /* Custom scrollbar for modal */
        .modal-body::-webkit-scrollbar {
            width: 8px;
        }
        
        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .modal-body::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        
        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>


<!-- Create ARF Modal Component -->
<div class="modal fade" id="createArfModal" tabindex="-1" aria-labelledby="createArfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width: 90vw; width: 90vw;">
        <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header" style="background: var(--primary-green); color: white;">
                <h5 class="modal-title fw-bold text-white" id="createArfModalLabel">
                    <i class="bx bx-file-plus me-2"></i>Create Activity Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" style="flex: 1; overflow-y: auto;">
                <!-- Source Details Section -->
                <div class="mb-4">
                    <div class="section-header">
                        <h6 class="fw-bold mb-0 text-primary">
                            <i class="bx bx-info-circle me-2"></i>{{ $sourceType ?? 'Source' }} Details
                        </h6>
                    </div>
                    
                    <table class="info-table">
                        <tr>
                            <td class="label-cell"><i class="bx bx-hash me-1"></i>{{ $sourceType }} ID</td>
                            <td class="value-cell">{{ $sourceId ?? 'N/A' }}</td>
                            <td class="label-cell"><i class="bx bx-file me-1"></i>ARF Number</td>
                            <td class="value-cell">{{ $arfNumber ?? 'Auto-generated' }}</td>
                        </tr>
                        <tr>
                            <td class="label-cell"><i class="bx bx-buildings me-1"></i>Division</td>
                            <td class="value-cell">{{ $divisionName ?? 'N/A' }}</td>
                            <td class="label-cell"><i class="bx bx-money me-1"></i>Fund Location</td>
                            <td class="value-cell">
                                <span class="badge {{ $fundTypeId == 2 ? 'bg-info' : 'bg-primary' }} value-badge">
                                    {{ $fundTypeName ?? 'N/A' }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-cell"><i class="bx bx-user me-1"></i>Focal Person</td>
                            <td class="value-cell">{{ $focalPerson ?? 'N/A' }}</td>
                            <td class="label-cell"><i class="bx bx-calendar me-1"></i>Activity Date Range</td>
                            <td class="value-cell">
                                {{ $dateFrom ?? 'N/A' }} to {{ $dateTo ?? 'N/A' }}
                                <small class="text-muted d-block">({{ $numberOfDays ?? 'N/A' }} days)</small>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-cell"><i class="bx bx-map me-1"></i>Location/Venue</td>
                            <td class="value-cell" colspan="3">{{ $location ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="label-cell"><i class="bx bx-briefcase me-1"></i>Title of mission/activity</td>
                            <td class="value-cell" colspan="3">{{ $sourceTitle ?? 'N/A' }}</td>
                        </tr>
                        @if($sourceType !== 'Non-Travel Memo')
                        <tr>
                            <td class="label-cell"><i class="bx bx-user-plus me-1"></i>External Participants</td>
                            <td class="value-cell">{{ $externalParticipants ?? 'N/A' }}</td>
                            <td class="label-cell"><i class="bx bx-user-circle me-1"></i>Internal Participants</td>
                            <td class="value-cell">{{ $internalParticipants ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="label-cell"><i class="bx bx-group me-1"></i>Total Participants</td>
                            <td class="value-cell" colspan="3">
                                {{ $totalParticipants ?? 'N/A' }}
                                <small class="text-muted">({{ $internalParticipants ?? 0 }} internal + {{ $externalParticipants ?? 0 }} external)</small>
                            </td>
                        </tr>
                        @endif
                        <tr>
                            <td class="label-cell"><i class="bx bx-target-lock me-1"></i>Key Result Area</td>
                            <td class="value-cell" colspan="3">{{ $keyResultArea ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="label-cell"><i class="bx bx-detail me-1"></i>Background/Context</td>
                            <td class="value-cell" colspan="3">{!! $background ?? 'N/A' !!}</td>
                        </tr>
                    </table>
                </div>

                <!-- Budget Codes Section (matches request-arf show view structure) -->
                <div class="mb-4">
                    <div class="section-header">
                        <h6 class="fw-bold mb-0 text-primary">
                            <i class="bx bx-money me-2"></i>Budget Codes & Allocations
                        </h6>
                    </div>
                    @php
                        $budgetArray = is_array($budgetBreakdown ?? []) ? $budgetBreakdown : [];
                        $fundCodesCollection = $fundCodes ?? collect();
                        $isNonTravelMemo = ($sourceType ?? '') === 'Non-Travel Memo';
                        $grandTotal = 0;
                    @endphp
                    @if($sourceType === 'Activity' && $fundCodesCollection->isNotEmpty())
                        {{-- Activity: same structure as request-arf show – by fund code, with Units/Days/Total --}}
                        @foreach($fundCodesCollection as $fundCodeId => $fundCode)
                            @php
                                $items = $budgetArray[$fundCodeId] ?? [];
                                $items = is_array($items) ? $items : [];
                                $fundTotal = 0;
                            @endphp
                            <div class="mb-4">
                                <div class="fund-header">
                                    <h6 class="fw-bold mb-0" style="color: var(--secondary-maroon);">
                                        {{ $fundCode->activity }} - {{ $fundCode->code }} - ({{ $fundCode->fundType->name ?? 'N/A' }})
                                    </h6>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered budget-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="fw-bold" style="width: 40px;">#</th>
                                                <th class="fw-bold cost-item">Cost Item</th>
                                                <th class="fw-bold text-end" style="width: 100px;">Unit Cost</th>
                                                <th class="fw-bold text-end" style="width: 80px;">Units</th>
                                                <th class="fw-bold text-end" style="width: 80px;">Days</th>
                                                <th class="fw-bold text-end" style="width: 120px;">Total</th>
                                                <th class="fw-bold description">Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php $count = 1; @endphp
                                            @foreach($items as $item)
                                                @php
                                                    $unitCost = floatval($item['unit_cost'] ?? 0);
                                                    $units = floatval($item['units'] ?? 0);
                                                    $days = floatval($item['days'] ?? 0);
                                                    $total = $unitCost * $units * $days;
                                                    $fundTotal += $total;
                                                    $grandTotal += $total;
                                                @endphp
                                                <tr>
                                                    <td>{{ $count++ }}</td>
                                                    <td class="cost-item">{{ $item['cost'] ?? 'N/A' }}</td>
                                                    <td class="text-end">${{ number_format($unitCost, 2) }}</td>
                                                    <td class="text-end">{{ $units }}</td>
                                                    <td class="text-end">{{ $days }}</td>
                                                    <td class="text-end fw-bold">${{ number_format($total, 2) }}</td>
                                                    <td class="description">{{ $item['description'] ?? ($item['cost'] ?? '') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <th colspan="5" class="text-end">Fund Total:</th>
                                                <th class="text-end text-success">${{ number_format($fundTotal, 2) }}</th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                        <div class="grand-total text-end">
                            <h5 class="fw-bold mb-0">
                                <i class="bx bx-dollar me-2"></i>
                                Grand Total: ${{ number_format($grandTotal, 2) }}
                            </h5>
                        </div>
                    @elseif($sourceType !== 'Activity' && !empty($budgetArray))
                        {{-- Non-Travel Memo / Special Memo: fund-code keyed breakdown, Non-Travel uses Description column only --}}
                        @foreach($budgetArray as $fundCodeId => $items)
                            @if($fundCodeId !== 'grand_total' && is_array($items))
                                @php
                                    $fundCode = $fundCodesCollection[$fundCodeId] ?? null;
                                    $fundTotal = 0;
                                @endphp
                                <div class="mb-4">
                                    <div class="fund-header">
                                        <h6 class="fw-bold mb-0" style="color: var(--secondary-maroon);">
                                            @if($fundCode)
                                                {{ $fundCode->activity }} - {{ $fundCode->code }} - ({{ $fundCode->fundType->name ?? 'N/A' }})@if($fundCode->funder ?? null) - {{ $fundCode->funder->name }}@endif
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
                                                    <th class="fw-bold cost-item">{{ $isNonTravelMemo ? 'Description' : 'Cost Item' }}</th>
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
                                                    <th colspan="{{ $isNonTravelMemo ? '4' : '5' }}" class="text-end">Fund Total:</th>
                                                    <th class="text-end text-success">${{ number_format($fundTotal, 2) }}</th>
                                                    @if(!$isNonTravelMemo)<th></th>@endif
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                        @if(isset($budgetArray['grand_total']))
                            @php $grandTotal = (float)$budgetArray['grand_total']; @endphp
                        @endif
                        <div class="grand-total text-end">
                            <h5 class="fw-bold mb-0">
                                <i class="bx bx-dollar me-2"></i>
                                Grand Total: ${{ number_format($grandTotal, 2) }}
                            </h5>
                        </div>
                    @elseif($sourceType === 'Activity' && $fundCodesCollection->isEmpty())
                        <div class="alert alert-warning">
                            <i class="bx bx-info-circle me-2"></i>No budget codes selected for this activity.
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <i class="bx bx-info-circle me-2"></i>No budget information available for this {{ strtolower($sourceType ?? 'source') }}.
                        </div>
                    @endif
                </div>

                <!-- Request for Approval Section -->
                <div class="mt-4">
                    <div class="section-header">
                        <h6 class="fw-bold mb-0 text-primary">
                            <i class="bx bx-check-circle me-2"></i>Request for Approval
                        </h6>
                    </div>
                    
                    <div class="bg-light p-3 rounded border mb-3" style="word-wrap: break-word; word-break: break-word; white-space: normal; overflow-wrap: break-word; max-height: 150px; overflow-y: auto;">
                        {!! $requestForApproval ?? 'N/A' !!}
                    </div>
                    
                    <form id="createArfForm" action="{{ route('request-arf.store-from-modal') }}" method="POST">
                        @csrf
                        @php
                            // Convert display sourceType to validation format
                            $sourceTypeMap = [
                                'Activity' => 'activity',
                                'Non-Travel Memo' => 'non_travel',
                                'Special Memo' => 'special_memo'
                            ];
                            $sourceTypeValue = $sourceTypeMap[$sourceType] ?? 'activity';
                        @endphp
                        <input type="hidden" name="source_type" value="{{ $sourceTypeValue }}">
                        <input type="hidden" name="source_id" value="{{ $sourceId ?? '' }}">
                        <input type="hidden" name="title" value="{{ $defaultTitle ?? 'Activity Request' }}">
                        <input type="hidden" name="total_budget" value="{{ $totalBudget ?? '0.00' }}">
                        <input type="hidden" name="fund_type_id" value="{{ $fundTypeId ?? '' }}">
                        <input type="hidden" name="model_type" value="{{ $modelType ?? 'App\\Models\\Activity' }}">
                        
                        <div class="d-flex gap-2 justify-content-end pt-3 border-top">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="bx bx-x me-1"></i>Cancel
                            </button>
                            <button type="button" id="submitApprovalBtn" class="btn btn-success">
                                <i class="bx bx-send me-1"></i>Submit Activity Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // ARF Form AJAX Submission
    function submitArfForm() {
        const form = $('#createArfForm');
        const submitBtn = $('#submitApprovalBtn');
        const originalBtnText = submitBtn.html();
        
        // Disable submit button and show loading state
        submitBtn.prop('disabled', true)
            .html('<i class="bx bx-loader-alt bx-spin me-1"></i>Submitting...');
        
        // Create FormData object
        const formData = new FormData(form[0]);
        formData.append('action', 'submit');
        
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Show success notification
                    const message = 'ARF request submitted for final approval successfully! Status: Pending';
                    
                    show_notification(message, 'success');
                    
                    // Close modal after short delay
                    setTimeout(function() {
                        $('#createArfModal').modal('hide');
                        
                        // Redirect to ARF show page if redirect_url is provided
                        if (response.redirect_url) {
                            window.location.href = response.redirect_url;
                        } else {
                            // Fallback to reload if no redirect_url
                            window.location.reload();
                        }
                    }, 1500);
                    
                } else {
                    show_notification(response.msg || 'An error occurred while creating the ARF request.', 'error');
                    submitBtn.prop('disabled', false).html(originalBtnText);
                }
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while creating the ARF request.';
                
                if (xhr.status === 422) {
                    // Validation errors
                    const errors = xhr.responseJSON.errors;
                    if (errors) {
                        // Show first error message as notification
                        const firstError = Object.values(errors)[0][0];
                        show_notification(firstError, 'error');
                    }
                } else if (xhr.responseJSON && xhr.responseJSON.msg) {
                    errorMessage = xhr.responseJSON.msg;
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred. Please try again.';
                } else if (xhr.status === 403) {
                    errorMessage = 'You do not have permission to perform this action.';
                } else if (xhr.status === 404) {
                    errorMessage = 'The requested resource was not found.';
                }
                
                show_notification(errorMessage, 'error');
                submitBtn.prop('disabled', false).html(originalBtnText);
            }
        });
    }
    
    // Handle Submit for Final Approval button click
    $('#submitApprovalBtn').on('click', function(e) {
        e.preventDefault();
        submitArfForm();
    });
});
</script>
