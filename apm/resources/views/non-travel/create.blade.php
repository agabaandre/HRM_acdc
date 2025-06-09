@extends('layouts.app')

@section('title', 'Create Non-Travel Memo')

@section('header', 'Create New Non-Travel Memo')

@section('header-actions')
<a href="{{ route('non-travel.index') }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back me-1"></i> Back to List
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
            <form action="{{ route('non-travel.store') }}" method="POST" id="activityForm" enctype="multipart/form-data">
                @csrf

                @includeIf('activities.form')

                <div class="row g-4 mt-2">
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

                    <div class="col-md-2 activity_code" style="display: none;">
                        <label for="activity_code" class="form-label fw-semibold">
                            <i class="fas fa-hand-holding-usd me-1 text-success"></i> Activity Code <span class="text-danger">*</span>
                        </label>
                        <input name="activity_code" id="activity_code" class="form-control border-success" />
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
                                <option value="{{ $index }}">
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
        <input type="text" id="total_participants_display" class="form-control bg-white border-success fw-bold" readonly value="0">
        <input type="hidden" name="total_participants" id="total_participants" value="0">
    </div>
</div>




                <div id="externalParticipantsWrapper"></div>

                <div id="budgetGroupContainer" class="mt-4"></div>

                <!-- Attachments Section -->
                <div class="mt-5">
                    <h5 class="fw-bold text-success mb-3">
                        <i class="fas fa-paperclip me-2"></i> Attachments
                    </h5>
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-danger btn-sm" id="addAttachment">Add New</button>
                        <button type="button" class="btn btn-secondary btn-sm" id="removeAttachment">Remove</button>
                    </div>
                    <div class="row g-3" id="attachmentContainer">
                        <div class="col-md-4 attachment-block">
                            <label class="form-label">Document Type*</label>
                            <input type="text" name="attachments[0][type]" class="form-control" required>
                            <input type="file" name="attachments[0][file]" class="form-control mt-1" required>
                        </div>
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
                   
                    <button type="submit" class="btn btn-success btn-lg px-5">
                        <i class="bx bx-check-circle me-1"></i> Save Activity
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
  
const staffData = @json($allStaffGroupedByDivision);

$(document).ready(function () {

  
    

    $('#fund_type').change(function(event){
        let selectedText = $('#fund_type option:selected').text();

        if(selectedText.toLocaleLowerCase().indexOf("intramural")>-1){
            $('.fund_type').removeClass('col-md-4');
            $('.fund_type').addClass('col-md-2');
            $('.activity_code').show();
        }
        else{
            $('#activity_code').value=""
            $('.activity_code').hide();
             $('.fund_type').removeClass('col-md-2');
             $('.fund_type').ddClass('col-md-4');
        }

        console.log(event);
        console.log(selectedText);
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
});


$(document).ready(function () {
    const today = new Date().setHours(0, 0, 0, 0);
    const divisionId = {{ user_session('division_id') }};


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
        participantsTableBody.append('<tr><td colspan="4" class="text-muted text-center">No participants selected yet</td></tr>');
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

        $.get('{{ route("budget-codes.by-fund-type") }}', { fund_type_id: fundTypeId, division_id: divisionId }, function (data) {
            budgetCodesSelect.empty();
            if (data.length) {
                data.forEach(code => {
                    budgetCodesSelect.append(`<option value="${code.id}" data-balance="${code.available_balance}">${code.code} - ${code.description || ''}</option>`);
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
    container.empty();

    selected.each(function () {
        const codeId = $(this).val();
        const label = $(this).text();
        const balance = $(this).data('balance');

        const cardHtml = `
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h6 class="fw-semibold">
                        Budget for: ${label}
                        <span class="float-end text-muted">Balance: $<span class="text-danger">${parseFloat(balance).toFixed(2)}</span></span>
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-bordered text-center align-middle">
                        <thead class="table-light fw-bold">
                            <tr>
                                <th>Cost</th>
                                <th>Description</th>
                                <th>Unit Cost</th>
                                <th>Units/People</th>
                                <th>Days/Frequency</th>
                                <th>Total</th>
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

        <td>
            <input type="text" 
                   name="budget[${codeId}][${index}][description]" 
                   class="form-control" 
                   placeholder="Description (optional)">
        </td>

        <td><input type="number" name="budget[${codeId}][${index}][unit_cost]" class="form-control unit-cost" step="0.01" min="0"></td>
        <td><input type="number" name="budget[${codeId}][${index}][units]" class="form-control units" min="0"></td>
        <td><input type="number" name="budget[${codeId}][${index}][days]" class="form-control days" min="0"></td>
        <td><input type="text" class="form-control-plaintext total fw-bold text-success text-center" readonly value="0.00"></td>
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
        $('.budget-body').each(function () {
            const code = $(this).data('code');
            let subtotal = 0;
            $(this).find('tr').each(function () {
                subtotal += parseFloat($(this).find('.total').val()) || 0;
            });
            $(`.subtotal[data-code="${code}"]`).text(subtotal.toFixed(2));
            grand += subtotal;
        });
        $('#grandBudgetTotal').text(grand.toFixed(2));
        $('#grandBudgetTotalInput').val(grand.toFixed(2));
    }

    // Initial check
    toggleParticipantSelection();
});

let attachmentIndex = 1;

// Allowed extensions
const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

// Add new attachment block
$('#addAttachment').on('click', function () {
    const newField = `
        <div class="col-md-4 attachment-block">
            <label class="form-label">Document Type*</label>
            <input type="text" name="attachments[${attachmentIndex}][type]" class="form-control" required>
            <input type="file" name="attachments[${attachmentIndex}][file]" 
                   class="form-control mt-1 attachment-input" 
                   accept=".pdf, .jpg, .jpeg, .png, image/*" 
                   required>
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
    const fileName = fileInput.files[0]?.name || '';
    const ext = fileName.split('.').pop().toLowerCase();

    if (!allowedExtensions.includes(ext)) {
        show_notification("Only PDF, JPG, JPEG, or PNG files are allowed.", "warning");
        $(fileInput).val(''); // Clear invalid file
    }
});


</script>

@endpush
