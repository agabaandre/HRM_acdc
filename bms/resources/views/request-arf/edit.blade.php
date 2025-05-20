@extends('layouts.app')

@section('title', 'Edit ARF Request')

@section('header', 'Edit ARF Request')

@section('header-actions')
<a href="{{ route('request-arf.index') }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back me-1"></i> Back to List
</a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bx bx-edit me-2 text-primary"></i>Edit ARF Request Details</h5>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('request-arf.update', $requestARF) }}" method="POST" enctype="multipart/form-data" id="arfRequestForm">
            @csrf
            @method('PUT')

            <!-- Basic Information -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="arf_number" class="form-label fw-semibold">
                            <i class="bx bx-hash me-1 text-primary"></i>ARF Number <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               name="arf_number" 
                               id="arf_number" 
                               class="form-control form-control-lg @error('arf_number') is-invalid @enderror" 
                               value="{{ old('arf_number', $requestARF->arf_number) }}" 
                               required
                               readonly>
                        @error('arf_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="request_date" class="form-label fw-semibold">
                            <i class="bx bx-calendar me-1 text-primary"></i>Request Date <span class="text-danger">*</span>
                        </label>
                        <input type="date" 
                               name="request_date" 
                               id="request_date" 
                               class="form-control form-control-lg @error('request_date') is-invalid @enderror" 
                               value="{{ old('request_date', $requestARF->request_date->format('Y-m-d')) }}" 
                               required>
                        @error('request_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="activity_title" class="form-label fw-semibold">
                            <i class="bx bx-heading me-1 text-primary"></i>Activity Title <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               name="activity_title" 
                               id="activity_title" 
                               class="form-control form-control-lg @error('activity_title') is-invalid @enderror" 
                               value="{{ old('activity_title', $requestARF->activity_title) }}" 
                               required>
                        @error('activity_title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="staff_id" class="form-label fw-semibold">
                            <i class="bx bx-user me-1 text-primary"></i>Staff Member <span class="text-danger">*</span>
                        </label>
                        <select name="staff_id" 
                                id="staff_id" 
                                class="form-select form-select-lg @error('staff_id') is-invalid @enderror" 
                                required>
                            <option value="">Select Staff Member</option>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}" {{ old('staff_id', $requestARF->staff_id) == $member->id ? 'selected' : '' }}>
                                    {{ $member->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('staff_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="division_id" class="form-label fw-semibold">
                            <i class="bx bx-building me-1 text-primary"></i>Division <span class="text-danger">*</span>
                        </label>
                        <select name="division_id" 
                                id="division_id" 
                                class="form-select form-select-lg @error('division_id') is-invalid @enderror" 
                                required>
                            <option value="">Select Division</option>
                            @foreach($divisions as $division)
                                <option value="{{ $division->id }}" {{ old('division_id', $requestARF->division_id) == $division->id ? 'selected' : '' }}>
                                    {{ $division->division_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('division_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="location_id" class="form-label fw-semibold">
                            <i class="bx bx-map-pin me-1 text-primary"></i>Locations <span class="text-danger">*</span>
                        </label>
                        <select name="location_id[]" 
                                id="location_id" 
                                class="form-select form-select-lg @error('location_id') is-invalid @enderror" 
                                multiple
                                required>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" {{ (old('location_id', $requestARF->location_id) && in_array($location->id, old('location_id', $requestARF->location_id))) ? 'selected' : '' }}>
                                    {{ $location->location_name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted mt-1 d-block">Hold Ctrl/Cmd to select multiple locations</small>
                        @error('location_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="forward_workflow_id" class="form-label fw-semibold">
                            <i class="bx bx-git-branch me-1 text-primary"></i>Forward Workflow <span class="text-danger">*</span>
                        </label>
                        <select name="forward_workflow_id" 
                                id="forward_workflow_id" 
                                class="form-select form-select-lg @error('forward_workflow_id') is-invalid @enderror" 
                                required>
                            <option value="">Select Forward Workflow</option>
                            @foreach($workflows as $workflow)
                                <option value="{{ $workflow->id }}" {{ old('forward_workflow_id', $requestARF->forward_workflow_id) == $workflow->id ? 'selected' : '' }}>
                                    {{ $workflow->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('forward_workflow_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="reverse_workflow_id" class="form-label fw-semibold">
                            <i class="bx bx-git-repo-forked me-1 text-primary"></i>Reverse Workflow <span class="text-danger">*</span>
                        </label>
                        <select name="reverse_workflow_id" 
                                id="reverse_workflow_id" 
                                class="form-select form-select-lg @error('reverse_workflow_id') is-invalid @enderror" 
                                required>
                            <option value="">Select Reverse Workflow</option>
                            @foreach($workflows as $workflow)
                                <option value="{{ $workflow->id }}" {{ old('reverse_workflow_id', $requestARF->reverse_workflow_id) == $workflow->id ? 'selected' : '' }}>
                                    {{ $workflow->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('reverse_workflow_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex align-items-center mb-3">
                    <h5 class="fw-semibold m-0"><i class="bx bx-detail text-primary me-2"></i>Activity Details <span class="text-danger">*</span></h5>
                    <hr class="flex-grow-1 mx-3">
                </div>
                
                <div class="form-group position-relative mb-4">
                    <label for="purpose" class="form-label fw-semibold">
                        <i class="bx bx-target-lock me-1 text-primary"></i>Purpose of Activity <span class="text-danger">*</span>
                    </label>
                    <textarea name="purpose" 
                             id="purpose" 
                             class="form-control @error('purpose') is-invalid @enderror" 
                             rows="4" 
                             required>{{ old('purpose', $requestARF->purpose) }}</textarea>
                    @error('purpose')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="start_date" class="form-label fw-semibold">
                            <i class="bx bx-calendar-check me-1 text-primary"></i>Start Date <span class="text-danger">*</span>
                        </label>
                        <input type="date" 
                               name="start_date" 
                               id="start_date" 
                               class="form-control form-control-lg @error('start_date') is-invalid @enderror" 
                               value="{{ old('start_date', $requestARF->start_date->format('Y-m-d')) }}" 
                               required>
                        @error('start_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="end_date" class="form-label fw-semibold">
                            <i class="bx bx-calendar-x me-1 text-primary"></i>End Date <span class="text-danger">*</span>
                        </label>
                        <input type="date" 
                               name="end_date" 
                               id="end_date" 
                               class="form-control form-control-lg @error('end_date') is-invalid @enderror" 
                               value="{{ old('end_date', $requestARF->end_date->format('Y-m-d')) }}" 
                               required>
                        @error('end_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="requested_amount" class="form-label fw-semibold">
                            <i class="bx bx-money me-1 text-primary"></i>Requested Amount <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" 
                                   name="requested_amount" 
                                   id="requested_amount" 
                                   class="form-control form-control-lg @error('requested_amount') is-invalid @enderror" 
                                   value="{{ old('requested_amount', $requestARF->requested_amount) }}" 
                                   step="0.01"
                                   min="0"
                                   required>
                        </div>
                        @error('requested_amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="accounting_code" class="form-label fw-semibold">
                            <i class="bx bx-code-alt me-1 text-primary"></i>Accounting Code <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               name="accounting_code" 
                               id="accounting_code" 
                               class="form-control form-control-lg @error('accounting_code') is-invalid @enderror" 
                               value="{{ old('accounting_code', $requestARF->accounting_code) }}" 
                               required>
                        @error('accounting_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex align-items-center mb-3">
                    <h5 class="fw-semibold m-0"><i class="bx bx-money text-primary me-2"></i>Budget Breakdown <span class="text-danger">*</span></h5>
                    <hr class="flex-grow-1 mx-3">
                </div>
                
                <div id="budget-items">
                    @if($requestARF->budget_breakdown && is_array($requestARF->budget_breakdown))
                        @foreach($requestARF->budget_breakdown as $index => $item)
                            <div class="budget-item card border shadow-sm mb-3">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 fw-semibold">Budget Item #{{ $index + 1 }}</h6>
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-budget-item">
                                        <i class="bx bx-trash me-1"></i> Remove
                                    </button>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label fw-semibold">Description</label>
                                                <input type="text" 
                                                       name="budget_breakdown[{{ $index }}][description]" 
                                                       class="form-control" 
                                                       value="{{ old('budget_breakdown.'.$index.'.description', $item['description'] ?? '') }}" 
                                                       required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="form-label fw-semibold">Quantity</label>
                                                <input type="number" 
                                                       name="budget_breakdown[{{ $index }}][quantity]" 
                                                       class="form-control budget-quantity" 
                                                       value="{{ old('budget_breakdown.'.$index.'.quantity', $item['quantity'] ?? 1) }}" 
                                                       min="1" 
                                                       required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="form-label fw-semibold">Unit Price</label>
                                                <input type="number" 
                                                       name="budget_breakdown[{{ $index }}][unit_price]" 
                                                       class="form-control budget-unit-price" 
                                                       value="{{ old('budget_breakdown.'.$index.'.unit_price', $item['unit_price'] ?? 0) }}" 
                                                       min="0" 
                                                       step="0.01" 
                                                       required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label class="form-label fw-semibold">Notes</label>
                                                <textarea name="budget_breakdown[{{ $index }}][notes]" 
                                                         class="form-control" 
                                                         rows="2">{{ old('budget_breakdown.'.$index.'.notes', $item['notes'] ?? '') }}</textarea>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="text-end">
                                                <span class="fw-bold">
                                                    Total: <span class="budget-item-total">{{ number_format(($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0), 2) }}</span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
                
                <div class="row g-3 align-items-center">
                    <div class="col-md-9">
                        <button type="button" class="btn btn-outline-primary" id="add-budget-item">
                            <i class="bx bx-plus-circle me-1"></i> Add Budget Item
                        </button>
                    </div>
                    <div class="col-md-3 text-end">
                        <h5 class="mb-0">
                            Grand Total: $<span id="grand-total">0.00</span>
                        </h5>
                    </div>
                </div>
            </div>

            <!-- Current Attachments Section -->
            @if(!empty($requestARF->attachment) && count($requestARF->attachment) > 0)
                <div class="mb-4">
                    <h6 class="fw-semibold"><i class="bx bx-file me-1 text-primary"></i>Current Attachments</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="bg-light">
                                <tr>
                                    <th>File Name</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($requestARF->attachment as $index => $attachment)
                                    <tr>
                                        <td>{{ $attachment['name'] ?? 'File #'.($index+1) }}</td>
                                        <td>{{ $attachment['type'] ?? 'Unknown' }}</td>
                                        <td>{{ isset($attachment['size']) ? round($attachment['size']/1024, 2).' KB' : 'N/A' }}</td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="{{ Storage::url($attachment['path']) }}" target="_blank" class="btn btn-sm btn-info">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                                <form action="{{ route('request-arf.remove-attachment', $requestARF) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="attachment_index" value="{{ $index }}">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this attachment?')">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="mb-4">
                <div class="form-group position-relative">
                    <label for="attachment" class="form-label fw-semibold">
                        <i class="bx bx-paperclip me-1 text-primary"></i>New Attachments
                    </label>
                    <input type="file" 
                           name="attachment[]" 
                           id="attachment" 
                           class="form-control form-control-lg @error('attachment') is-invalid @enderror" 
                           multiple>
                    <small class="text-muted mt-1 d-block">Allowed file types: PDF, DOC, DOCX, JPG, JPEG, PNG (max 10MB each)</small>
                    @error('attachment')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    @error('attachment.*')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-4">
                <div class="form-group position-relative">
                    <label for="status" class="form-label fw-semibold">
                        <i class="bx bx-check-circle me-1 text-primary"></i>Status
                    </label>
                    <select name="status" 
                            id="status" 
                            class="form-select form-select-lg @error('status') is-invalid @enderror">
                        <option value="draft" {{ old('status', $requestARF->status) == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="submitted" {{ old('status', $requestARF->status) == 'submitted' ? 'selected' : '' }}>Submit for Approval</option>
                        @if(in_array($requestARF->status, ['approved', 'rejected']))
                            <option value="{{ $requestARF->status }}" selected>{{ ucfirst($requestARF->status) }}</option>
                        @endif
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <a href="{{ route('request-arf.index') }}" class="btn btn-outline-secondary px-4 btn-lg">
                    <i class="bx bx-arrow-back me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm">
                    <i class="bx bx-save me-2"></i> Update Request
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<!-- Import Select2, SweetAlert -->
<link href="{{ asset('assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
<script src="{{ asset('assets/libs/select2/js/select2.min.js') }}"></script>
<script src="{{ asset('assets/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script type="text/javascript">
    $(document).ready(function() {
        let budgetIndex = {{ count($requestARF->budget_breakdown ?? []) }};

        // Initialize Select2 for better dropdown UX
        $('.form-select').select2({
            dropdownParent: $('#arfRequestForm'),
        });

        // Add new budget item
        $('#add-budget-item').click(function() {
            const newItem = `
                <div class="budget-item card border shadow-sm mb-3" style="display: none;">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-semibold">Budget Item #${budgetIndex + 1}</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-budget-item">
                            <i class="bx bx-trash me-1"></i> Remove
                        </button>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label fw-semibold">Description</label>
                                    <input type="text" 
                                           name="budget_breakdown[${budgetIndex}][description]" 
                                           class="form-control" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label fw-semibold">Quantity</label>
                                    <input type="number" 
                                           name="budget_breakdown[${budgetIndex}][quantity]" 
                                           class="form-control budget-quantity" 
                                           value="1" 
                                           min="1" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label fw-semibold">Unit Price</label>
                                    <input type="number" 
                                           name="budget_breakdown[${budgetIndex}][unit_price]" 
                                           class="form-control budget-unit-price" 
                                           value="0" 
                                           min="0" 
                                           step="0.01" 
                                           required>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group">
                                    <label class="form-label fw-semibold">Notes</label>
                                    <textarea name="budget_breakdown[${budgetIndex}][notes]" 
                                             class="form-control" 
                                             rows="2"></textarea>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="text-end">
                                    <span class="fw-bold">
                                        Total: <span class="budget-item-total">0.00</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            const $newItem = $(newItem);
            $('#budget-items').append($newItem);
            $newItem.slideDown(300);
            budgetIndex++;

            // Scroll to the new item
            $('html, body').animate({
                scrollTop: $newItem.offset().top - 100
            }, 300);
            
            // Initialize total calculation for the new item
            $newItem.find('.budget-quantity, .budget-unit-price').trigger('input');
        });

        // Remove budget item
        $(document).on('click', '.remove-budget-item', function() {
            const itemsCount = $('.budget-item').length;
            if (itemsCount > 1) {
                const $item = $(this).closest('.budget-item');
                $item.slideUp(300, function() {
                    $item.remove();
                    // Update the numbering of remaining items
                    $('.budget-item').each(function(idx) {
                        $(this).find('h6').text(`Budget Item #${idx + 1}`);
                    });
                    updateTotalBudget();
                });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Cannot Remove',
                    text: 'At least one budget item is required.',
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: '<i class="bx bx-check me-1"></i> OK',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            }
        });

        // Calculate budget item total
        $(document).on('input', '.budget-quantity, .budget-unit-price', function() {
            const $item = $(this).closest('.budget-item');
            const quantity = parseFloat($item.find('.budget-quantity').val()) || 0;
            const unitPrice = parseFloat($item.find('.budget-unit-price').val()) || 0;
            const total = quantity * unitPrice;
            $item.find('.budget-item-total').text(total.toFixed(2));
            updateTotalBudget();
            
            // Update the requested amount field
            $('#requested_amount').val(parseFloat($('#grand-total').text()).toFixed(2));
        });

        // Update total budget
        function updateTotalBudget() {
            let grandTotal = 0;
            $('.budget-item').each(function() {
                const itemTotal = parseFloat($(this).find('.budget-item-total').text()) || 0;
                grandTotal += itemTotal;
            });
            $('#grand-total').text(grandTotal.toFixed(2));
            
            // Update the requested amount field
            $('#requested_amount').val(grandTotal.toFixed(2));
        }

        // Initialize totals
        $('.budget-quantity, .budget-unit-price').trigger('input');
        
        // Check if we need to add at least one budget item
        if ($('.budget-item').length === 0) {
            $('#add-budget-item').click();
        }

        // Form validation
        $('#arfRequestForm').on('submit', function(e) {
            if ($('.budget-item').length === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'At least one budget item is required.',
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: '<i class="bx bx-check me-1"></i> OK',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
                return false;
            }

            // Show loading indicator
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.html('<i class="bx bx-loader bx-spin me-2"></i> Updating...');
            submitBtn.prop('disabled', true);

            return true;
        });
        
        // Date validation
        $('#end_date').on('change', function() {
            const startDate = new Date($('#start_date').val());
            const endDate = new Date($(this).val());
            
            if (endDate < startDate) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Date',
                    text: 'End date cannot be earlier than start date.',
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: '<i class="bx bx-check me-1"></i> OK',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
                $(this).val('');
            }
        });
        
        // If we have a start date, set min date for end date
        $('#start_date').on('change', function() {
            $('#end_date').attr('min', $(this).val());
        });
    });
</script>
@endpush
@endsection
