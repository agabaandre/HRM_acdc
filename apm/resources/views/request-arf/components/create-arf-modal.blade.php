<!-- Create ARF Modal Component -->
<div class="modal fade" id="createArfModal" tabindex="-1" aria-labelledby="createArfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: #119A48; color: white; border-radius: 0.75rem 0.75rem 0 0;">
                <h5 class="modal-title fw-bold text-white" id="createArfModalLabel">
                    <i class="bx bx-file-plus me-2"></i>Create ARF Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="max-height: 80vh; overflow-y: auto;">
                <!-- Source Details Section -->
                <div class="bg-light p-3 border-bottom">
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="bx bx-info-circle me-2"></i>{{ $sourceType ?? 'Source' }} Details
                    </h6>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small"><i class="bx bx-hash me-1"></i>{{ $sourceType }} ID</label>
                            <div class="form-control-plaintext bg-white rounded p-2 border small">{{ $sourceId ?? 'N/A' }}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small"><i class="bx bx-file me-1"></i>ARF Number</label>
                            <div class="form-control-plaintext bg-white rounded p-2 border small">{{ $arfNumber ?? 'Auto-generated' }}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small"><i class="bx bx-buildings me-1"></i>Division</label>
                            <div class="form-control-plaintext bg-white rounded p-2 border small">{{ $divisionName ?? 'N/A' }}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small"><i class="bx bx-money me-1"></i>Fund Location</label>
                            <div class="form-control-plaintext bg-white rounded p-2 border">
                                <span class="badge {{ $fundTypeId == 2 ? 'bg-info' : 'bg-primary' }} small">
                                    {{ $fundTypeName ?? 'N/A' }}
                                </span>
                            </div>
                        </div>
                
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small"><i class="bx bx-user me-1"></i>Focal Person</label>
                            <div class="form-control-plaintext bg-white rounded p-2 border small">{{ $focalPerson ?? 'N/A' }}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small"><i class="bx bx-calendar me-1"></i>Activity Date From</label>
                            <div class="form-control-plaintext bg-white rounded p-2 border small">{{ $dateFrom ?? 'N/A' }}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small"><i class="bx bx-calendar me-1"></i>Activity Date To</label>
                            <div class="form-control-plaintext bg-white rounded p-2 border small">{{ $dateTo ?? 'N/A' }}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small"><i class="bx bx-time me-1"></i>No. of days</label>
                            <div class="form-control-plaintext bg-white rounded p-2 border small">{{ $numberOfDays ?? 'N/A' }}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small"><i class="bx bx-map me-1"></i>Location/Venue</label>
                            <div class="form-control-plaintext bg-white rounded p-2 border small">{{ $location ?? 'N/A' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small"><i class="bx bx-briefcase me-1"></i>Title of mission/activity</label>
                            <div class="form-control-plaintext bg-white rounded p-2 border small">{{ $sourceTitle ?? 'N/A' }}</div>
                        </div>
                        @if($sourceType !== 'Non-Travel Memo')
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small"><i class="bx bx-user-plus me-1"></i>Number of External Participants</label>
                            <div class="form-control-plaintext bg-white rounded p-2 border small">{{ $externalParticipants ?? 'N/A' }}</div>
                        </div>
                        @endif
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small"><i class="bx bx-target-lock me-1"></i>Key Result Area</label>
                            <div class="form-control-plaintext bg-white rounded p-2 border small">{{ $keyResultArea ?? 'N/A' }}</div>
                        </div>
                      
                        @if($sourceType !== 'Non-Travel Memo')
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small"><i class="bx bx-user-circle me-1"></i>Number of internal participants</label>
                            <div class="form-control-plaintext bg-white rounded p-2 border small">{{ $internalParticipants ?? 'N/A' }}</div>
                        </div>
                          <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small"><i class="bx bx-group me-1"></i>Total Number of Participants</label>
                            <div class="form-control-plaintext bg-white rounded p-2 border small">
                                {{ $totalParticipants ?? 'N/A' }}
                                <small class="text-muted d-block">({{ $internalParticipants ?? 0 }} internal + {{ $externalParticipants ?? 0 }} external)</small>
                            </div>
                        </div>
                        @endif
                     
                        <div class="col-md-12">
                            <label class="form-label fw-semibold text-muted small"><i class="bx bx-detail me-1"></i>Background/Context</label>
                            <div class="form-control-plaintext bg-white rounded p-2 border small">{!! $background ?? 'N/A' !!}</div>
                        </div>
                      
                       
                    </div>
                </div>

                <!-- Budget Codes Section -->
                <div class="p-3">
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="bx bx-money me-2"></i>Budget Codes & Allocations
                    </h6>
                    
                    @if(!empty($budgetBreakdown))
                        @if($sourceType === 'Activity' && is_object($budgetBreakdown) && method_exists($budgetBreakdown, 'toArray'))
                            {{-- Matrix Activity Budget (Collection of ActivityBudget models) --}}
                            @foreach($fundCodes as $fundCode)
                                <div class="mb-4">
                                    <h6 style="color: #911C39; font-weight: 600; margin-bottom: 10px;">
                                        {{ $fundCode->activity }} - {{ $fundCode->code }} - ({{ $fundCode->fundType->name }})
                                    </h6>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="fw-bold">#</th>
                                                    <th class="fw-bold">Cost Item</th>
                                                    <th class="fw-bold text-end">Unit Cost</th>
                                                    <th class="fw-bold text-end">Units</th>
                                                    <th class="fw-bold text-end">Days</th>
                                                    <th class="fw-bold text-end">Total</th>
                                                    <th class="fw-bold">Description</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $count = 1;
                                                    $fundTotal = 0;
                                                @endphp
                                            
                                                @foreach($budgetBreakdown as $item)
                                                    @if($item->fund_code == $fundCode->id)
                                                        @php
                                                            $total = $item->unit_cost * $item->units;
                                                            $fundTotal += $total;
                                                        @endphp
                                                        <tr>
                                                            <td>{{ $count++ }}</td>
                                                            <td>{{ $item->cost }}</td>
                                                            <td class="text-end">${{ number_format($item->unit_cost, 2) }}</td>
                                                            <td class="text-end">{{ $item->units }}</td>
                                                            <td class="text-end">{{ $item->days }}</td>
                                                            <td class="text-end fw-bold">${{ number_format($total, 2) }}</td>
                                                            <td>{{ $item->description }}</td>
                                                        </tr>
                                                    @endif
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
                            
                            @php
                                $grandTotal = 0;
                                foreach($budgetBreakdown as $item) {
                                    $grandTotal += $item->unit_cost * $item->units * $item->days;
                                }
                            @endphp
                            
                        @else
                            {{-- Memo Budget (Array with fund code keys) --}}
                            @php
                                $grandTotal = 0;
                                $budgetArray = is_array($budgetBreakdown) ? $budgetBreakdown : [];
                            @endphp
                            
                            @foreach($budgetArray as $fundCodeId => $items)
                                @if($fundCodeId !== 'grand_total' && is_array($items))
                                    @php
                                        $fundCode = $fundCodes[$fundCodeId] ?? null;
                                        $fundTotal = 0;
                                    @endphp
                                    
                                    <div class="mb-4">
                                        <h6 style="color: #911C39; font-weight: 600; margin-bottom: 10px;">
                                            @if($fundCode)
                                                {{ $fundCode->activity }} - {{ $fundCode->code }} - ({{ $fundCode->fundType->name }}) - {{ $fundCode->funder->name ?? 'N/A' }}
                                            @else
                                                Fund Code ID: {{ $fundCodeId }}
                                            @endif
                                        </h6>
                                        
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-sm">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="fw-bold">#</th>
                                                        <th class="fw-bold">Cost Item</th>
                                                        <th class="fw-bold text-end">Unit Cost</th>
                                                        <th class="fw-bold text-end">Quantity</th>
                                                        <th class="fw-bold text-end">Total</th>
                                                        <th class="fw-bold">Description</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @php $count = 1; @endphp
                                                    @foreach($items as $item)
                                                        @php
                                                            // Handle different field names: non-travel uses 'quantity', special memo uses 'units'
                                                            $quantity = $item['quantity'] ?? $item['units'] ?? 1;
                                                            $total = (float)$item['unit_cost'] * (float)$quantity;
                                                            $fundTotal += $total;
                                                            $grandTotal += $total;
                                                        @endphp
                                                        <tr>
                                                            <td>{{ $count++ }}</td>
                                                            <td>{{ $item['cost'] ?? $item['description'] ?? 'N/A' }}</td>
                                                            <td class="text-end">${{ number_format($item['unit_cost'], 2) }}</td>
                                                            <td class="text-end">{{ $quantity }}</td>
                                                            <td class="text-end fw-bold">${{ number_format($total, 2) }}</td>
                                                            <td>{{ $item['description'] ?? 'N/A' }}</td>
                                                        </tr>
                                                    @endforeach
                                  
                                  
                                  
                                          </tbody>
                                                <tfoot class="table-light">
                                                    <tr>
                                                        <th colspan="4" class="text-end">Fund Total:</th>
                                                        <th class="text-end text-success">${{ number_format($fundTotal, 2) }}</th>
                                                        <th></th>
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
                        @endif
                        
                        <div class="alert alert-success">
                            <i class="bx bx-dollar me-2"></i>
                            <strong>Grand Total: ${{ number_format($grandTotal, 2) }}</strong>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <i class="bx bx-info-circle me-2"></i>No budget information available for this {{ strtolower($sourceType ?? 'source') }}.
                        </div>
                    @endif
                </div>

                <!-- Request for Approval Section -->
                <div class="bg-light p-3 border-top">
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="bx bx-check-circle me-2"></i>Request for Approval
                    </h6>
                    <div class="form-control-plaintext bg-white rounded p-3 border">
                        {!! $requestForApproval ?? 'N/A' !!}
                    </div>
                    
            <form id="createArfForm" action="{{ route('request-arf.store-from-modal') }}" method="POST" class="mt-3">
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
                <input type="hidden" name="title" value="{{ $defaultTitle ?? 'ARF Request' }}">
                <input type="hidden" name="total_budget" value="{{ $totalBudget ?? '0.00' }}">
                <input type="hidden" name="fund_type_id" value="{{ $fundTypeId ?? '' }}">
                <input type="hidden" name="model_type" value="{{ $modelType ?? 'App\\Models\\Activity' }}">
                
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i>Cancel
                    </button>
                    <button type="button" id="submitApprovalBtn" class="btn btn-success">
                        <i class="bx bx-send me-1"></i>Submit ARF Request
                    </button>
                </div>
            </form>
                </div>
            </div>
        </div>
    </div>
</div>

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
