@extends('layouts.app')

@section('title', 'Add Activity')

@section('header', "Add Activity - {$matrix->quarter} {$matrix->year}")

@section('header-actions')
<a href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back"></i> Back to Matrix
</a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bx bx-calendar-check me-2 text-primary"></i>Activity Details</h5>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('matrices.activities.store', $matrix) }}" method="POST" id="activityForm">
            @csrf

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="workplan_activity_code" class="form-label fw-semibold"><i class="bx bx-code-alt me-1 text-primary"></i>Activity Code <span class="text-danger">*</span></label>
                        <input type="text"
                               name="workplan_activity_code"
                               id="workplan_activity_code"
                               class="form-control form-control-lg @error('workplan_activity_code') is-invalid @enderror"
                               value="{{ old('workplan_activity_code') }}"
                               placeholder="Enter activity code"
                               required>
                        <small class="text-muted mt-1 d-block">Unique identifier for this activity</small>
                        @error('workplan_activity_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="request_type_id" class="form-label fw-semibold"><i class="bx bx-category me-1 text-primary"></i>Request Type <span class="text-danger">*</span></label>
                        <select name="request_type_id"
                                id="request_type_id"
                                class="form-select form-select-lg @error('request_type_id') is-invalid @enderror"
                                required>
                            <option value="">Select Request Type</option>
                            @foreach($requestTypes as $type)
                                <option value="{{ $type->id }}" {{ old('request_type_id') == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted mt-1 d-block">Category of this activity request</small>
                        @error('request_type_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="card border shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="m-0 fw-semibold"><i class="bx bx-info-circle me-2 text-primary"></i>Activity Information</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-12">
                            <div class="form-group position-relative">
                                <label for="activity_title" class="form-label fw-semibold"><i class="bx bx-heading me-1 text-primary"></i>Activity Title <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="activity_title"
                                       id="activity_title"
                                       class="form-control form-control-lg @error('activity_title') is-invalid @enderror"
                                       value="{{ old('activity_title') }}"
                                       placeholder="Enter a descriptive title for this activity"
                                       required>
                                @error('activity_title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group position-relative">
                                <label for="background" class="form-label fw-semibold"><i class="bx bx-detail me-1 text-primary"></i>Background <span class="text-danger">*</span></label>
                                <textarea name="background"
                                          id="background"
                                          class="form-control @error('background') is-invalid @enderror"
                                          rows="4"
                                          placeholder="Provide background information about this activity"
                                          required>{{ old('background') }}</textarea>
                                <small class="text-muted mt-1 d-block">Describe the purpose and context of this activity</small>
                                @error('background')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="m-0 fw-semibold"><i class="bx bx-calendar me-2 text-primary"></i>Date & Time</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="form-group position-relative">
                                <label for="date_from" class="form-label fw-semibold"><i class="bx bx-calendar-plus me-1 text-primary"></i>Start Date <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="date_from"
                                       id="date_from"
                                       class="form-control form-control-lg datepicker @error('date_from') is-invalid @enderror"
                                       value="{{ old('date_from') }}"
                                       placeholder="Select start date"
                                       required>
                                <small class="text-muted mt-1 d-block">When the activity will begin</small>
                                @error('date_from')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group position-relative">
                                <label for="date_to" class="form-label fw-semibold"><i class="bx bx-calendar-x me-1 text-primary"></i>End Date <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="date_to"
                                       id="date_to"
                                       class="form-control form-control-lg datepicker @error('date_to') is-invalid @enderror"
                                       value="{{ old('date_to') }}"
                                       placeholder="Select end date"
                                       required>
                                <small class="text-muted mt-1 d-block">When the activity will conclude</small>
                                @error('date_to')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="m-0 fw-semibold"><i class="bx bx-user-plus me-2 text-primary"></i>Personnel & Participants</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="form-group position-relative">
                                <label for="staff_id" class="form-label fw-semibold"><i class="bx bx-user-circle me-1 text-primary"></i>Responsible Staff <span class="text-danger">*</span></label>
                                <select name="staff_id"
                                        id="staff_id"
                                        class="form-select form-select-lg @error('staff_id') is-invalid @enderror"
                                        required>
                                    <option value="">Select Primary Staff</option>
                                    @foreach($staff as $member)
                                        <option value="{{ $member->id }}" {{ old('staff_id') == $member->id ? 'selected' : '' }}>
                                            {{ $member->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted mt-1 d-block">Staff member responsible for this activity</small>
                                @error('staff_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group position-relative">
                                <label for="internal_participants" class="form-label fw-semibold"><i class="bx bx-group me-1 text-primary"></i>Internal Participants <span class="text-danger">*</span></label>
                                <select name="internal_participants[]"
                                        id="internal_participants"
                                        class="form-select form-select-lg @error('internal_participants') is-invalid @enderror"
                                        multiple
                                        required>
                                    @foreach($staff as $member)
                                        <option value="{{ $member->id }}" {{ in_array($member->id, old('internal_participants', [])) ? 'selected' : '' }}>
                                            {{ $member->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted mt-1 d-block">Select all staff members who will participate</small>
                                @error('internal_participants')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="m-0 fw-semibold"><i class="bx bx-map-pin me-2 text-primary"></i>Participants & Location</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="form-group position-relative">
                                <label for="total_participants" class="form-label fw-semibold"><i class="bx bx-user-voice me-1 text-primary"></i>Total Participants <span class="text-danger">*</span></label>
                                <input type="number"
                                       name="total_participants"
                                       id="total_participants"
                                       class="form-control form-control-lg @error('total_participants') is-invalid @enderror"
                                       value="{{ old('total_participants') }}"
                                       min="1"
                                       placeholder="Total number of participants"
                                       required>
                                <small class="text-muted mt-1 d-block">Combined internal and external participants</small>
                                @error('total_participants')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group position-relative">
                                <label for="external_participants" class="form-label fw-semibold"><i class="bx bx-user-plus me-1 text-primary"></i>External Participants</label>
                                <input type="number"
                                       name="external_participants"
                                       id="external_participants"
                                       class="form-control form-control-lg @error('external_participants') is-invalid @enderror"
                                       value="{{ old('external_participants', 0) }}"
                                       placeholder="Number of external participants"
                                       min="0">
                                <small class="text-muted mt-1 d-block">Participants from outside the organization</small>
                                @error('external_participants')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group position-relative">
                                <label for="location_id" class="form-label fw-semibold"><i class="bx bx-map me-1 text-primary"></i>Location <span class="text-danger">*</span></label>
                                <select name="location_id[]"
                                        id="location_id"
                                        class="form-select form-select-lg @error('location_id') is-invalid @enderror"
                                        multiple
                                        required>
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}" {{ in_array($location->id, old('location_id', [])) ? 'selected' : '' }}>
                                            {{ $location->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted mt-1 d-block">Where the activity will take place</small>
                                @error('location_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="m-0 fw-semibold"><i class="bx bx-money me-2 text-primary"></i>Budget Details</h6>
                </div>
                <div class="card-body p-4">
                    <div id="budgetItems">
                        <div class="budget-item mb-4">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-5">
                                    <div class="form-floating">
                                        <input type="text"
                                               name="budget[items][0][description]"
                                               id="budget-desc-0"
                                               class="form-control"
                                               placeholder="Item description"
                                               value="{{ old('budget.items.0.description') }}"
                                               required>
                                        <label for="budget-desc-0">Item Description</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-floating">
                                        <input type="number"
                                               name="budget[items][0][amount]"
                                               id="budget-amount-0"
                                               class="form-control amount-input"
                                               placeholder="Amount"
                                               value="{{ old('budget.items.0.amount') }}"
                                               required>
                                        <label for="budget-amount-0">Amount ($)</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-floating">
                                        <input type="number"
                                               name="budget[items][0][quantity]"
                                               id="budget-qty-0"
                                               class="form-control quantity-input"
                                               placeholder="Quantity"
                                               value="{{ old('budget.items.0.quantity', 1) }}"
                                               required>
                                        <label for="budget-qty-0">Quantity</label>
                                    </div>
                                </div>
                                <div class="col-md-1 d-flex align-items-center">
                                    <button type="button" class="btn btn-outline-danger remove-budget-item" title="Remove item">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="button" class="btn btn-success btn-lg px-4 shadow-sm" id="addBudgetItem">
                            <i class="bx bx-plus-circle me-2"></i> Add Budget Item
                        </button>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <div class="border p-4 rounded-3 shadow-sm bg-light">
                            <h5 class="mb-0">Total Budget: <span class="text-success fw-bold">$<span id="budgetTotal">0.00</span></span></h5>
                            <input type="hidden" name="budget[total]" id="budgetTotalInput" value="0">
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <div class="form-group">
                    <label for="activity_request_remarks" class="form-label">Remarks <span class="text-danger">*</span></label>
                    <textarea name="activity_request_remarks"
                              id="activity_request_remarks"
                              class="form-control @error('activity_request_remarks') is-invalid @enderror"
                              rows="3"
                              required>{{ old('activity_request_remarks') }}</textarea>
                    @error('activity_request_remarks')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-4">
                <div class="form-group">
                    <label for="key_result_area" class="form-label">Key Result Area <span class="text-danger">*</span></label>
                    <textarea name="key_result_area"
                              id="key_result_area"
                              class="form-control @error('key_result_area') is-invalid @enderror"
                              rows="2"
                              required>{{ old('key_result_area') }}</textarea>
                    @error('key_result_area')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4">
                <a href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-secondary px-4">
                    <i class="bx bx-arrow-back me-1"></i> Cancel
                </a>
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bx bx-save me-1"></i> Save Activity
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        let budgetIndex = 1;

        // Initialize date range picker
        $('.datepicker').flatpickr({
            dateFormat: "Y-m-d",
            minDate: "today",
            allowInput: true
        });

        // Add new budget item
        $('#addBudgetItem').click(function() {
            const newItem = `
                <div class="budget-item mb-3">
                    <div class="row">
                        <div class="col-md-5">
                            <input type="text"
                                   name="budget[items][${budgetIndex}][description]"
                                   class="form-control"
                                   placeholder="Item description"
                                   required>
                        </div>
                        <div class="col-md-3">
                            <input type="number"
                                   name="budget[items][${budgetIndex}][amount]"
                                   class="form-control amount-input"
                                   placeholder="Amount"
                                   required>
                        </div>
                        <div class="col-md-3">
                            <input type="number"
                                   name="budget[items][${budgetIndex}][quantity]"
                                   class="form-control quantity-input"
                                   placeholder="Quantity"
                                   value="1"
                                   required>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-link text-danger remove-budget-item">
                                <i class="bx bx-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('#budgetItems').append(newItem);
            budgetIndex++;
        });

        // Remove budget item
        $(document).on('click', '.remove-budget-item', function() {
            $(this).closest('.budget-item').remove();
            calculateTotal();
        });

        // Calculate total
        function calculateTotal() {
            let total = 0;
            $('.budget-item').each(function() {
                const amount = parseFloat($(this).find('.amount-input').val()) || 0;
                const quantity = parseFloat($(this).find('.quantity-input').val()) || 1;
                total += amount * quantity;
            });
            $('#budgetTotal').text(total.toFixed(2));
            $('#budgetTotalInput').val(total.toFixed(2));
        }

        // Recalculate on input change
        $(document).on('input', '.amount-input, .quantity-input', calculateTotal);

        // Date validation
        $('#date_to').on('change', function() {
            const startDate = $('#date_from').val();
            const endDate = $(this).val();

            if (startDate && endDate && new Date(endDate) < new Date(startDate)) {
                alert('End date cannot be earlier than start date');
                $(this).val('');
            }
        });

        // Participants validation
        $('#total_participants').on('input', function() {
            const total = parseInt($(this).val());
            const internal = $('#internal_participants').val().length;

            if (total < internal) {
                alert('Total participants cannot be less than internal participants');
                $(this).val(internal);
            }
        });

        $('#internal_participants').on('change', function() {
            const internal = $(this).val().length;
            const total = parseInt($('#total_participants').val());

            if (total < internal) {
                $('#total_participants').val(internal);
            }
        });
    });
</script>
@endpush
@endsection
