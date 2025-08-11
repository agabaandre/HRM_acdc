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
                    <select name="fund_type" id="fund_type" class="form-select border-success" required>
                        <option value="">Select Fund Type</option>
                        @foreach($fundTypes as $type)
                            <option value="{{ $type->id }}" {{ old('fund_type', $specialMemo->fund_type_id) == $type->id ? 'selected' : '' }}>{{ ucfirst($type->name) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 activity_code" style="display: none;">
                    <label for="activity_code" class="form-label fw-semibold">
                        <i class="fas fa-code me-1 text-success"></i> Activity Code <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="activity_code" id="activity_code" class="form-control border-success" value="{{ old('activity_code') }}" placeholder="Enter Activity Code">
                </div>

                <div class="col-md-4">
                    <label for="key_result_link" class="form-label fw-semibold">
                        <i class="fas fa-link me-1 text-success"></i> Key Result Area Link <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="key_result_link" id="key_result_link" class="form-control border-success" value="{{ old('key_result_link', $specialMemo->key_result_area) }}" placeholder="Enter Key Result Area Link" required>
                </div>
            </div>

            <!-- Budget Section -->
            <div class="card border-0 shadow-sm mb-5 mt-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 text-success">
                        <i class="fas fa-calculator me-2"></i> Budget Details
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div id="budgetContainer">
                        @foreach($budgetCodes as $code)
                            <div class="budget-section mb-4">
                                <h6 class="fw-bold text-success mb-3">
                                    <i class="fas fa-tag me-2"></i>{{ $code->name }}
                                </h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Cost Item</th>
                                                <th>Unit Cost</th>
                                                <th>Units</th>
                                                <th>Days</th>
                                                <th>Total</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="budget-body" data-code="{{ $code->id }}">
                                            @if(isset($specialMemo->budget[$code->id]) && is_array($specialMemo->budget[$code->id]))
                                                @foreach($specialMemo->budget[$code->id] as $index => $item)
                                                    <tr>
                                                        <td>
                                                            <select name="budget[{{ $code->id }}][{{ $index }}][cost_item_id]" class="form-select select-cost-item" required>
                                                                <option value="">Select Cost Item</option>
                                                                @foreach($costItems as $costItem)
                                                                    <option value="{{ $costItem->id }}" {{ isset($item['cost_item_id']) && $item['cost_item_id'] == $costItem->id ? 'selected' : '' }}>
                                                                        {{ $costItem->name }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <input type="number" name="budget[{{ $code->id }}][{{ $index }}][unit_cost]" class="form-control unit-cost" step="0.01" value="{{ $item['unit_cost'] ?? 0 }}" required>
                                                        </td>
                                                        <td>
                                                            <input type="number" name="budget[{{ $code->id }}][{{ $index }}][units]" class="form-control units" value="{{ $item['units'] ?? 0 }}" required>
                                                        </td>
                                                        <td>
                                                            <input type="number" name="budget[{{ $code->id }}][{{ $index }}][days]" class="form-control days" value="{{ $item['days'] ?? 0 }}" required>
                                                        </td>
                                                        <td>
                                                            <input type="number" name="budget[{{ $code->id }}][{{ $index }}][total]" class="form-control total" step="0.01" value="{{ $item['total'] ?? 0 }}" readonly>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-outline-danger remove-row">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @endif
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="4" class="text-end fw-bold">Subtotal:</td>
                                                <td class="fw-bold subtotal" data-code="{{ $code->id }}">0.00</td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-success add-row" data-code="{{ $code->id }}">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="text-end">
                        <h5 class="text-success">
                            Total Budget: <span class="text-success fw-bold">$<span id="grandBudgetTotal">0.00</span></span>
                        </h5>
                        <input type="hidden" name="budget[grand_total]" id="grandBudgetTotalInput" value="0">
                    </div>
                </div>
            </div>

            <!-- Attachments Section -->
            <div class="card border-0 shadow-sm mb-5">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 text-success">
                        <i class="fas fa-paperclip me-2"></i> Attachments
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="row" id="attachmentContainer">
                        @if(isset($specialMemo->attachment) && is_array($specialMemo->attachment))
                            @foreach($specialMemo->attachment as $index => $attachment)
                                <div class="col-md-4 attachment-block">
                                    <label class="form-label">Document Type*</label>
                                    <input type="text" name="attachments[{{ $index }}][type]" class="form-control" value="{{ $attachment['type'] ?? '' }}" required>
                                    <input type="file" name="attachments[{{ $index }}][file]" class="form-control mt-1 attachment-input" accept=".pdf, .jpg, .jpeg, .png, image/*">
                                    <small class="text-muted">Current: {{ $attachment['name'] ?? 'No file' }}</small>
                                </div>
                            @endforeach
                        @else
                            <div class="col-md-4 attachment-block">
                                <label class="form-label">Document Type*</label>
                                <input type="text" name="attachments[0][type]" class="form-control" required>
                                <input type="file" name="attachments[0][file]" class="form-control mt-1 attachment-input" accept=".pdf, .jpg, .jpeg, .png, image/*" required>
                            </div>
                        @endif
                    </div>

                    <div class="mt-3">
                        <button type="button" id="addAttachment" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-plus me-1"></i> Add Attachment
                        </button>
                        <button type="button" id="removeAttachment" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-minus me-1"></i> Remove Attachment
                        </button>
                    </div>
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
             $('.fund_type').addClass('col-md-4');
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
            console.log(staffData[selectedDivision]);
            staffData[selectedDivision].forEach(staff => {
                staffSelect.append(`<option value="${staff.staff_id}">${staff.fname} ${staff.lname}</option>`);
            });
        }
    });

    // Budget functionality
    function createBudgetRow(codeId, index) {
        return `<tr>
            <td>
                <select name="budget[${codeId}][${index}][cost_item_id]" class="form-select select-cost-item" required>
                    <option value="">Select Cost Item</option>
                    @foreach($costItems as $costItem)
                        <option value="{{ $costItem->id }}">{{ $costItem->name }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="number" name="budget[${codeId}][${index}][unit_cost]" class="form-control unit-cost" step="0.01" value="0" required></td>
            <td><input type="number" name="budget[${codeId}][${index}][units]" class="form-control units" value="0" required></td>
            <td><input type="number" name="budget[${codeId}][${index}][days]" class="form-control days" value="0" required></td>
            <td><input type="number" name="budget[${codeId}][${index}][total]" class="form-control total" step="0.01" value="0" readonly></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-trash"></i></button></td>
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
