@extends('layouts.app')

@section('title', 'Edit Special Memo')
@section('header', "Edit Special Memo")

@section('header-actions')
    <a href="{{ route('special-memo.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back 
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
            <form action="{{ route('special-memo.update', $specialMemo) }}" method="POST" id="activityForm" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                @includeIf('special-memo.form')

                <div class="row g-4 mt-2">
                    <div class="col-md-4 fund_type">
                        <label for="fund_type" class="form-label fw-semibold">
                            <i class="fas fa-hand-holding-usd me-1 text-success"></i> Fund Type <span class="text-danger">*</span>
                        </label>
                        <select name="fund_type" id="fund_type" class="form-select border-success" required >
                            <option value="">Select Fund Type</option>
                            @foreach($fundTypes as $type)
                                <option value="{{ $type->id }}" {{ old('fund_type', $specialMemo->fund_type_id) == $type->id ? 'selected' : '' }}>{{ ucfirst($type->name) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 activity_code" style="display: none;">
                        <label for="activity_code" class="form-label fw-semibold">
                            <i class="fas fa-hand-holding-usd me-1 text-success"></i> Activity Code <span class="text-danger">*</span>
                        </label>
                        <input name="activity_code" id="activity_code" class="form-control border-success" value="{{ old('activity_code', $specialMemo->activity_code ?? '') }}" />
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
                </div>

                <div class="row mt-3">
                    <div class="col-md-4 offset-md-8">
                        <label for="total_participants" class="form-label fw-semibold">
                            <i class="fas fa-users me-1 text-success"></i> Total Participants
                        </label>
                        <input type="text" id="total_participants_display" class="form-control bg-white border-success fw-bold" readonly value="{{ $specialMemo->total_participants ?? 0 }}">
                        <input type="hidden" name="total_participants" id="total_participants" value="{{ $specialMemo->total_participants ?? 0 }}">
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
                        @if(isset($specialMemo->attachments) && count($specialMemo->attachments) > 0)
                            @foreach($specialMemo->attachments as $index => $attachment)
                                <div class="col-md-4 attachment-block">
                                    <label class="form-label">Document Type*</label>
                                    <input type="text" name="attachments[{{ $index }}][type]" class="form-control" value="{{ $attachment->type ?? '' }}" required>
                                    <input type="file" name="attachments[{{ $index }}][file]" class="form-control mt-1">
                                    @if(isset($attachment->file_path))
                                        <small class="text-muted">Current: {{ basename($attachment->file_path) }}</small>
                                    @endif
                                </div>
                            @endforeach
                        @else
                            <div class="col-md-4 attachment-block">
                                <label class="form-label">Document Type*</label>
                                <input type="text" name="attachments[0][type]" class="form-control" required>
                                <input type="file" name="attachments[0][file]" class="form-control mt-1">
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
                    <button type="submit" name="action" value="draft" class="btn btn-secondary btn-lg px-5">
                        <i class="bx bx-save me-1"></i> Save as Draft
                    </button>
                    
                    <button type="submit" name="action" value="submit" class="btn btn-success btn-lg px-5">
                        <i class="bx bx-send me-1"></i> Submit for Approval
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
const staffData = @json($allStaffGroupedByDivision ?? []);

$(document).ready(function () {
    // Initialize fund type change handler
    $('#fund_type').change(function(event){
        let selectedText = $('#fund_type option:selected').text();

        if(selectedText.toLocaleLowerCase().indexOf("intramural")>-1){
            $('.fund_type').removeClass('col-md-4');
            $('.fund_type').addClass('col-md-2');
            $('.activity_code').show();
        }
        else{
            $('#activity_code').val("");
            $('.activity_code').hide();
            $('.fund_type').removeClass('col-md-2');
            $('.fund_type').addClass('col-md-4');
        }
    });

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

        appendToInternalParticipantsTable(staffList);
        updateTotalParticipants();
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

        staffList.forEach(staff => {
            const row = `
                <tr>
                    <td>${staff.name}</td>
                    <td><input type="date" name="participant_start[${staff.id}]" class="form-control" value="${mainStart}" required></td>
                    <td><input type="date" name="participant_end[${staff.id}]" class="form-control" value="${mainEnd}" required></td>
                    <td><input type="number" name="participant_days[${staff.id}]" class="form-control" value="${days}" readonly></td>
                </tr>`;
            tableBody.append(row);
        });
    }

    // External participants functionality
    let divisionBlockIndex = 0;

    $('#addDivisionBlock').on('click', function () {
        const divisionBlock = `
            <div class="card border-success mb-3 division-participants-block" data-index="${divisionBlockIndex}">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-success">Division Participants</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-division-block">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Division</label>
                            <select class="form-select division-select" data-index="${divisionBlockIndex}">
                                <option value="">Select Division</option>
                                ${Object.keys(staffData).map(division => 
                                    `<option value="${division}">${division}</option>`
                                ).join('')}
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Staff Members</label>
                            <select class="form-select staff-select" name="external_participants[${divisionBlockIndex}][]" multiple>
                                <option value="">Select Staff</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>`;
        
        $('#externalParticipantsWrapper').append(divisionBlock);
        divisionBlockIndex++;
    });

    $(document).on('click', '.remove-division-block', function () {
        $(this).closest('.division-participants-block').remove();
    });

    $(document).on('change', '.division-select', function () {
        const selectedDivision = $(this).val();
        const blockIndex = $(this).data('index');
        const staffSelect = $(this).closest('.card-body').find('.staff-select');
        
        staffSelect.empty().append('<option value="">Select Staff</option>');
        
        if (selectedDivision && staffData[selectedDivision]) {
            staffData[selectedDivision].forEach(staff => {
                staffSelect.append(`<option value="${staff.staff_id}">${staff.fname} ${staff.lname}</option>`);
            });
        }
    });

    // Initialize existing participants table if data exists
    if ($('#internal_participants').val() && $('#internal_participants').val().length > 0) {
        const selectedIds = $('#internal_participants').val();
        const staffList = selectedIds.map(id => {
            return {
                id: id,
                name: $(`#internal_participants option[value="${id}"]`).text()
            };
        });
        appendToInternalParticipantsTable(staffList);
        updateTotalParticipants();
    }

    // Initialize total participants display
    updateTotalParticipants();
});

let attachmentIndex = {{ isset($specialMemo->attachments) ? count($specialMemo->attachments) : 1 }};

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
        alert("Only PDF, JPG, JPEG, or PNG files are allowed.");
        $(fileInput).val(''); // Clear invalid file
    }
});
</script>
@endpush
