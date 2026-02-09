@extends('layouts.app')

@php
    // Single memos only for pending or approved matrices, regular activities for draft or returned
    $is_single_memo = in_array($matrix->overall_status, ['pending', 'approved']) ? 1 : 0;
    $title = $is_single_memo ? ' Single Memo' : ' Activity';
   // dd($is_single_memo);
@endphp

@section('title', $title)
@section('header', "Add " . $title . " - {{$matrix->quarter}} {{$matrix->year}}")



@section('header-actions')
    <a href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to Matrix
    </a>
@endsection

@section('content')
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0 text-success">
                <i class="fas fa-calendar-plus me-2"></i> {{ $title }} Details
            </h5>
        </div>

        <div class="card-body p-4">
            <form action="{{ route('matrices.activities.store', $matrix) }}" method="POST" id="activityForm" enctype="multipart/form-data">
                @csrf

                @includeIf('activities.form', ['title' => $title])

                <input type="hidden" name="is_single_memo" value="{{ $is_single_memo }}">

                <div class="card border-0 shadow-sm mb-5">
                    <div class="card-body">
                        <div class="row g-4">
                    <div class="col-md-4 fund_type">
                        <label for="fund_type" class="form-label fw-semibold">
                            <i class="fas fa-hand-holding-usd me-1 text-success"></i> Fund Type <span class="text-danger">*</span>
                        </label>
                        <select name="fund_type" id="fund_type" class="form-select border-success" required >
                            <option value="">Select Fund Type</option>
                            @foreach($fundTypes as $type)
                                <option value="{{ $type->id }}">{{ ucfirst($type->name) }}</option>
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
                        <small class="text-info d-block" id="external-source-note" style="display: none;">
                            <i class="fas fa-info-circle me-1"></i>External source activities can have zero budget as budgets are defined outside the system
                        </small>
                    </div>

                    <div class="col-md-3 activity_code" style="display: none;">
                        <label for="activity_code" class="form-label fw-semibold">
                            <i class="fas fa-hand-holding-usd me-1 text-success"></i> World Bank Activity Code <span class="text-danger" style="display: none;">*</span>
                        </label>
                        <input name="activity_code" id="activity_code" class="form-control border-success" />
                        <small class="text-muted">Applicable to only World Bank Budget Codes</small>
                    </div>

                    <div class="col-md-2">
                        <!-- Empty column for spacing -->
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4 offset-md-8">
                        <label for="total_participants" class="form-label fw-semibold">
                            <i class="fas fa-users me-1 text-success"></i> Total Participants
                        </label>
                        <input type="text" id="total_participants_display" class="form-control bg-white border-success fw-bold" readonly value="0">
                        <input type="hidden" name="total_participants" id="total_participants" value="0">
                    </div>
                </div>




                <div id="externalParticipantsWrapper"></div>

                <div id="budgetGroupContainer" class="mt-4"></div>

                <!-- Attachments Section -->
            <div class="col-md-12">
                <label for="activity_request_remarks" class="form-label fw-semibold">
                    <i class="fas fa-comment-dots me-1 text-success"></i>Request for Approval  <span class="text-danger">*</span>
                </label>
                <textarea name="activity_request_remarks" id="activity_request_remarks" class="form-control summernote" rows="3" required>{{ old('activity_request_remarks', $activity->activity_request_remarks ?? '') }}</textarea>
            </div>

            

                <div class="mt-5">
                    <h5 class="fw-bold text-success mb-3">
                        <i class="fas fa-paperclip me-2"></i> Attachments
                    </h5>
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-danger btn-sm" id="addAttachment">Add New</button>
                        <button type="button" class="btn btn-secondary btn-sm" id="removeAttachment">Remove</button>
                    </div>
                    <div class="row g-3" id="attachmentContainer"></div>
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
                   
                    <button type="submit" class="btn btn-success btn-lg px-5" id="submitBtn">
                        <i class="bx bx-check-circle me-1"></i> Save {{ $title }}
                    </button>
                </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered" data-bs-backdrop="static">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">
                        <i class="bx bx-check-circle me-2"></i>Activity Created Successfully!
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="bx bx-check-circle text-success" style="font-size: 3rem;"></i>
                    </div>
                    <div id="activityDetails">
                        <!-- Activity details will be populated here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" id="viewActivityBtn" class="btn btn-primary">
                        <i class="bx bx-eye me-1"></i>View Activity
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
const staffData = @json($allStaffGroupedByDivision);
const oldParticipants = @json(old('internal_participants', []));
const oldTravel = @json(old('international_travel', []));

