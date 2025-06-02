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
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('matrices.activities.store', $matrix) }}" method="POST" id="activityForm">
                @csrf

                @includeIf('activities.form')

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="fund_type" class="form-label fw-semibold">Fund Type <span class="text-danger">*</span></label>
                        <select name="fund_type" id="fund_type" class="form-select" required>
                            <option value="">Select Fund Type</option>
                            @foreach($fundTypes as $type)
                                <option value="{{ $type->id }}" {{ old('fund_type') == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('fund_type')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="budget_codes" class="form-label fw-semibold">Budget Code(s) <span class="text-danger">*</span></label>
                        <select name="budget_codes[]" id="budget_codes" class="form-select @error('budget_codes') is-invalid @enderror" multiple required disabled>
                            <option value="" disabled>Select a fund type first</option>
                            @foreach(old('budget_codes', []) as $codeId)
                                @php $code = \App\Models\FundCode::find($codeId); @endphp
                                @if($code)
                                    <option value="{{ $code->id }}" selected>{{ $code->code }} - {{ $code->description }}</option>
                                @endif
                            @endforeach
                        </select>
                        <small class="text-muted">Hold Ctrl/Command to select multiple</small>
                        @error('budget_codes')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="key_result_link" class="form-label fw-semibold">Link to Key Result <span class="text-danger">*</span></label>
                        <select name="key_result_link" id="key_result_link" class="form-select @error('key_result_link') is-invalid @enderror" required>
                            <option value="">Select Key Result</option>
                            @foreach(json_decode($matrix->key_result_area ?? '[]') as $index => $kr)
                                <option value="{{ $index }}" {{ old('key_result_link') == $index ? 'selected' : '' }}>
                                    {{ $kr->title ?? 'Untitled' }}
                                </option>
                            @endforeach
                        </select>
                        @error('key_result_link')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mt-4">
                    <h6 class="fw-bold mb-2">Internal Participants Days</h6>
                    <table class="table table-bordered" id="participantsTable">
                        <thead>
                            <tr>
                                <th>Participant Name</th>
                                <th>No. of Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($staff as $member)
                                @php $isSelected = in_array($member->id, old('internal_participants', [])) @endphp
                                <tr data-participant-id="{{ $member->id }}" class="{{ $isSelected ? '' : 'd-none' }}">
                                    <td>{{ $member->name }}</td>
                                    <td>
                                        <input type="number" 
                                               name="participant_days[{{ $member->id }}]"
                                               class="form-control participant-days" 
                                               value="{{ old('participant_days.' . $member->id, '0') }}" 
                                               min="0"
                                               {{ $isSelected ? '' : 'disabled' }}>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @error('internal_participants')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    @error('participant_days')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4">
                    <a href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-secondary px-4">
                        <i class="bx bx-arrow-back me-1"></i> Cancel
                    </a>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bx bx-save me-1"></i> Save Activity
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('styles')
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    
    <style>
        /* Select2 Styling */
        .select2-container {
            width: 100% !important;
        }
        
        .select2-container--default .select2-selection--single,
        .select2-container--default .select2-selection--multiple {
            min-height: 38px;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            padding: 0.375rem 0.75rem;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 1.5;
            padding-left: 0;
            padding-right: 20px;
        }
        
        .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            padding: 0;
            line-height: 1.5;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
            right: 8px;
        }
        
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            margin: 2px;
            padding: 0 0.5rem;
        }
        
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #0d6efd;
        }
        
        .select2-container--default .select2-search--inline .select2-search__field {
            margin-top: 0;
            height: 28px;
        }
        
        /* Error state */
        .is-invalid ~ .select2-container .select2-selection {
            border-color: #dc3545 !important;
        }
        
        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }
    </style>
    @endpush

    @push('scripts')
        <!-- Select2 JS -->
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

        <script>
            $(document).ready(function () {
                // Initialize form elements
                let startDateInput = $('#date_from');
                let endDateInput = $('#date_to');
                let internalParticipants = $('#internal_participants');
                let participantsTable = $('#participantsTable tbody');
                let grandTotalInput = $('#grandBudgetTotalInput');
                let grandTotalDisplay = $('#grandBudgetTotal');
                let form = $('#activityForm');

                // Initialize Select2 for select elements that are not date pickers
                $('select:not(.datepicker)').each(function() {
                    const $select = $(this);
                    // Skip if already initialized or is a date picker
                    if ($select.hasClass('select2-hidden-accessible') || $select.hasClass('datepicker')) {
                        return;
                    }
                    
                    const isMultiple = $select.attr('multiple') === 'multiple';
                    const placeholder = $select.attr('placeholder') || (isMultiple ? 'Select options' : 'Select an option');
                    
                    $select.select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: placeholder,
                        allowClear: !$select.is('[required]'),
                        dropdownParent: $select.closest('.modal').length ? $select.closest('.modal') : document.body,
                        closeOnSelect: isMultiple ? false : true
                    });
                });

                // Initialize date picker
                $('input.datepicker').each(function() {
                    // Make sure this is not a Select2 element
                    if (!$(this).hasClass('select2-hidden-accessible')) {
                        $(this).datepicker({
                            format: 'yyyy-mm-dd',
                            autoclose: true,
                            todayHighlight: true,
                            orientation: 'bottom auto',
                            autocomplete: 'off' // Prevent browser autocomplete
                        });
                    }
                });

                // Update participants table when internal participants change
                internalParticipants.on('change', function() {
                    updateParticipantsTable();
                });


                // Function to update participants table
                function updateParticipantsTable() {
                    participantsTable.empty();
                    const selectedParticipants = internalParticipants.select2('data');
                    
                    if (selectedParticipants.length === 0) {
                        participantsTable.append('<tr><td colspan="3" class="text-center">No participants selected</td></tr>');
                        return;
                    }

                    selectedParticipants.forEach(function(participant) {
                        const participantId = participant.id;
                        const participantName = participant.text;
                        const days = $('input[name="participant_days[' + participantId + ']"]').val() || 0;
                        
                        const row = `
                            <tr>
                                <td>${participantName}</td>
                                <td>
                                    <input type="number" name="participant_days[${participantId}]" 
                                           class="form-control participant-days" 
                                           value="${days}" min="0" required>
                                </td>
                            </tr>
                        `;
                        participantsTable.append(row);
                    });
                }

                // Handle form submission
                form.on('submit', function(e) {
                    e.preventDefault();
                    
                    // Show loading state
                    const submitBtn = form.find('button[type="submit"]');
                    const originalBtnText = submitBtn.html();
                    submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
                    
                    // Clear previous error messages
                    $('.is-invalid').removeClass('is-invalid');
                    $('.invalid-feedback').remove();
                    $('.alert').remove();
                    
                    // Submit form via AJAX
                    $.ajax({
                        url: form.attr('action'),
                        type: 'POST',
                        data: form.serialize(),
                        dataType: 'json',
                        success: function(response) {
                            if (response && response.success) {
                                // Show success message
                                const successHtml = `
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="bx bx-check-circle me-2"></i> ${response.message || 'Activity created successfully!'}
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                `;
                                
                                form.prepend(successHtml);
                                
                                // Scroll to top to show success message
                                $('html, body').animate({
                                    scrollTop: 0
                                }, 500);
                                
                                // Redirect after a short delay
                                setTimeout(() => {
                                    window.location.href = response.redirect || '{{ route("matrices.show", $matrix) }}';
                                }, 1500);
                            } else {
                                // Handle unexpected response
                                const errorHtml = `
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="bx bx-error-circle me-2"></i> ${(response && response.message) || 'An error occurred. Please try again.'}
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                `;
                                
                                form.prepend(errorHtml);
                                
                                // Re-enable submit button
                                submitBtn.prop('disabled', false).html(originalBtnText);
                                
                                // Scroll to top to show error message
                                $('html, body').animate({
                                    scrollTop: 0
                                }, 500);
                            }
                        },
                        error: function(xhr) {
                            // Re-enable submit button
                            submitBtn.prop('disabled', false).html(originalBtnText);
                            
                            // Clear previous error messages
                            $('.is-invalid').removeClass('is-invalid');
                            $('.invalid-feedback').remove();
                            
                            let errorHtml = '';
                            
                            // Handle validation errors
                            if (xhr.status === 422) {
                                const response = xhr.responseJSON;
                                errorHtml = `
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <div class="d-flex align-items-center">
                                            <i class="bx bx-error-circle me-2"></i>
                                            <div>
                                                ${response.message ? `<p class="mb-2 fw-bold">${response.message}</p>` : ''}
                                                ${response.errors ? `
                                                    <ul class="mb-0">
                                                        ${Object.entries(response.errors).map(([field, messages]) => {
                                                            const errorMessage = Array.isArray(messages) ? messages[0] : messages;
                                                            const input = $(`[name="${field}"]`);
                                                            const select2Container = input.next('.select2-container');
                                                            
                                                            // Add error class to input and select2 container
                                                            input.addClass('is-invalid');
                                                            if (select2Container.length) {
                                                                select2Container.prev().addClass('is-invalid');
                                                            }
                                                            
                                                            // Add error message after the input
                                                            if (input.length) {
                                                                input.after(`<div class="invalid-feedback">${errorMessage}</div>`);
                                                            }
                                                            
                                                            return `<li>${errorMessage}</li>`;
                                                        }).join('')}
                                                    </ul>
                                                ` : ''}
                                            </div>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                `;
                            } else {
                                // Handle other types of errors
                                errorHtml = `
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <div class="d-flex align-items-center">
                                            <i class="bx bx-error-circle me-2"></i>
                                            <div>
                                                <p class="mb-0">An unexpected error occurred. Please try again later.</p>
                                                ${xhr.responseJSON && xhr.responseJSON.message ? 
                                                    `<p class="mb-0 mt-2"><small>Error: ${xhr.responseJSON.message}</small></p>` : ''}
                                            </div>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                `;
                            }
                            
                            // Show error messages at the top of the form
                            if ($('.alert.alert-danger').length) {
                                $('.alert.alert-danger').replaceWith($(errorHtml));
                            } else {
                                form.prepend(errorHtml);
                            }
                            
                            // Scroll to top to show error message
                            $('html, body').animate({
                                scrollTop: 0
                            }, 500);
                                
                                // Scroll to the first error
                                $('html, body').animate({
                                    scrollTop: $('.alert.alert-danger').offset().top - 20
                                }, 500);
                            } else {
                                // Handle other errors
                                const errorHtml = `
                                    <div class="alert alert-danger">
                                        <strong>An error occurred!</strong> Please try again later.
                                    </div>
                                `;
                                
                                if ($('.alert.alert-danger').length) {
                                    $('.alert.alert-danger').replaceWith($(errorHtml));
                                } else {
                                    form.prepend(errorHtml);
                                }
                            }
                        }
                    });
                });

                // Initialize participants table on page load
                updateParticipantsTable();


                // Handle fund type change
                $('#fund_type').on('change', function () {
                    const fundTypeId = $(this).val();
                    const divisionId = {{ user_session('division_id') }}; // Get division ID from session
                    const budgetCodesSelect = $('#budget_codes');

                    // Clear and disable the budget codes select while loading
                    budgetCodesSelect.empty().prop('disabled', true);

                    if (!fundTypeId) {
                        budgetCodesSelect.append($('<option>', {
                            value: '',
                            text: 'Select a fund type first'
                        }));
                        return;
                    }

                    // Show loading state
                    budgetCodesSelect.append($('<option>', {
                        value: '',
                        text: 'Loading budget codes...',
                        disabled: true,
                        selected: true
                    }));

                    // Fetch budget codes via AJAX
                    $.ajax({
                        url: '{{ route("budget-codes.by-fund-type") }}',
                        type: 'GET',
                        data: {
                            fund_type_id: fundTypeId,
                            division_id: divisionId
                        },
                        success: function (response) {
                            budgetCodesSelect.empty();

                            if (response.length > 0) {
                                $.each(response, function (index, code) {
                                    budgetCodesSelect.append($('<option>', {
                                        value: code.id,
                                        text: code.code + ' - ' + (code.description || 'No description')
                                    }));
                                });
                                budgetCodesSelect.prop('disabled', false);
                            } else {
                                budgetCodesSelect.append($('<option>', {
                                    value: '',
                                    text: 'No budget codes found for this fund type',
                                    disabled: true,
                                    selected: true
                                }));
                            }
                        },
                        error: function (xhr) {
                            console.error('Error fetching budget codes:', xhr);
                            budgetCodesSelect.empty().append($('<option>', {
                                value: '',
                                text: 'Error loading budget codes',
                                disabled: true,
                                selected: true
                            }));
                        }
                    });
                });


                // Trigger change on page load if fund type is already selected
                if (fundTypeSelect.val()) {
                    fundTypeSelect.trigger('change');
                }


                // Handle internal participants selection
                internalParticipants.on('change', function() {
                    const selectedIds = $(this).val() || [];
                    
                    // Show/hide participant rows and enable/disable inputs
                    participantsTable.find('tr').each(function() {
                        const participantId = $(this).data('participant-id');
                        const isSelected = selectedIds.includes(participantId.toString());
                        
                        $(this).toggleClass('d-none', !isSelected);
                        $(this).find('.participant-days').prop('disabled', !isSelected);
                        
                        // Reset days to 0 when unselected
                        if (!isSelected) {
                            $(this).find('.participant-days').val('0');
                        }
                    });
                });


                // Form submission handler
                form.on('submit', function(e) {
                    e.preventDefault();
                    
                    // Show loading state
                    const submitBtn = form.find('button[type="submit"]');
                    const originalBtnText = submitBtn.html();
                    submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
                    
                    // Submit form via AJAX
                    $.ajax({
                        url: form.attr('action'),
                        type: 'POST',
                        data: form.serialize(),
                        success: function(response) {
                            // Redirect to show page on success
                            window.location.href = response.redirect || '{{ route("matrices.show", $matrix) }}';
                        },
                        error: function(xhr) {
                            // Re-enable submit button
                            submitBtn.prop('disabled', false).html(originalBtnText);
                            
                            if (xhr.status === 422) {
                                // Handle validation errors
                                const errors = xhr.responseJSON.errors;
                                let errorMessages = [];
                                
                                // Show error messages in a user-friendly way
                                for (const [field, messages] of Object.entries(errors)) {
                                    errorMessages = errorMessages.concat(messages);
                                }
                                
                                Swal.fire({
                                    title: 'Validation Error',
                                    html: errorMessages.join('<br>'),
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                                
                                // Scroll to first error
                                $('html, body').animate({
                                    scrollTop: $('.is-invalid').first().offset().top - 100
                                }, 500);
                            } else {
                                // Handle other errors
                                Swal.fire({
                                    title: 'Error',
                                    text: 'An error occurred while saving the activity. Please try again.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        }
                    });
                });
                
                // Initialize any existing values
                @if(old('fund_type'))
                    fundTypeSelect.trigger('change');
                @endif
                
                @if(old('internal_participants'))
                    internalParticipants.trigger('change');
                @endif
                
                // Handle budget codes change
                $('#budget_codes').on('change', function () {
                    const selectedCodes = $(this).val();
                    const container = $('#budgetGroupContainer');
                    container.empty();
                    let grandTotal = 0;

                    selectedCodes.forEach(function (codeId) {
                        const label = $('#budget_codes option[value="' + codeId + '"]').text();
                        const section = $(
                            `<div class="card mt-4">
                                            <div class="card-header bg-light">
                                                <h6 class="fw-semibold">Budget Breakdown - ${label}</h6>
                                            </div>
                                            <div class="card-body">
                                                <input type="hidden" name="budget[groups][${codeId}][code_id]" value="${codeId}">
                                                <div id="budgetItems-${codeId}" class="budget-items"></div>
                                                <div class="text-end">
                                                    <button type="button" class="btn btn-success add-budget-item" data-code="${codeId}"><i class="bx bx-plus"></i> Add Item</button>
                                                </div>
                                                <div class="text-end mt-3">
                                                    <strong>Total for ${label}: $<span class="budget-total" id="total-${codeId}">0.00</span></strong>
                                                    <input type="hidden" name="budget[groups][${codeId}][total]" id="total-input-${codeId}" value="0">
                                                </div>
                                            </div>
                                        </div>`
                        );
                        container.append(section);
                        addBudgetItem(codeId);
                    });
                    calculateGrandTotal();
                });

                function addBudgetItem(codeId) {
                    const container = $(`#budgetItems-${codeId}`);
                    const index = container.children().length;
                    const row = $(
                        `<div class="row g-2 mb-3 budget-row">
                                                <div class="col-md-4">
                                                    <select name="budget[groups][${codeId}][items][${index}][description]" class="form-control item-description" required>
                                                        @foreach($costItems as $item)
                                                            <option value="{{ $item->name }}">{{ $item->name }} ({{ $item->cost_type }})</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="number" name="budget[groups][${codeId}][items][${index}][amount]" class="form-control amount-input" placeholder="Unit Cost" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="number" name="budget[groups][${codeId}][items][${index}][quantity]" class="form-control quantity-input" placeholder="Qty" value="1" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="number" name="budget[groups][${codeId}][items][${index}][days]" class="form-control days-input" placeholder="Days" value="1">
                                                </div>
                                                <div class="col-md-2 d-flex align-items-center">
                                                    <button type="button" class="btn btn-outline-danger remove-item"><i class="bx bx-trash"></i></button>
                                                </div>
                                            </div>`
                    );
                    container.append(row);
                    initializeSelect2(row.find('.item-description'));
                }

                $(document).on('click', '.add-budget-item', function () {
                    const codeId = $(this).data('code');
                    addBudgetItem(codeId);
                });

                $(document).on('click', '.remove-item', function () {
                    $(this).closest('.budget-row').remove();
                    calculateGrandTotal();
                });

                $(document).on('input', '.amount-input, .quantity-input, .days-input', function () {
                    calculateGrandTotal();
                });

                function calculateGrandTotal() {
                    let total = 0;
                    $('.budget-items').each(function () {
                        let groupTotal = 0;
                        $(this).find('.budget-row').each(function () {
                            const amt = parseFloat($(this).find('.amount-input').val()) || 0;
                            const qty = parseFloat($(this).find('.quantity-input').val()) || 1;
                            const days = parseFloat($(this).find('.days-input').val()) || 1;
                            groupTotal += amt * qty * days;
                        });
                        const groupId = $(this).attr('id').split('-')[1];
                        $(`#total-${groupId}`).text(groupTotal.toFixed(2));
                        $(`#total-input-${groupId}`).val(groupTotal.toFixed(2));
                        total += groupTotal;
                    });
                    grandTotalInput.val(total.toFixed(2));
                    grandTotalDisplay.text(total.toFixed(2));
                }
            });

        </script>


    @endpush
@endsection