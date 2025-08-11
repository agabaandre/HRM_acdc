@extends('layouts.app')

@section('title', 'Edit Non-Travel Memo')

@section('header', 'Edit Non-Travel Memo')

@section('header-actions')
<a href="{{ route('non-travel.index') }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back me-1"></i> Back to List
</a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bx bx-edit me-2 text-primary"></i>Edit Memo Details</h5>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('non-travel.update', $nonTravel) }}" method="POST" enctype="multipart/form-data" id="nonTravelForm">
            @csrf
            @method('PUT')

            <div class="row g-4 mb-4">
                <div class="col-md-12">
                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="bx bx-info-circle me-2"></i>
                        <div>
                            <strong>Status:</strong> {{ ucfirst($nonTravel->overall_status ?? 'draft') }}
                        </div>
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
                               value="{{ old('activity_title', $nonTravel->activity_title) }}" 
                               required>
                        @error('activity_title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="workplan_activity_code" class="form-label fw-semibold">
                            <i class="bx bx-code-block me-1 text-primary"></i>Activity Code <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               name="workplan_activity_code" 
                               id="workplan_activity_code" 
                               class="form-control form-control-lg @error('workplan_activity_code') is-invalid @enderror" 
                               value="{{ old('workplan_activity_code', $nonTravel->workplan_activity_code) }}" 
                               required>
                        @error('workplan_activity_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
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
                                <option value="{{ $member->staff_id }}" 
                                        {{ (int)old('staff_id', $nonTravel->staff_id) === $member->staff_id ? 'selected' : '' }}>
                                    {{ $member->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('staff_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="memo_date" class="form-label fw-semibold">
                            <i class="bx bx-calendar me-1 text-primary"></i>Memo Date <span class="text-danger">*</span>
                        </label>
                        <input type="date" 
                               name="memo_date" 
                               id="memo_date" 
                               class="form-control form-control-lg @error('memo_date') is-invalid @enderror" 
                               value="{{ old('memo_date', $nonTravel->memo_date ? $nonTravel->memo_date->format('Y-m-d') : '') }}" 
                               required>
                        @error('memo_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="non_travel_memo_category_id" class="form-label fw-semibold">
                            <i class="bx bx-category me-1 text-primary"></i>Memo Category <span class="text-danger">*</span>
                        </label>
                        <select name="non_travel_memo_category_id" 
                                id="non_travel_memo_category_id" 
                                class="form-select form-select-lg @error('non_travel_memo_category_id') is-invalid @enderror" 
                                required>
                            <option value="">Select Category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('non_travel_memo_category_id', $nonTravel->non_travel_memo_category_id) == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('non_travel_memo_category_id')
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
                                @php
                                    $locationIds = is_array($nonTravel->location_id) ? $nonTravel->location_id : (is_string($nonTravel->location_id) ? json_decode($nonTravel->location_id, true) : []);
                                    $oldLocationIds = old('location_id', $locationIds);
                                    $isSelected = is_array($oldLocationIds) && in_array($location->id, $oldLocationIds);
                                @endphp
                                <option value="{{ $location->id }}" {{ $isSelected ? 'selected' : '' }}>
                                    {{ $location->name }}
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
                                <option value="{{ $workflow->id }}" {{ old('forward_workflow_id', $nonTravel->forward_workflow_id) == $workflow->id ? 'selected' : '' }}>
                                    {{ $workflow->workflow_name }}
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
                                <option value="{{ $workflow->id }}" {{ old('reverse_workflow_id', $nonTravel->reverse_workflow_id) == $workflow->id ? 'selected' : '' }}>
                                    {{ $workflow->workflow_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('reverse_workflow_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="budget_codes" class="form-label fw-semibold">
                            <i class="fas fa-wallet me-1 text-primary"></i> Budget Code(s) <span class="text-danger">*</span>
                        </label>
                        <select name="budget_codes[]" 
                                id="budget_codes" 
                                class="form-select form-select-lg @error('budget_codes') is-invalid @enderror" 
                                multiple required>
                            @foreach($budgets as $budget)
                                <option value="{{ $budget['id'] }}" 
                                        {{ in_array($budget['id'], old('budget_codes', $selectedBudgetCodes ?? [])) ? 'selected' : '' }}>
                                    {{ $budget['code'] }} | {{ $budget['funder_name'] }} | ${{ number_format($budget['budget_balance'], 2) }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted mt-1 d-block">Select up to 2 codes</small>
                        @error('budget_codes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="attachment" class="form-label fw-semibold">
                            <i class="bx bx-paperclip me-1 text-primary"></i>New Attachments
                        </label>
                        <input type="file" 
                               name="attachments[]" 
                               id="attachment" 
                               class="form-control form-control-lg @error('attachments') is-invalid @enderror" 
                               multiple>
                        <small class="text-muted mt-1 d-block">Allowed file types: PDF, DOC, DOCX, JPG, JPEG, PNG (max 10MB each)</small>
                        @error('attachments')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @error('attachments.*')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Current Attachments Section -->
            @php
                $attachments = is_array($nonTravel->attachment) ? $nonTravel->attachment : (is_string($nonTravel->attachment) ? json_decode($nonTravel->attachment, true) : []);
            @endphp
            @if(!empty($attachments) && count($attachments) > 0)
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
                                @foreach($attachments as $index => $attachment)
                                    <tr>
                                        <td>{{ $attachment['name'] ?? 'File #'.($index+1) }}</td>
                                        <td>{{ $attachment['type'] ?? 'Unknown' }}</td>
                                        <td>{{ isset($attachment['size']) ? round($attachment['size']/1024, 2).' KB' : 'N/A' }}</td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="{{ Storage::url($attachment['path']) }}" target="_blank" class="btn btn-sm btn-info">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                                <form action="{{ route('non-travel.remove-attachment', $nonTravel) }}" method="POST">
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
                <div class="d-flex align-items-center mb-3">
                    <h5 class="fw-semibold m-0"><i class="bx bx-receipt text-primary me-2"></i>Memo Content <span class="text-danger">*</span></h5>
                    <hr class="flex-grow-1 mx-3">
                </div>
                
                <div class="form-group position-relative mb-4">
                    <label for="background" class="form-label fw-semibold">
                        <i class="bx bx-info-circle me-1 text-primary"></i>Background <span class="text-danger">*</span>
                    </label>
                    <textarea name="background" 
                             id="background" 
                             class="form-control @error('background') is-invalid @enderror" 
                             rows="4" 
                             required>{{ old('background', $nonTravel->background) }}</textarea>
                    @error('background')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-group position-relative mb-4">
                    <label for="activity_request_remarks" class="form-label fw-semibold">
                        <i class="bx bx-message-detail me-1 text-primary"></i>Request Remarks <span class="text-danger">*</span>
                    </label>
                    <textarea name="activity_request_remarks" 
                             id="activity_request_remarks" 
                             class="form-control @error('activity_request_remarks') is-invalid @enderror" 
                             rows="4" 
                             required>{{ old('activity_request_remarks', $nonTravel->activity_request_remarks) }}</textarea>
                    @error('activity_request_remarks')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-group position-relative">
                    <label for="justification" class="form-label fw-semibold">
                        <i class="bx bx-check-shield me-1 text-primary"></i>Justification <span class="text-danger">*</span>
                    </label>
                    <textarea name="justification" 
                             id="justification" 
                             class="form-control @error('justification') is-invalid @enderror" 
                             rows="4" 
                             required>{{ old('justification', $nonTravel->justification) }}</textarea>
                    @error('justification')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex align-items-center mb-3">
                    <h5 class="fw-semibold m-0"><i class="bx bx-money text-primary me-2"></i>Budget Breakdown <span class="text-danger">*</span></h5>
                    <hr class="flex-grow-1 mx-3">
                </div>
                
                <div id="budget-items">
                    @php
                        $budgetBreakdown = old('budget_breakdown', $nonTravel->budget_breakdown);
                        if (is_string($budgetBreakdown)) {
                            $budgetBreakdown = json_decode($budgetBreakdown, true);
                        }
                        $budgetBreakdown = is_array($budgetBreakdown) ? $budgetBreakdown : [];
                        
                        // Flatten the nested structure for editing
                        $flattenedBudget = [];
                        $index = 0;
                        foreach ($budgetBreakdown as $codeId => $items) {
                            if (is_array($items) && $codeId !== 'grand_total') {
                                foreach ($items as $item) {
                                    $flattenedBudget[$index] = $item;
                                    $index++;
                                }
                            }
                        }
                    @endphp
                    @if(!empty($flattenedBudget))
                        @foreach($flattenedBudget as $index => $item)
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
                                                       value="{{ $item['description'] ?? '' }}" 
                                                       required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="form-label fw-semibold">Quantity</label>
                                                <input type="number" 
                                                       name="budget_breakdown[{{ $index }}][quantity]" 
                                                       class="form-control budget-quantity" 
                                                       value="{{ $item['quantity'] ?? 1 }}" 
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
                                                       value="{{ $item['unit_price'] ?? 0 }}" 
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
                                                         rows="2">{{ $item['notes'] ?? '' }}</textarea>
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
                
                <div class="text-center mt-3">
                    <button type="button" class="btn btn-outline-primary btn-lg" id="add-budget-item">
                        <i class="bx bx-plus-circle me-1"></i> Add Budget Item
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-4 gap-2">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bx bx-save me-1"></i> Update Memo
                </button>
                @if(($nonTravel->overall_status ?? 'draft') === 'draft')
                    <form action="{{ route('non-travel.submit-for-approval', $nonTravel) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-success px-4">
                            <i class="bx bx-send me-1"></i> Submit for Approval
                        </button>
                    </form>
                @endif
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
        // Get the initial budget item count
                        let budgetIndex = {{ count($flattenedBudget ?? []) }};
        
        // Check if we need to add at least one budget item
        if (budgetIndex === 0) {
            addBudgetItem();
        }

        // Initialize Select2 for better dropdown UX
        $('.form-select').select2({
            dropdownParent: $('#nonTravelForm'),
        });

        // Add new budget item
        $('#add-budget-item').click(function() {
            addBudgetItem();
        });

        function addBudgetItem() {
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
        }

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
        });

        // Update total budget
        function updateTotalBudget() {
            let grandTotal = 0;
            $('.budget-item').each(function() {
                const itemTotal = parseFloat($(this).find('.budget-item-total').text()) || 0;
                grandTotal += itemTotal;
            });
            $('#grand-total').text(grandTotal.toFixed(2));
        }

        // Initialize totals
        $('.budget-quantity, .budget-unit-price').trigger('input');

        // Form validation
        $('#nonTravelForm').on('submit', function(e) {
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
    });
</script>
@endpush
@endsection