$(document).ready(function () {
    // Activity Title: max 200 characters – real-time validation and counter
    const ACTIVITY_TITLE_MAX = 200;
    const $activityTitleInput = $('#activity_title');
    const $activityTitleError = $('#activity-title-length-error');
    const $activityTitleCount = $('#activity-title-char-count');

    function validateActivityTitleField() {
        if (!$activityTitleInput.length) return true;
        const len = $activityTitleInput.val().length;
        $activityTitleCount.text(len);
        if (len > ACTIVITY_TITLE_MAX) {
            $activityTitleInput.addClass('is-invalid');
            $activityTitleError.show();
            $('#activity-title-char-counter').addClass('text-danger');
            return false;
        }
        $activityTitleInput.removeClass('is-invalid');
        $activityTitleError.hide();
        $('#activity-title-char-counter').removeClass('text-danger');
        return true;
    }

    $activityTitleInput.on('input paste', function () {
        validateActivityTitleField();
    });
    validateActivityTitleField();

    // Summernote is initialized once in layout (footer) with full toolbar (fontsize, fontname, etc.)

    // AJAX Form Submission
    $('#activityForm').on('submit', function(e) {
        e.preventDefault();

        // Block submit if Activity Title exceeds 200 characters
        if (!validateActivityTitleField()) {
            $activityTitleInput.focus();
            return;
        }
        
        const form = $(this);
        const submitBtn = $('#submitBtn');
        const originalBtnText = submitBtn.html();
        
        // Frontend validation
        const totalParticipants = parseInt($('#total_participants').val()) || 0;
        const totalBudget = parseFloat($('#grandBudgetTotalInput').val()) || 0;
        const fundTypeId = parseInt($('#fund_type').val()) || 0;
        
        if (totalParticipants <= 0) {
            show_notification('Cannot create activity with zero or negative total participants.', 'error');
            submitBtn.prop('disabled', false).html(originalBtnText);
            return;
        }
        
        // Allow zero budget only for external source (fund_type_id = 3)
        if (totalBudget <= 0 && fundTypeId !== 3) {
            show_notification('Cannot create activity with zero or negative total budget.', 'error');
            submitBtn.prop('disabled', false).html(originalBtnText);
            return;
        }
        
        // Disable submit button and show loading state
        submitBtn.prop('disabled', true)
            .html('<i class="bx bx-loader-alt bx-spin me-1"></i> Saving...');
        
        // Sync Summernote editors (Background/Context, Request for Approval) to textareas so full content is submitted without loss
        $('textarea.summernote').each(function() {
            var $ta = $(this);
            try {
                if ($ta.summernote('code') !== undefined) {
                    $ta.val($ta.summernote('code'));
                }
            } catch (e) {}
        });
        
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
                    // Populate modal with activity details
                    $('#activityDetails').html(`
                        <div class="row g-3">
                            <div class="col-12">
                                <h6 class="fw-bold text-primary">${response.activity?.title || 'Activity'}</h6>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Matrix:</small><br>
                                <strong>{{ $matrix->quarter }} {{ $matrix->year }}</strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Status:</small><br>
                                <span class="badge bg-secondary">Draft</span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Date From:</small><br>
                                <strong>${response.activity?.date_from || 'N/A'}</strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Date To:</small><br>
                                <strong>${response.activity?.date_to || 'N/A'}</strong>
                            </div>
                            <div class="col-12">
                                <small class="text-muted">Total Participants:</small><br>
                                <strong class="text-success">${response.activity?.total_participants || '0'}</strong>
                            </div>
                        </div>
                    `);
                    
                    // Set view activity URL
                    $('#viewActivityBtn').attr('href', response.redirect_url);
                    
                    // Show success modal
                    $('#successModal').modal('show');
                    
                    // Reset form
                    form[0].reset();
                    
                    // Reset budget totals
                    $('#grandBudgetTotal').text('0.00');
                    $('#grandBudgetTotalInput').val('0');
                    
                    // Clear budget container
                    $('#budgetGroupContainer').empty();
                    
                    // Reset participants
                    $('#participantsTableBody').empty();
                    $('#total_participants_display').val('0');
                    $('#total_participants').val('0');
                    
                    // Reset file inputs
                    $('input[type="file"]').val('');
                    
                    // Show success notification
                    show_notification(response.msg || 'Activity created successfully!', 'success');
                    
                } else {
                    show_notification(response.msg || 'An error occurred while creating the activity.', 'error');
                    submitBtn.prop('disabled', false).html(originalBtnText);
                }
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while creating the activity.';
                
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
                // Show external source note
                $('#external-source-note').show();
            } else {
                $('.activity_code').show();
                // Show and enable budget codes, add required
                $('#budget_codes').prop('disabled', false).prop('required', true).closest('.col-md-4').show();
                // Hide external source note
                $('#external-source-note').hide();
            }
        } else {
            $('#activity_code').val(""); // clear value
            $('.activity_code').hide();
            // Hide and disable budget codes, remove required
            $('#budget_codes').val("").prop('disabled', true).prop('required', false).closest('.col-md-4').hide();
            // Hide external source note
            $('#external-source-note').hide();
        }
    })

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

