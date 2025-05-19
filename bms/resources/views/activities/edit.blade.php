@extends('layouts.app')

@section('title', 'Edit Activity')

@section('header', "Edit Activity - {$matrix->quarter} {$matrix->year}")

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
        <form action="{{ route('matrices.activities.update', [$matrix, $activity]) }}" method="POST" id="activityForm">
            @csrf
            @method('PUT')

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="form-group position-relative">
                        <label for="workplan_activity_code" class="form-label fw-semibold"><i class="bx bx-code-alt me-1 text-primary"></i>Activity Code <span class="text-danger">*</span></label>
                        <input type="text"
                               name="workplan_activity_code"
                               id="workplan_activity_code"
                               class="form-control form-control-lg @error('workplan_activity_code') is-invalid @enderror"
                               value="{{ old('workplan_activity_code', $activity->workplan_activity_code) }}"
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
                                <option value="{{ $type->id }}" {{ old('request_type_id', $activity->request_type_id) == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
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
                                       value="{{ old('activity_title', $activity->activity_title) }}"
                                       required>
                                @error('activity_title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <div class="form-group position-relative">
                                <label for="background" class="form-label fw-semibold"><i class="bx bx-align-left me-1 text-primary"></i>Background <span class="text-danger">*</span></label>
                                <textarea name="background"
                                          id="background"
                                          class="form-control form-control-lg @error('background') is-invalid @enderror"
                                          rows="3"
                                          required>{{ old('background', $activity->background) }}</textarea>
                                @error('background')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="form-group position-relative">
                                <label for="date_from" class="form-label fw-semibold"><i class="bx bx-calendar-plus me-1 text-primary"></i>Start Date <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="date_from"
                                       id="date_from"
                                       class="form-control form-control-lg datepicker @error('date_from') is-invalid @enderror"
                                       value="{{ old('date_from', $activity->date_from->format('Y-m-d')) }}"
                                       required>
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
                                       value="{{ old('date_to', $activity->date_to->format('Y-m-d')) }}"
                                       required>
                                @error('date_to')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="form-group position-relative">
                                <label for="staff_id" class="form-label fw-semibold"><i class="bx bx-user me-1 text-primary"></i>Responsible Staff <span class="text-danger">*</span></label>
                                <select name="staff_id"
                                        id="staff_id"
                                        class="form-select form-select-lg @error('staff_id') is-invalid @enderror"
                                        required>
                                    <option value="">Select Staff</option>
                                    @foreach($staff as $member)
                                        <option value="{{ $member->id }}" {{ old('staff_id', $activity->staff_id) == $member->id ? 'selected' : '' }}>
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
                                <label for="internal_participants" class="form-label fw-semibold"><i class="bx bx-group me-1 text-primary"></i>Internal Participants <span class="text-danger">*</span></label>
                                <select name="internal_participants[]"
                                        id="internal_participants"
                                        class="form-select form-select-lg @error('internal_participants') is-invalid @enderror"
                                        multiple
                                        required>
                                    @foreach($staff as $member)
                                        <option value="{{ $member->id }}" {{ in_array($member->id, old('internal_participants', $activity->internal_participants)) ? 'selected' : '' }}>
                                            {{ $member->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('internal_participants')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="total_participants" class="form-label fw-semibold"><i class="bx bx-user-check me-1 text-primary"></i>Total Participants <span class="text-danger">*</span></label>
                                <input type="number"
                                       name="total_participants"
                                       id="total_participants"
                                       class="form-control form-control-lg @error('total_participants') is-invalid @enderror"
                                       value="{{ old('total_participants', $activity->total_participants) }}"
                                       min="1"
                                       required>
                                @error('total_participants')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="venue" class="form-label fw-semibold"><i class="bx bx-map me-1 text-primary"></i>Venue <span class="text-danger">*</span></label>
                                <select name="location_id[]"
                                        id="location_id"
                                        class="form-select select2 @error('location_id') is-invalid @enderror"
                                        multiple
                                        required>
                                    @foreach(['Kampala', 'Entebbe', 'Jinja', 'Mbarara', 'Gulu'] as $location)
                                        <option value="{{ $location }}" {{ in_array($location, old('location_id', $activity->location_id)) ? 'selected' : '' }}>
                                            {{ $location }}
                                        </option>
                                    @endforeach
                                </select>
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
                        @foreach(old('budget.items', $activity->budget['items'] ?? []) as $index => $item)
                            <div class="budget-item mb-3">
                                <div class="row">
                                    <div class="col-md-5">
                                        <input type="text"
                                               name="budget[items][{{ $index }}][description]"
                                               class="form-control"
                                               placeholder="Item description"
                                               value="{{ $item['description'] }}"
                                               required>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number"
                                               name="budget[items][{{ $index }}][amount]"
                                               class="form-control amount-input"
                                               placeholder="Amount"
                                               value="{{ $item['amount'] }}"
                                               required>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number"
                                               name="budget[items][{{ $index }}][quantity]"
                                               class="form-control quantity-input"
                                               placeholder="Quantity"
                                               value="{{ $item['quantity'] ?? 1 }}"
                                               required>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-link text-danger remove-budget-item">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <button type="button" id="addBudgetItem" class="btn btn-outline-primary">
                            <i class="bx bx-plus me-1"></i> Add Budget Item
                        </button>
                    </div>

                    <div class="d-flex justify-content-end mt-4 align-items-center">
                        <h5 class="mb-0 me-2">Total:</h5>
                        <div class="h4 text-primary mb-0">$<span id="budgetTotal">0.00</span></div>
                        <input type="hidden" name="budget[total]" id="budgetTotalInput" value="0">
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
                              required>{{ old('activity_request_remarks', $activity->activity_request_remarks) }}</textarea>
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
                              class="form-control form-control-lg @error('key_result_area') is-invalid @enderror"
                              rows="2"
                              required>{{ old('key_result_area', $activity->key_result_area) }}</textarea>
                    @error('key_result_area')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <a href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-secondary px-4">
                    <i class="bx bx-arrow-back me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm">
                    <i class="bx bx-save me-2"></i> Update Activity
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        let budgetIndex = {{ count($activity->budget['items'] ?? []) }};

        // Initialize date range picker
        $('.datepicker').flatpickr({
            dateFormat: "Y-m-d",
            minDate: "today",
            allowInput: true
        });

        // Calculate initial total
        calculateTotal();

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
            const areasCount = $('.budget-item').length;
            if (areasCount > 1) {
                $(this).closest('.budget-item').remove();
                calculateTotal();
            } else {
                alert('At least one budget item is required.');
            }
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
