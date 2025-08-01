@extends('layouts.app')

@section('title', 'Edit Activity')

@section('header', "Edit Activity - {$matrix->quarter} {$matrix->year}")

@section('header-actions')
<a href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back"></i> Back to Matrix
</a>
@endsection

@section('content')
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0 text-success">
                <i class="fas fa-calendar-plus me-2"></i> Activity Details
            </h5>
        </div>

        <div class="card-body p-4">
            <form action="{{ route('matrices.activities.update', [$matrix, $activity]) }}" method="POST" id="activityForm" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                @includeIf('activities.form')

                <div class="row g-4 mt-2">
                    <div class="col-md-4 fund_type">
                        <label for="fund_type" class="form-label fw-semibold">
                            <i class="fas fa-hand-holding-usd me-1 text-success"></i> Fund Type <span class="text-danger">*</span>
                        </label>
                        <select name="fund_type" id="fund_type" class="form-select border-success" required >
                            <option value="">Select Fund Type</option>
                            @foreach($fundTypes as $type)
                                <option value="{{ $type->id }}" {{ old('fund_type', $activity->fund_type_id) == $type->id ? 'selected' : '' }}>{{ ucfirst($type->name) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="budget_codes" class="form-label fw-semibold">
                            <i class="fas fa-wallet me-1 text-success"></i> Budget Code(s) <span class="text-danger">*</span>
                        </label>
                        <select name="budget_codes[]" id="budget_codes" class="form-select border-success" multiple disabled>
                            <option value="" selected disabled>Select a fund type first</option>
                        </select>
                        <small class="text-muted">Select up to 2 codes</small>
                    </div>

                    <div class="col-md-2 activity_code" style="display: none;">
                        <label for="activity_code" class="form-label fw-semibold">
                            <i class="fas fa-hand-holding-usd me-1 text-success"></i> Activity Code <span class="text-danger"></span>
                        </label>
                        <input name="activity_code" id="activity_code" class="form-control border-success" value="{{ old('activity_code', $activity->workplan_activity_code) }}" />
                    </div>

                    <div class="col-md-4">
                        <label for="key_result_link" class="form-label fw-semibold">
                            <i class="fas fa-link me-1 text-success"></i> Link to Key Result <span class="text-danger">*</span>
                        </label>
                        <select name="key_result_link" id="key_result_link" class="form-select border-success" required>
                            <option value="">Select Key Result</option>
                            @php
                                $keyResults = is_array($matrix->key_result_area) 
                                            ? $matrix->key_result_area 
                                            : json_decode($matrix->key_result_area ?? '[]', true);
                            @endphp
                            @foreach($keyResults as $index => $kr)
                                <option value="{{ $index }}" {{ old('key_result_link', $activity->key_result_area) == $index ? 'selected' : '' }}>
                                    {{ $kr['description'] ?? 'No Description' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4 offset-md-8">
                        <label for="total_participants" class="form-label fw-semibold">
                            <i class="fas fa-users me-1 text-success"></i> Total Participants
                        </label>
                        <input type="text" id="total_participants_display" class="form-control bg-white border-success fw-bold" readonly value="{{ old('total_participants', $activity->total_participants) }}">
                        <input type="hidden" name="total_participants" id="total_participants" value="{{ old('total_participants', $activity->total_participants) }}">
                    </div>
                </div>

                <div id="externalParticipantsWrapper"></div>

                <div id="budgetGroupContainer" class="mt-4"></div>

                <!-- Attachments Section -->
                <div class="mt-5">
                    <h5 class="fw-bold text-success mb-3">
                        <i class="fas fa-paperclip me-2"></i> Attachments
                    </h5>
                    
                    @if($attachments && count($attachments) > 0)
                        <div class="mb-4">
                            <h6 class="text-muted mb-3">Current Attachments:</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Document Type</th>
                                            <th>File Name</th>
                                            <th>Size</th>
                                            <th>Uploaded</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($attachments as $index => $attachment)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $attachment['type'] ?? 'Document' }}</td>
                                                <td>{{ $attachment['original_name'] ?? 'Unknown' }}</td>
                                                <td>{{ isset($attachment['size']) ? round($attachment['size']/1024, 2).' KB' : 'N/A' }}</td>
                                                <td>{{ isset($attachment['uploaded_at']) ? \Carbon\Carbon::parse($attachment['uploaded_at'])->format('Y-m-d H:i') : 'N/A' }}</td>
                                                <td>
                                                    <a href="{{ Storage::url($attachment['path']) }}" target="_blank" class="btn btn-sm btn-info">
                                                        <i class="bx bx-show"></i> View
                                                    </a>
                                                    <a href="{{ Storage::url($attachment['path']) }}" download="{{ $attachment['original_name'] }}" class="btn btn-sm btn-success">
                                                        <i class="bx bx-download"></i> Download
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-danger btn-sm" id="addAttachment">Add New</button>
                        <button type="button" class="btn btn-secondary btn-sm" id="removeAttachment">Remove</button>
                        <button type="button" class="btn btn-warning btn-sm" id="retryBudgetInit" style="display: none;">Retry Budget Init</button>
                    </div>
                    <div class="row g-3" id="attachmentContainer">
                        @if($attachments && count($attachments) > 0)
                            @foreach($attachments as $index => $attachment)
                                <div class="col-md-4 attachment-block">
                                    <label class="form-label">Document Type*</label>
                                    <input type="text" name="attachments[{{ $index }}][type]" class="form-control" value="{{ $attachment['type'] ?? '' }}" required>
                                    <input type="file" name="attachments[]" class="form-control mt-1 attachment-input" accept=".pdf,.jpg,.jpeg,.png,.ppt,.pptx,.xls,.xlsx,.doc,.docx,image/*">
                                    <small class="text-muted">Current: {{ $attachment['original_name'] ?? 'No file' }}</small>
                                    <small class="text-muted d-block">Leave empty to keep existing file</small>
                                </div>
                            @endforeach
                        @else
                            <div class="col-md-4 attachment-block">
                                <label class="form-label">Document Type*</label>
                                <input type="text" name="attachments[0][type]" class="form-control" required>
                                <input type="file" name="attachments[]" class="form-control mt-1 attachment-input" accept=".pdf,.jpg,.jpeg,.png,.ppt,.pptx,.xls,.xlsx,.doc,.docx,image/*" required>
                                <small class="text-muted">Max size: 10MB. Allowed: PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, DOCX</small>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <div class="border p-4 rounded-3 shadow-sm bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-coins me-2 text-success"></i>
                            Total Budget: <span class="text-success fw-bold">$<span id="grandBudgetTotal">0.00</span></span>
                        </h5>
                        <input type="hidden" name="budget[grand_total]" id="grandBudgetTotalInput" value="0">
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center border-top pt-4 mt-5 gap-3">
                    <a href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-secondary px-4">
                        <i class="bx bx-arrow-back me-1"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-success btn-lg px-5" id="submitBtn">
                        <i class="bx bx-check-circle me-1"></i> Update Activity
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
const staffData = @json($allStaffGroupedByDivision);
const oldParticipants = @json(old('internal_participants', []));
const oldTravel = @json(old('international_travel', []));
const existingParticipants = @json($internalParticipants);
const existingBudgetItems = @json($budgetItems);
console.log('Budget items passed from controller:', existingBudgetItems);

$(document).ready(function () {
    // AJAX Form Submission
    $('#activityForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = $('#submitBtn');
        const originalBtnText = submitBtn.html();
        
        // Disable submit button and show loading state
        submitBtn.prop('disabled', true)
            .html('<i class="bx bx-loader-alt bx-spin me-1"></i> Updating...');
        
        // Create FormData object to handle file uploads
        const formData = new FormData(this);
        
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    show_notification(response.msg || 'Activity updated successfully!', 'success');
                    
                    // Redirect to the activity show page after a short delay
                    setTimeout(function() {
                        window.location.href = response.redirect_url || '{{ route("matrices.activities.show", [$matrix, $activity]) }}';
                    }, 1500);
                } else {
                    show_notification(response.msg || 'An error occurred while updating the activity.', 'error');
                    submitBtn.prop('disabled', false).html(originalBtnText);
                }
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while updating the activity.';
                
                if (xhr.status === 422) {
                    // Validation errors
                    const errors = xhr.responseJSON.errors;
                    if (errors) {
                        // Clear previous error states
                        $('.is-invalid').removeClass('is-invalid');
                        $('.invalid-feedback').remove();
                        
                        // Display validation errors
                        Object.keys(errors).forEach(function(field) {
                            let fieldElement = $(`[name="${field}"]`);
                            
                            // Handle array fields like location_id[]
                            if (!fieldElement.length && field.includes('[')) {
                                const baseField = field.split('[')[0];
                                fieldElement = $(`[name^="${baseField}["]`);
                            }
                            
                            // Handle nested fields like budget[code][index][field]
                            if (!fieldElement.length && field.includes('[')) {
                                const parts = field.split('[');
                                const baseField = parts[0];
                                fieldElement = $(`[name*="${baseField}["]`);
                            }
                            
                            if (fieldElement.length) {
                                fieldElement.addClass('is-invalid');
                                
                                // Add error message below the field
                                const errorDiv = $('<div class="invalid-feedback"></div>').text(errors[field][0]);
                                fieldElement.after(errorDiv);
                                
                                // Scroll to first error field
                                if (Object.keys(errors).indexOf(field) === 0) {
                                    $('html, body').animate({
                                        scrollTop: fieldElement.offset().top - 100
                                    }, 500);
                                }
                            }
                        });
                        
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
    });

    // Restore existing participants and their international travel state
    if (existingParticipants && existingParticipants.length > 0) {
        const participantIds = existingParticipants.map(p => p.staff.staff_id);
        $('#internal_participants').val(participantIds).trigger('change');
        
        // Restore international travel checkboxes after participants are loaded
        setTimeout(() => {
            existingParticipants.forEach(participant => {
                const checkbox = $(`input[name="international_travel[${participant.staff.staff_id}]"]`);
                if (checkbox.length) {
                    checkbox.prop('checked', participant.international_travel == 1);
                }
            });
        }, 100);
    }

    // Restore old data if form was reloaded after validation errors
    if (oldParticipants && oldParticipants.length > 0) {
        $('#internal_participants').val(oldParticipants).trigger('change');
        
        // Restore international travel checkboxes after participants are loaded
        setTimeout(() => {
            oldParticipants.forEach(participantId => {
                const checkbox = $(`input[name="international_travel[${participantId}]"]`);
                if (checkbox.length && oldTravel && oldTravel[participantId]) {
                    checkbox.prop('checked', true);
                }
            });
        }, 100);
    }

    // Fund type change handler
    $('#fund_type').change(function(event){
        let selectedText = $('#fund_type option:selected').text();
        let selectedId = $('#fund_type').val();

        // Show for intramural or extramural, hide for external source (id=3)
        if (
            selectedText.toLocaleLowerCase().indexOf("intramural") > -1 ||
            selectedText.toLocaleLowerCase().indexOf("extramural") > -1
        ) {
            if (selectedId == 3) {
                // Hide for external source
                $('#activity_code').val(""); // clear value
                $('.activity_code').hide();
                // Hide and disable budget codes, remove required
                $('#budget_codes').val("").prop('disabled', true).prop('required', false).closest('.col-md-4').hide();
            } else {
            $('.activity_code').show();
                // Show and enable budget codes, add required
                $('#budget_codes').prop('disabled', false).prop('required', true).closest('.col-md-4').show();
        }
        } else {
            $('#activity_code').val(""); // clear value
            $('.activity_code').hide();
            // Hide and disable budget codes, remove required
            $('#budget_codes').val("").prop('disabled', true).prop('required', false).closest('.col-md-4').hide();
        }
    });

    // Date validation and participant management functions
    function isValidActivityDates() {
        return $('#date_from').val() && $('#date_to').val();
    }

    function updateTotalParticipants() {
        let internalCount = 0;

        $('#participantsTableBody tr').each(function () {
            if (!$(this).find('td').hasClass('text-muted')) {
                internalCount++;
            }
        });

        const externalCount = parseInt($('#total_external_participants').val()) || 0;
        const total = internalCount + externalCount;

        $('#total_participants_display').val(total);
        $('#total_participants').val(total);
    }

    function getActivityDays(startDate, endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        const msPerDay = 1000 * 60 * 60 * 24;
        return Math.max(Math.ceil((end - start) / msPerDay) + 1, 1);
    }

    function appendToInternalParticipantsTable(staffList) {
        const mainStart = $('#date_from').val();
        const mainEnd = $('#date_to').val();
        const days = getActivityDays(mainStart, mainEnd);
        const tableBody = $('#participantsTableBody');

        if (tableBody.find('td').length === 1 && tableBody.find('td').hasClass('text-muted')) {
            tableBody.empty();
        }

        staffList.forEach(({ id, name }) => {
            if (!tableBody.find(`input[name="participant_days[${id}]"]`).length) {
                const row = $(`
                    <tr data-participant-id="${id}">
                        <td>${name}</td>
                        <td><input type="text" name="participant_start[${id}]" class="form-control date-picker participant-start" value="${mainStart}"></td>
                        <td><input type="text" name="participant_end[${id}]" class="form-control date-picker participant-end" value="${mainEnd}"></td>
                        <td><input type="number" name="participant_days[${id}]" class="form-control participant-days" value="${days}" readonly></td>
                        <td class="text-center">
                            <div class="form-check d-flex justify-content-center">
                                <input type="checkbox" name="international_travel[${id}]" class="form-check-input" value="1" checked>
                                <label class="form-check-label ms-2">Yes</label>
                            </div>
                        </td>
                    </tr>
                `);
                tableBody.append(row);

                // Flatpickr initialization
                const $start = row.find('.participant-start');
                const $end = row.find('.participant-end');
                const $days = row.find('.participant-days');

                $start.flatpickr({
                    dateFormat: 'Y-m-d',
                    defaultDate: mainStart,
                    onChange: function () {
                        const startDate = $start.val();
                        const endDate = $end.val();
                        if (startDate && endDate) {
                            $days.val(getActivityDays(startDate, endDate));
                            updateTotalParticipants();
                        }
                    }
                });

                $end.flatpickr({
                    dateFormat: 'Y-m-d',
                    defaultDate: mainEnd,
                    onChange: function () {
                        const startDate = $start.val();
                        const endDate = $end.val();
                        if (startDate && endDate) {
                            $days.val(getActivityDays(startDate, endDate));
                            updateTotalParticipants();
                        }
                    }
                });
            }
        });

        updateTotalParticipants();
    }

    // Participant selection change handler
    $('#internal_participants').on('change', function () {
        const selectedIds = $(this).val() || [];
        const staffList = selectedIds.map(id => {
            return {
                id: id,
                name: $(`#internal_participants option[value="${id}"]`).text()
            };
        });

        appendToInternalParticipantsTable(staffList);
        updateTotalParticipants();
    });

    // Update total participants on any input change
    $(document).on('input change', '#participantsTableBody input, #internal_participants, .staff-names, #total_external_participants', function () {
        updateTotalParticipants();
    });

    // External participants management
    $('#addDivisionBlock').click(function () {
        if (!isValidActivityDates()) {
            show_notification("Please select both Start Date and End Date before adding division staff.", "warning");
            return;
        }

        const divisions = Object.keys(staffData);
        let divisionOptions = '<option value="">Select Division</option>';
        divisions.forEach(div => {
            divisionOptions += `<option value="${div}">${div}</option>`;
        });

        const block = `
            <div class="division-block border p-3 mb-3 rounded bg-light position-relative">
                <button type="button" class="btn btn-danger remove-division-block position-absolute end-0 top-0 m-3">
                    <i class="fas fa-trash"></i>
                </button>
                <div class="row g-3 align-items-center">
                    <div class="col-md-3">
                        <label class="form-label">Division</label>
                        <select class="form-select division-select">${divisionOptions}</select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Division Staff By</label>
                        <select class="form-select filter-type" disabled>
                            <option value="title" disabled>Job Title</option>
                            <option value="name" selected>Name</option>
                            <option value="number" disabled>Number of Staff</option>
                        </select>
                    </div>
                    <div class="col-md-3 job-title-col d-none">
                        <label class="form-label">Job Title(s)</label>
                        <select class="form-select job-titles" multiple disabled></select>
                    </div>
                    <div class="col-md-3 staff-name-col">
                        <label class="form-label">Staff Info</label>
                        <select class="form-select staff-names" multiple></select>
                    </div>
                </div>
            </div>`;

        const $block = $(block);
        $('#externalParticipantsWrapper').append($block);

        $block.find('.division-select, .staff-names').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'Select'
        });
    });

    $(document).on('click', '.remove-division-block', function () {
        $(this).closest('.division-block').remove();
    });

    $(document).on('change', '.division-select', function () {
        const division = $(this).val();
        const container = $(this).closest('.division-block');
        const staffSelect = container.find('.staff-names');
        const staff = staffData[division] || [];

        staffSelect.empty().append(
            staff.map(s => `<option value="${s.staff_id}">${s.fname} ${s.lname}</option>`)
        ).trigger('change');
    });

    $(document).on('change', '.staff-names', function () {
        const container = $(this).closest('.division-block');
        const division = container.find('.division-select').val();
        const selected = $(this).val() || [];
        const staff = staffData[division] || [];

        const selectedStaff = staff
            .filter(s => selected.includes(s.staff_id.toString()))
            .map(s => ({
                id: s.staff_id,
                name: `${s.fname} ${s.lname}`
            }));

        appendToInternalParticipantsTable(selectedStaff);
    });

    // Budget management functions
    function createBudgetRow(codeId, index) {
        return `
        <tr>
            <td>
                <select name="budget[${codeId}][${index}][cost]" 
                        class="form-select select-cost-item" 
                        required 
                        data-placeholder="Select Cost Item">
                    <option></option>
                    @foreach($costItems as $item)
                        <option value="{{ $item->name }}">{{ $item->name }} ({{ $item->cost_type }})</option>
                    @endforeach
                </select>
            </td>
            <td><input type="number" name="budget[${codeId}][${index}][unit_cost]" class="form-control unit-cost" step="0.01" min="0"></td>
            <td><input type="number" name="budget[${codeId}][${index}][units]" class="form-control units" min="0"></td>
            <td><input type="number" name="budget[${codeId}][${index}][days]" class="form-control days" min="0"></td>
            <td><input type="text" class="form-control-plaintext total fw-bold text-success text-center" readonly value="0.00"></td>
            <td>
                <input type="text" 
                       name="budget[${codeId}][${index}][description]" 
                       class="form-control" 
                       placeholder="Description (optional)">
            </td>
            <td><button type="button" class="btn btn-danger remove-row"><i class="fas fa-trash"></i></button></td>
        </tr>`;
    }

    // Budget codes loading and management
    const divisionId = {{ user_session('division_id') }};

    $('#fund_type').on('change', function () {
        const fundTypeId = $(this).val();
        const budgetCodesSelect = $('#budget_codes');
        console.log('Fund type changed to:', fundTypeId);
        
        budgetCodesSelect.empty().prop('disabled', true).append('<option disabled selected>Loading...</option>');

        $.get('{{ route("budget-codes.by-fund-type") }}', {
            fund_type_id: fundTypeId,
            division_id: divisionId
        }, function (data) {
            console.log('Budget codes loaded:', data);
            budgetCodesSelect.empty();
            if (data.length) {
                data.forEach(code => {
                    const label = `${code.code} | ${code.funder_name || 'No Funder'} | $${parseFloat(code.budget_balance).toLocaleString()}`;
                    budgetCodesSelect.append(
                        `<option value="${code.id}" data-balance="${code.budget_balance}">${label}</option>`
                    );
                });
                budgetCodesSelect.prop('disabled', false);
                console.log('Budget codes loaded successfully');
            } else {
                budgetCodesSelect.append('<option disabled selected>No budget codes found</option>');
                console.warn('No budget codes found for fund type:', fundTypeId);
            }
        });
    });

    $('#budget_codes').on('change', function () {
        const selected = $(this).find('option:selected');
        const container = $('#budgetGroupContainer');
        console.log('Budget codes changed. Selected:', selected.map(function() { return $(this).val(); }).get());
        
        container.empty();

        selected.each(function () {
            const codeId = $(this).val();
            const label = $(this).text();
            const balance = $(this).data('balance');
            console.log('Creating budget card for code:', codeId, 'with balance:', balance);
            
            // Extract the budget code from the label (format: "CODE | Funder | $Balance")
            const codeMatch = label.match(/^([^|]+)/);
            const budgetCode = codeMatch ? codeMatch[1].trim() : `Code ${codeId}`;

            const cardHtml = `
                <div class="card mt-4">
                    <div class="card-header bg-light">
                        <h6 class="fw-semibold mb-0">
                            <span class="badge bg-primary me-2">${budgetCode}</span>
                            <span class="float-end text-muted">
                                Balance: $<span class="text-danger">${parseFloat(balance).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                            </span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered text-center align-middle">
                            <thead class="table-light fw-bold">
                                <tr>
                                    <th>Cost</th>
                                    <th>Unit Cost</th>
                                    <th>Units/People</th>
                                    <th>Days/Frequency</th>
                                    <th>Total</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody class="budget-body" data-code="${codeId}">
                                ${createBudgetRow(codeId, 0)}
                            </tbody>
                        </table>
                        <div class="text-end mt-2">
                            <button type="button" class="btn btn-primary btn-sm add-row" data-code="${codeId}">
                                <i class="fas fa-plus"></i> Add
                            </button>
                            <strong class="ms-3">Sub Total: $<span class="subtotal" data-code="${codeId}">0.00</span></strong>
                        </div>
                    </div>
                </div>
            `;

            container.append(cardHtml);

            container.find('.select-cost-item').select2({
                theme: 'bootstrap4',
                width: '100%',
                placeholder: 'Select Cost Item',
                allowClear: true
            });
        });
    });

    $(document).on('click', '.add-row', function () {
        const codeId = $(this).data('code');
        const tbody = $(`.budget-body[data-code="${codeId}"]`);
        const index = tbody.find('tr').length;
        const newRow = $(createBudgetRow(codeId, index));

        tbody.append(newRow);

        // Initialize select2 on the new cost item select
        newRow.find('.select-cost-item').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'Select Cost Item',
            allowClear: true
        });
    });

    $(document).on('click', '.remove-row', function () {
        $(this).closest('tr').remove();
        updateAllTotals();
    });

    $(document).on('input', '.unit-cost, .units, .days', function () {
        const row = $(this).closest('tr');
        const unitCost = parseFloat(row.find('.unit-cost').val()) || 0;
        const units = parseFloat(row.find('.units').val()) || 0;
        const days = parseFloat(row.find('.days').val()) || 0;
        const total = (unitCost * units * days).toFixed(2);
        row.find('.total').val(total);
        updateAllTotals();
    });

    function updateAllTotals() {
        let grand = 0;
        let hasExceededBudget = false;
        
        $('.budget-body').each(function () {
            const code = $(this).data('code');
            let subtotal = 0;
            $(this).find('tr').each(function () {
                subtotal += parseFloat($(this).find('.total').val()) || 0;
            });
            
            // Get the budget balance for this code
            const balanceElement = $(`#budget_codes option[value="${code}"]`);
            const budgetBalance = parseFloat(balanceElement.data('balance')) || 0;
            
            // Check if subtotal exceeds budget balance
            if (subtotal > budgetBalance) {
                hasExceededBudget = true;
                $(`.subtotal[data-code="${code}"]`).text(subtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }))
                    .addClass('text-danger fw-bold');
                
                // Show warning message
                const card = $(this).closest('.card');
                let warningDiv = card.find('.budget-warning');
                if (warningDiv.length === 0) {
                    warningDiv = $(`<div class="alert alert-danger mt-2 budget-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Budget exceeded! Available: $${budgetBalance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                    </div>`);
                    card.find('.card-body').append(warningDiv);
                }
            } else {
                $(`.subtotal[data-code="${code}"]`).text(subtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }))
                    .removeClass('text-danger fw-bold');
                
                // Remove warning if exists
                const card = $(this).closest('.card');
                card.find('.budget-warning').remove();
            }
            
            grand += subtotal;
        });
        
        $('#grandBudgetTotal').text(grand.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        $('#grandBudgetTotalInput').val(grand.toFixed(2));
        
        // Update submit button state
        const submitBtn = $('button[type="submit"]');
        if (hasExceededBudget) {
            submitBtn.prop('disabled', true).addClass('btn-danger').removeClass('btn-success')
                .html('<i class="bx bx-x-circle me-1"></i> Budget Exceeded - Cannot Save');
        } else {
            submitBtn.prop('disabled', false).removeClass('btn-danger').addClass('btn-success')
                .html('<i class="bx bx-check-circle me-1"></i> Update Activity');
        }
    }



    // Initialize Select2 for form fields
    $('#internal_participants').select2({
        placeholder: 'Select Internal Participants',
        width: '100%'
    });

    $('#location_id').select2({
        placeholder: "Select Location/Venue",
        allowClear: true,
        width: '100%'
    });

    $('#budget_codes').select2({ 
        maximumSelectionLength: 2, 
        width: '100%' 
    });

    $('#responsible_person_id').select2({
        placeholder: 'Select Responsible Person',
        width: '100%'
    });

    $('#request_type_id').select2({
        placeholder: 'Select Request Type',
        width: '100%'
    });

    // Initialize date pickers
    $('.datepicker').flatpickr({
        dateFormat: 'Y-m-d',
        allowInput: true
    });

    // Initialize existing participants table if there are existing participants
    if (existingParticipants && existingParticipants.length > 0) {
        const participantIds = existingParticipants.map(p => p.staff.staff_id);
        $('#internal_participants').val(participantIds).trigger('change');
        
        // Restore international travel checkboxes after participants are loaded
        setTimeout(() => {
            existingParticipants.forEach(participant => {
                const checkbox = $(`input[name="international_travel[${participant.staff.staff_id}]"]`);
                if (checkbox.length) {
                    checkbox.prop('checked', participant.international_travel == 1);
                }
            });
        }, 100);
    }

    // Initialize form fields with existing data
    function initializeExistingData() {
        // Set activity code visibility based on fund type
        const fundTypeText = $('#fund_type option:selected').text();
        if (fundTypeText.toLowerCase().indexOf("intramural") > -1) {
            $('.activity_code').show();
            $('.fund_type').removeClass('col-md-4').addClass('col-md-2');
        }

        // Load budget codes if fund type is selected
        if ($('#fund_type').val()) {
            // Enable budget codes dropdown and load codes
            $('#budget_codes').prop('disabled', false);
            
            // Trigger fund type change to load budget codes
            $('#fund_type').trigger('change');
            
            // If there are existing budget items, ensure they're properly loaded
            if (existingBudgetItems && Object.keys(existingBudgetItems).length > 0) {
                console.log('Existing budget items found:', existingBudgetItems);
                
                // Wait for budget codes to load, then restore existing selections
                setTimeout(() => {
                    console.log('Restoring budget code selections...');
            Object.entries(existingBudgetItems).forEach(([codeId, items]) => {
                // Select the budget code
                        const option = $(`#budget_codes option[value="${codeId}"]`);
                        if (option.length) {
                            option.prop('selected', true);
                            console.log(`Selected budget code: ${codeId}`);
                        } else {
                            console.warn(`Budget code option not found: ${codeId}`);
                        }
                    });
                
                // Trigger budget codes change to create budget cards
                $('#budget_codes').trigger('change');
                
                    // Restore budget items in the cards after they're created
                setTimeout(() => {
                        console.log('Restoring budget items in cards...');
                        Object.entries(existingBudgetItems).forEach(([codeId, items]) => {
                    const tbody = $(`.budget-body[data-code="${codeId}"]`);
                            console.log(`Looking for tbody with data-code="${codeId}":`, tbody.length);
                            
                    if (tbody.length && items.length > 0) {
                        tbody.empty();
                        items.forEach((item, index) => {
                                    console.log(`Creating budget row for item:`, item);
                            const row = createBudgetRow(codeId, index);
                            tbody.append(row);
                            
                            // Set values
                            const newRow = tbody.find('tr').last();
                            newRow.find('select[name*="[cost]"]').val(item.cost).trigger('change');
                            newRow.find('input[name*="[unit_cost]"]').val(item.unit_cost);
                            newRow.find('input[name*="[units]"]').val(item.units);
                            newRow.find('input[name*="[days]"]').val(item.days);
                            newRow.find('input[name*="[description]"]').val(item.description);
                            
                            // Calculate total
                            const unitCost = parseFloat(item.unit_cost) || 0;
                            const units = parseFloat(item.units) || 0;
                            const days = parseFloat(item.days) || 0;
                            const total = (unitCost * units * days).toFixed(2);
                            newRow.find('.total').val(total);
                        });
                                
                                // Initialize select2 for cost items
                                tbody.find('.select-cost-item').select2({
                                    theme: 'bootstrap4',
                                    width: '100%',
                                    placeholder: 'Select Cost Item',
                                    allowClear: true
                        });
                        
                        updateAllTotals();
                                console.log(`Budget items restored for code ${codeId}`);
                            } else {
                                console.warn(`Tbody not found or no items for code ${codeId}`);
                    }
            });
        }, 500);
                }, 1500);
            } else {
                console.log('No existing budget items found');
            }
        } else {
            console.log('No fund type selected');
        }

        // Update total participants display
        updateTotalParticipants();
    }

    // Call initialization after a short delay to ensure all elements are loaded
    setTimeout(initializeExistingData, 100);
    
    // Fallback: Retry budget initialization if it fails
    setTimeout(() => {
        if (existingBudgetItems && Object.keys(existingBudgetItems).length > 0) {
            const budgetCards = $('.budget-body');
            if (budgetCards.length === 0) {
                console.log('Budget cards not found, retrying initialization...');
                initializeExistingData();
                
                // Show debug button if still no budget cards after retry
                setTimeout(() => {
                    if ($('.budget-body').length === 0) {
                        $('#retryBudgetInit').show();
                        console.warn('Budget initialization failed, debug button shown');
                    }
                }, 2000);
            }
        }
    }, 3000);
    
    // Manual trigger for budget initialization (for debugging)
    $(document).on('click', '#retryBudgetInit', function() {
        console.log('Manual budget initialization triggered');
        initializeExistingData();
    });
    
    // File validation
    $(document).on('change', '.attachment-input', function () {
        const fileInput = this;
        const file = fileInput.files[0];
        
        if (!file) {
            return;
        }
        
        const fileName = file.name;
        const fileSize = file.size;
        const maxSize = 10 * 1024 * 1024; // 10MB in bytes
        const ext = fileName.split('.').pop().toLowerCase();
        const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'ppt', 'pptx', 'xls', 'xlsx', 'doc', 'docx'];

        // Check file extension
        if (!allowedExtensions.includes(ext)) {
            show_notification("Only PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, or DOCX files are allowed.", "warning");
            $(fileInput).val(''); // Clear invalid file
            return;
        }
        
        // Check file size
        if (fileSize > maxSize) {
            show_notification("File size must be less than 10MB.", "warning");
            $(fileInput).val(''); // Clear invalid file
            return;
        }
        
        // Show success message
        show_notification(`File "${fileName}" selected successfully.`, "success");
    });

    // Attachment management
    let attachmentIndex = {{ $attachments ? count($attachments) : 1 }};

    $('#addAttachment').on('click', function () {
        const newField = `
            <div class="col-md-4 attachment-block">
                <label class="form-label">Document Type*</label>
                <input type="text" name="attachments[${attachmentIndex}][type]" class="form-control" required>
                <input type="file" name="attachments[]" 
                       class="form-control mt-1 attachment-input" 
                       accept=".pdf,.jpg,.jpeg,.png,.ppt,.pptx,.xls,.xlsx,.doc,.docx,image/*" 
                       required>
                <small class="text-muted">Max size: 10MB. Allowed: PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, DOCX</small>
            </div>`;
        $('#attachmentContainer').append(newField);
        attachmentIndex++;
    });

    $('#removeAttachment').on('click', function () {
        if ($('.attachment-block').length > 1) {
            $('.attachment-block').last().remove();
            attachmentIndex--;
        }
    });
});
</script>
@endpush