$('#internal_participants').on('change', function () {
    const selectedIds = $(this).val() || [];
    const staffList = selectedIds.map(id => {
        return {
            id: id,
            name: $(`#internal_participants option[value="${id}"]`).text()
        };
    });

    appendToInternalParticipantsTable(staffList); // appends rows
    updateTotalParticipants(); //  force count update
});

$(document).on('input change', '#participantsTableBody input, #internal_participants, .staff-names, #total_external_participants', function () {
    updateTotalParticipants();
});



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
                            <input type="hidden" name="international_travel[${id}]" value="1" class="international-travel-value">
                            <input type="checkbox" class="form-check-input international-travel-checkbox" data-participant-id="${id}" checked>
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
                        updateTotalParticipants(); // 🔁 trigger here too
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
                        updateTotalParticipants(); // 🔁 trigger here too
                    }
                }
            });
        }
    });

    updateTotalParticipants(); // 🔁 TRIGGER HERE AFTER ALL PARTICIPANTS ADDED
}



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

    // Restore participants and their international travel state if form was reloaded
    if (oldParticipants && oldParticipants.length > 0) {
        $('#internal_participants').val(oldParticipants).trigger('change');
        
        // Restore international travel from old() after validation errors
        setTimeout(() => {
            oldParticipants.forEach(participantId => {
                const row = $(`tr[data-participant-id="${participantId}"]`);
                if (row.length) {
                    const val = (oldTravel && oldTravel[participantId]) ? '1' : '0';
                    row.find('.international-travel-checkbox').prop('checked', val === '1');
                    row.find('input.international-travel-value').val(val);
                }
            });
        }, 100);
    }

    // Sync hidden input when International Travel checkbox is toggled (so every participant saves correctly)
    $(document).on('change', '.international-travel-checkbox', function () {
        const row = $(this).closest('tr');
        const hidden = row.find('input.international-travel-value');
        hidden.val($(this).is(':checked') ? '1' : '0');
    });
});


