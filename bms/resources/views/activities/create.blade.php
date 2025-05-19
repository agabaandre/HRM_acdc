@extends('layouts.app')

@section('title', 'Add Activity')

@section('header', "Add Activity - {$matrix->quarter} {$matrix->year}")

@section('header-actions')
<a href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back"></i> Back to Matrix
</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Activity Details</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('matrices.activities.store', $matrix) }}" method="POST" id="activityForm">
            @csrf

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="workplan_activity_code" class="form-label">Activity Code <span class="text-danger">*</span></label>
                        <input type="text"
                               name="workplan_activity_code"
                               id="workplan_activity_code"
                               class="form-control @error('workplan_activity_code') is-invalid @enderror"
                               value="{{ old('workplan_activity_code') }}"
                               required>
                        @error('workplan_activity_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="request_type_id" class="form-label">Request Type <span class="text-danger">*</span></label>
                        <select name="request_type_id"
                                id="request_type_id"
                                class="form-select select2 @error('request_type_id') is-invalid @enderror"
                                required>
                            <option value="">Select Request Type</option>
                            @foreach($requestTypes as $type)
                                <option value="{{ $type->id }}" {{ old('request_type_id') == $type->id ? 'selected' : '' }}>
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

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="activity_title" class="form-label">Activity Title <span class="text-danger">*</span></label>
                        <input type="text"
                               name="activity_title"
                               id="activity_title"
                               class="form-control @error('activity_title') is-invalid @enderror"
                               value="{{ old('activity_title') }}"
                               required>
                        @error('activity_title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="background" class="form-label">Background <span class="text-danger">*</span></label>
                        <textarea name="background"
                                  id="background"
                                  class="form-control @error('background') is-invalid @enderror"
                                  rows="3"
                                  required>{{ old('background') }}</textarea>
                        @error('background')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="date_from" class="form-label">Start Date <span class="text-danger">*</span></label>
                        <input type="text"
                               name="date_from"
                               id="date_from"
                               class="form-control datepicker @error('date_from') is-invalid @enderror"
                               value="{{ old('date_from') }}"
                               required>
                        @error('date_from')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="date_to" class="form-label">End Date <span class="text-danger">*</span></label>
                        <input type="text"
                               name="date_to"
                               id="date_to"
                               class="form-control datepicker @error('date_to') is-invalid @enderror"
                               value="{{ old('date_to') }}"
                               required>
                        @error('date_to')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="staff_id" class="form-label">Responsible Staff <span class="text-danger">*</span></label>
                        <select name="staff_id"
                                id="staff_id"
                                class="form-select select2 @error('staff_id') is-invalid @enderror"
                                required>
                            <option value="">Select Staff</option>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}" {{ old('staff_id') == $member->id ? 'selected' : '' }}>
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
                    <div class="form-group">
                        <label for="internal_participants" class="form-label">Internal Participants <span class="text-danger">*</span></label>
                        <select name="internal_participants[]"
                                id="internal_participants"
                                class="form-select select2 @error('internal_participants') is-invalid @enderror"
                                multiple
                                required>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}" {{ in_array($member->id, old('internal_participants', [])) ? 'selected' : '' }}>
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
                        <label for="total_participants" class="form-label">Total Participants <span class="text-danger">*</span></label>
                        <input type="number"
                               name="total_participants"
                               id="total_participants"
                               class="form-control @error('total_participants') is-invalid @enderror"
                               value="{{ old('total_participants') }}"
                               min="1"
                               required>
                        @error('total_participants')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="form-group">
                        <label for="location_id" class="form-label">Locations <span class="text-danger">*</span></label>
                        <select name="location_id[]"
                                id="location_id"
                                class="form-select select2 @error('location_id') is-invalid @enderror"
                                multiple
                                required>
                            <option value="Kampala">Kampala</option>
                            <option value="Entebbe">Entebbe</option>
                            <option value="Jinja">Jinja</option>
                            <option value="Mbarara">Mbarara</option>
                            <option value="Gulu">Gulu</option>
                        </select>
                        @error('location_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Budget <span class="text-danger">*</span></label>
                <div id="budgetItems">
                    <div class="budget-item mb-3">
                        <div class="row">
                            <div class="col-md-5">
                                <input type="text"
                                       name="budget[items][0][description]"
                                       class="form-control"
                                       placeholder="Item description"
                                       required>
                            </div>
                            <div class="col-md-3">
                                <input type="number"
                                       name="budget[items][0][amount]"
                                       class="form-control amount-input"
                                       placeholder="Amount"
                                       required>
                            </div>
                            <div class="col-md-3">
                                <input type="number"
                                       name="budget[items][0][quantity]"
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
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <button type="button" class="btn btn-outline-success" id="addBudgetItem">
                            <i class="bx bx-plus"></i> Add Budget Item
                        </button>
                    </div>
                    <div class="col-md-6 text-end">
                        <h5>Total: $<span id="budgetTotal">0.00</span></h5>
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

            <div class="text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bx bx-save"></i> Create Activity
                </button>
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