$(document).ready(function () {
    const today = new Date().setHours(0, 0, 0, 0);
    const divisionId = {{ $matrix->division_id }};


    function validateDates(showError = true) {
        const startDateVal = $('#date_from').val();
        const endDateVal = $('#date_to').val();

        if (!startDateVal || !endDateVal) return false;

        const startDate = new Date(startDateVal);
        const endDate = new Date(endDateVal);

        if (isNaN(startDate) || isNaN(endDate)) return false;

        if (startDate < today) {
            if (showError) show_notification('Start date must be today or later.', 'error');
            $('#date_from').val('');
            return false;
        }
        if (endDate < startDate) {
            if (showError) show_notification('End date must be after the start date.', 'error');
            $('#date_to').val('');
            return false;
        }
        return true;
    }

    function getActivityDays() {
        const start = new Date($('#date_from').val());
        const end = new Date($('#date_to').val());
        const msPerDay = 1000 * 60 * 60 * 24;
        return Math.max(Math.ceil((end - start) / msPerDay) + 1, 1);
    }

  

    function updateParticipantsTable() {
    const selectedIds = $('#internal_participants').val();
    const participantsTableBody = $('#participantsTableBody');
    const mainStart = $('#date_from').val();
    const mainEnd = $('#date_to').val();
    const days = getActivityDays(mainStart, mainEnd);
    participantsTableBody.empty();

    if (!selectedIds || selectedIds.length === 0) {
        participantsTableBody.append('<tr><td colspan="5" class="text-muted text-center">No participants selected yet</td></tr>');
        return;
    }

    selectedIds.forEach(id => {
        const name = $(`#internal_participants option[value="${id}"]`).text();
        participantsTableBody.append(`
            <tr data-participant-id="${id}">
                <td>${name}</td>
                <td><input type="text" name="participant_start[${id}]" class="form-control date-picker participant-start" value="${mainStart}"></td>
                <td><input type="text" name="participant_end[${id}]" class="form-control date-picker participant-end" value="${mainEnd}"></td>
                <td><input type="number" name="participant_days[${id}]" class="form-control participant-days" value="${days}" readonly></td>
                <td class="text-center">
                    <div class="form-check d-flex justify-content-center">
                        <input type="hidden" name="international_travel[${id}]" value="1" class="international-travel-value">
                        <input type="checkbox" class="form-check-input international-travel-checkbox" data-participant-id="${id}" checked>
                        <label class="form-check-label ms-2">Yes</label>
                    </div>
                </td>
            </tr>
        `);
    });

    flatpickr('.date-picker', {
        dateFormat: 'Y-m-d'
    });
}


$(document).on('change', '.participant-start, .participant-end', function () {
    const row = $(this).closest('tr');
    const $start = row.find('.participant-start').get(0)._flatpickr;
    const $end = row.find('.participant-end').get(0)._flatpickr;

    if ($start && $end && $start.selectedDates.length && $end.selectedDates.length) {
        const start = $start.selectedDates[0];
        const end = $end.selectedDates[0];
        const msPerDay = 1000 * 60 * 60 * 24;
        const days = Math.max(Math.ceil((end - start) / msPerDay) + 1, 1);
        row.find('.participant-days').val(days);
    }
});


    function handleParticipantsChange() {

        if (!validateDates(false)) return;
        updateParticipantsTable();
    }

    function toggleParticipantSelection() {
        const hasDates = $('#date_from').val() && $('#date_to').val();
       

        if (hasDates) {
            $('#internal_participants').prop('disabled', false);
        } else {
            $('#internal_participants').val(null).trigger('change.select2');
            $('#internal_participants').prop('disabled', true);
            $('#participantsTableBody').empty().append('<tr><td colspan="2" class="text-muted text-center">No participants selected yet</td></tr>');
        }
    }

    $('#date_from, #date_to').on('change', function () {
        toggleParticipantSelection();
        if (validateDates()) {
            updateParticipantsTable();
        }
    });

   

    $('#internal_participants').select2({
        placeholder: 'Select Internal Participants',
        width: '100%'
    }).on('select2:select select2:unselect', function () {
        handleParticipantsChange();
    });

    $('#location_id').select2({
        placeholder: "Select Location/Venue",
        allowClear: true,
        width: '100%'
    });

    $('#budget_codes').select2({ maximumSelectionLength: 2, width: '100%' });

  $('#fund_type').on('change', function () {
    const fundTypeId = $(this).val();
    const budgetCodesSelect = $('#budget_codes');
    budgetCodesSelect.empty().prop('disabled', true).append('<option disabled selected>Loading...</option>');

    $.get('{{ route("budget-codes.by-fund-type") }}', {
        fund_type_id: fundTypeId,
        division_id: divisionId
       
    }, function (data) {
        budgetCodesSelect.empty();
        if (data.length) {
            data.forEach(code => {
                const label = `${code.code} | ${code.funder_name || 'No Funder'} | $${parseFloat(code.budget_balance).toLocaleString()}`;
                budgetCodesSelect.append(
                    `<option value="${code.id}" data-balance="${code.budget_balance}" data-funder-id="${code.funder_id}">${label}</option>`
                );
            });
            budgetCodesSelect.prop('disabled', false);
        } else {
            budgetCodesSelect.append('<option disabled selected>No budget codes found</option>');
        }
    });
});

    $('#budget_codes').on('change', function () {
    const selected = $(this).find('option:selected');
    const container = $('#budgetGroupContainer');
    
    // Check if any selected budget code is World Bank (funder_id = 1)
    let hasWorldBankCode = false;
    selected.each(function () {
        const funderId = $(this).data('funder-id');
        if (funderId == 1) { // World Bank funder_id
            hasWorldBankCode = true;
        }
    });

    // Make World Bank Activity Code required if World Bank budget code is selected
    if (hasWorldBankCode) {
        $('#activity_code').prop('required', true);
        $('.activity_code label .text-danger').show();
    } else {
        $('#activity_code').prop('required', false);
        $('.activity_code label .text-danger').hide();
    }
    
    // Get currently existing budget cards
    const existingCards = container.find('.card');
    const existingCodeIds = existingCards.map(function() {
        return $(this).find('.budget-body').data('code');
    }).get();
    
    // Get newly selected code IDs
    const selectedCodeIds = selected.map(function() { return $(this).val(); }).get();
    
    // Remove cards for codes that are no longer selected
    existingCards.each(function() {
        const cardCodeId = $(this).find('.budget-body').data('code');
        if (!selectedCodeIds.includes(cardCodeId)) {
            $(this).remove();
        }
    });

    // Add cards for newly selected codes
    selected.each(function () {
        const codeId = $(this).val();
        const label = $(this).text();
        const balance = $(this).data('balance');
        
        // Check if card already exists for this code
        if (existingCodeIds.includes(codeId)) {
            return; // Skip creating duplicate card
        }
        
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


    function createBudgetRow(codeId, index) {
    return `
    <tr>
        <td>
            <select name="budget[${codeId}][${index}][cost]" 
                    class="form-select select-cost-item" 
                    required 
                    data-placeholder="Select Cost Item">
                <option></option> <!-- Required for placeholder to work with Select2 -->
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
        const fundTypeId = parseInt($('#fund_type').val()) || 0;
        
        $('.budget-body').each(function () {
            const code = $(this).data('code');
            let subtotal = 0;
            $(this).find('tr').each(function () {
                subtotal += parseFloat($(this).find('.total').val()) || 0;
            });
            
            // Get the budget balance for this code
            const balanceElement = $(`#budget_codes option[value="${code}"]`);
            const budgetBalance = parseFloat(balanceElement.data('balance')) || 0;
            
            // Check if subtotal exceeds budget balance (skip for external source)
            if (subtotal > budgetBalance && fundTypeId !== 3) {
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
            const buttonText = fundTypeId === 3 ? 'Save Activity (External Source)' : 'Save Activity';
            submitBtn.prop('disabled', false).removeClass('btn-danger').addClass('btn-success')
                .html('<i class="bx bx-check-circle me-1"></i> ' + buttonText);
        }
    }

    // Initial check
    toggleParticipantSelection();
});

let attachmentIndex = 1;

// Allowed extensions
const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'ppt', 'pptx', 'xls', 'xlsx', 'doc', 'docx'];

// Add new attachment block
$('#addAttachment').on('click', function () {
    const newField = `
        <div class="col-md-4 attachment-block">
            <label class="form-label">Document Type*</label>
            <input type="text" name="attachments[${attachmentIndex}][type]" class="form-control" required>
            <input type="file" name="attachments[]" 
                   class="form-control mt-1 attachment-input" 
                   accept=".pdf,.jpg,.jpeg,.png,.ppt,.pptx,.xls,.xlsx,.doc,.docx,image/*" required>
            <small class="text-muted">Max size: 10MB. Allowed: PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, DOCX</small>
        </div>`;
    $('#attachmentContainer').append(newField);
    attachmentIndex++;
});

// Remove attachment block
$('#removeAttachment').on('click', function () {
    if ($('.attachment-block').length > 1) {
        $('.attachment-block').last().remove();
        attachmentIndex--;
    }
});

// Validate file extension on upload
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

// Reload page when success modal is closed
$('#successModal').on('hidden.bs.modal', function () {
    window.location.reload();
});


</script>

@endpush
