@extends('layouts.app')

@section('title', 'Edit Special Memo')

@section('styles')
<style>
    /* Attachment Preview Modal Styles */
    #previewModal .modal-dialog {
        max-width: 90vw;
        margin: 1.75rem auto;
    }

    #previewModal .modal-body {
        min-height: 500px;
        max-height: 80vh;
        overflow: hidden;
    }

    #previewModal .modal-content {
        border-radius: 0.75rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    #previewModal .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 0.75rem 0.75rem 0 0;
        border: none;
    }

    #previewModal .btn-close {
        filter: invert(1);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        #previewModal .modal-dialog {
            max-width: 95vw;
            margin: 0.5rem auto;
        }
        
        #previewModal .modal-body {
            min-height: 400px;
            max-height: 70vh;
        }
        
        #previewModalBody {
            min-height: 60vh !important;
        }
    }
</style>
@endsection
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
<?php ///dd($specialMemo)?>
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
                        <select name="fund_type_id" id="fund_type_id" class="form-select border-success" required >
                            <option value="">Select Fund Type</option>
                            @foreach($fundTypes as $type)
                                <option value="{{ $type->id }}" {{ old('fund_type_id', $specialMemo->fund_type_id) == $type->id ? 'selected' : '' }}>{{ ucfirst($type->name) }}</option>
                            @endforeach
                        </select>
                    </div>


                    <div class="col-md-3">
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
                            <i class="fas fa-hand-holding-usd me-1 text-success"></i> World Bank Activity Code <span class="text-danger">*</span>
                        </label>
                        <input name="activity_code" id="activity_code" class="form-control border-success" value="{{ old('activity_code', $specialMemo->workplan_activity_code ?? '') }}" />
                        <small class="text-muted">Applicable to only World Bank Budget Codes</small>
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
                <div class="col-md-12">
                    <label for="activity_request_remarks" class="form-label fw-semibold">
                        <i class="fas fa-comment-dots me-1 text-success"></i> Request for Approval <span class="text-danger">*</span>
                    </label>
                    <textarea name="activity_request_remarks" id="activity_request_remarks" class="form-control summernote" rows="3" required>{{ old('activity_request_remarks', $specialMemo->activity_request_remarks ?? '') }}</textarea>
                </div>
                <!-- Attachments Section -->
                <div class="mt-5">
                    <h5 class="fw-bold text-success mb-3">
                        <i class="fas fa-paperclip me-2"></i> Attachments
                    </h5>
                    
                    @php
                        // The accessor handles double-encoded JSON, so just use the attribute directly
                        $attachments = $specialMemo->attachment ?? [];
                        $attachments = is_array($attachments) ? $attachments : [];
                    @endphp
                    
                    @if(!empty($attachments) && count($attachments) > 0)
                        <div class="mb-4">
                            <h6 class="text-muted mb-3">Current Attachments:</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Type</th>
                                            <th>File Name</th>
                                            <th>Size</th>
                                            <th>Uploaded</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($attachments as $index => $attachment)
                                            @php
                                                $originalName = $attachment['original_name'] ?? $attachment['filename'] ?? $attachment['name'] ?? 'Unknown';
                                                $filePath = $attachment['path'] ?? $attachment['file_path'] ?? '';
                                                $ext = $filePath ? strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) : '';
                                                $fileUrl = $filePath ? url('storage/'.$filePath) : '#';
                                                $isOffice = in_array($ext, ['ppt','pptx','xls','xlsx','doc','docx']);
                                            @endphp
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $attachment['type'] ?? 'Document' }}</td>
                                                <td>{{ $originalName }}</td>
                                                <td>{{ isset($attachment['size']) ? round($attachment['size']/1024, 2).' KB' : 'N/A' }}</td>
                                                <td>{{ isset($attachment['uploaded_at']) ? \Carbon\Carbon::parse($attachment['uploaded_at'])->format('Y-m-d H:i') : 'N/A' }}</td>
                                                <td>
                                                    @if($filePath)
                                                    <button type="button" class="btn btn-sm btn-info preview-attachment" 
                                                        data-file-url="{{ $fileUrl }}"
                                                        data-file-ext="{{ $ext }}"
                                                        data-file-office="{{ $isOffice ? '1' : '0' }}">
                                                        <i class="bx bx-show"></i> Preview
                                                    </button>
                                                    <a href="{{ $fileUrl }}" target="_blank" class="btn btn-sm btn-success">
                                                        <i class="bx bx-download"></i> Download
                                                    </a>
                                                    @else
                                                    <span class="text-muted">File not found</span>
                                                    @endif
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
                    </div>
                    <div class="row g-3" id="attachmentContainer">
                        @if(!empty($attachments) && count($attachments) > 0)
                            @foreach($attachments as $index => $attachment)
                                <div class="col-md-4 attachment-block">
                                    <label class="form-label">Document Type*</label>
                                    <input type="text" name="attachments[{{ $index }}][type]" class="form-control" value="{{ $attachment['type'] ?? '' }}">
                                    <input type="file" name="attachments[{{ $index }}][file]" class="form-control mt-1 attachment-input" accept=".pdf,.jpg,.jpeg,.png,.ppt,.pptx,.xls,.xlsx,.doc,.docx,image/*">
                                    <small class="text-muted">Current: {{ $attachment['original_name'] ?? $attachment['filename'] ?? 'No file' }}</small>
                                    <small class="text-muted d-block">Leave empty to keep existing file</small>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="attachments[{{ $index }}][replace]" id="replace_{{ $index }}" value="1">
                                        <label class="form-check-label" for="replace_{{ $index }}">
                                            <small class="text-warning">Replace existing file</small>
                                        </label>
                                    </div>
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" name="attachments[{{ $index }}][delete]" id="delete_{{ $index }}" value="1">
                                        <label class="form-check-label" for="delete_{{ $index }}">
                                            <small class="text-danger">Delete this attachment</small>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <!-- No default attachment field when no attachments exist -->
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No attachments currently. Click "Add New" to add attachments.
                                </div>
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
                    <button type="submit" name="action" value="draft" class="btn btn-success btn-lg px-5">
                        <i class="bx bx-save me-1"></i> Update Special Memo
                    </button>
                    
                    {{-- <button type="submit" name="action" value="submit" class="btn btn-success btn-lg px-5">
                        <i class="bx bx-send me-1"></i> Submit for Approval
                    </button> --}}
                </div>
            </form>
        </div>
    </div>

{{-- Modal for preview --}}
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="previewModalLabel">Attachment Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="previewModalBody" style="min-height:60vh;display:flex;align-items:center;justify-content:center;">
        <div class="text-center w-100">Loading preview...</div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
// Attachment preview functionality
$(document).on('click', '.preview-attachment', function() {
    var fileUrl = $(this).data('file-url');
    var ext = $(this).data('file-ext');
    var isOffice = $(this).data('file-office') == '1';
    var modalBody = $('#previewModalBody');
    var content = '';
    
    if(['jpg','jpeg','png'].includes(ext)) {
        content = '<img src="'+fileUrl+'" class="img-fluid" style="max-height:70vh;max-width:100%;margin:auto;display:block;">';
    } else if(ext === 'pdf') {
        content = '<iframe src="'+fileUrl+'#toolbar=1&navpanes=0&scrollbar=1" style="width:100%;height:70vh;border:none;"></iframe>';
    } else if(isOffice) {
        var gdocs = 'https://docs.google.com/viewer?url='+encodeURIComponent(fileUrl)+'&embedded=true';
        content = '<iframe src="'+gdocs+'" style="width:100%;height:70vh;border:none;"></iframe>';
    } else {
        content = '<div class="alert alert-info">Preview not available. <a href="'+fileUrl+'" target="_blank">Download/Open file</a></div>';
    }
    
    modalBody.html(content);
    var modal = new bootstrap.Modal(document.getElementById('previewModal'));
    modal.show();
});

const staffData = @json($allStaffGroupedByDivision ?? []);
const existingParticipants = @json($internalParticipants ?? []);
const existingExternalParticipants = @json($externalParticipants ?? []);
const existingBudgetItems = @json($budgetItems ?? []);

console.log('Staff data for external participants:', staffData);
console.log('Available divisions:', Object.keys(staffData));
console.log('Existing external participants:', existingExternalParticipants);

$(document).ready(function () {
    const today = new Date().setHours(0, 0, 0, 0);
    const divisionId = {{ $specialMemo->division_id }};

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

    $('#activityForm').on('submit', function (e) {
        if (!validateActivityTitleField()) {
            e.preventDefault();
            $activityTitleInput.focus();
            return false;
        }
    });

    // Initialize fund type change handler
    $('#fund_type_id').change(function(event){
        let selectedText = $('#fund_type_id option:selected').text();

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

        staffList.forEach(({ id, name }) => {
            if (!tableBody.find(`input[name="participant_days[${id}]"]`).length) {
                const row = $(`
                    <tr data-participant-id="${id}">
                        <td>${name}</td>
                        <td><input type="text" name="participant_start[${id}]" class="form-control date-picker participant-start" value="${mainStart}"></td>
                        <td><input type="text" name="participant_end[${id}]" class="form-control date-picker participant-end" value="${mainEnd}"></td>
                        <td><input type="number" name="participant_days[${id}]" class="form-control participant-days" value="${days}" readonly></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-danger btn-sm remove-participant" data-staff-id="${id}">
                                <i class="fas fa-trash"></i>
                            </button>
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

    // Function to load existing participants with their specific dates
    function loadExistingParticipantsToTable(participants) {
        const tableBody = $('#participantsTableBody');
        
        // Clear existing content
        if (tableBody.find('td').length === 1 && tableBody.find('td').hasClass('text-muted')) {
            tableBody.empty();
        }

        participants.forEach(participant => {
            // Handle different data structures - following activities pattern
            let staffId, name, participantStart, participantEnd, participantDays;
            
            if (participant.staff) {
                // Activities pattern: {staff: {staff_id: 123, fname: "John", lname: "Doe"}, ...}
                staffId = participant.staff.staff_id;
                name = `${participant.staff.fname} ${participant.staff.lname}`;
                participantStart = participant.participant_start || '';
                participantEnd = participant.participant_end || '';
                participantDays = participant.participant_days || 0;
            } else {
                // Direct pattern: {staff_id: 123, name: "John Doe", ...}
                staffId = participant.staff_id;
                name = participant.name;
                participantStart = participant.participant_start || '';
                participantEnd = participant.participant_end || '';
                participantDays = participant.participant_days || 0;
            }
            
            if (!tableBody.find(`input[name="participant_days[${staffId}]"]`).length) {
                const row = $(`
                    <tr data-participant-id="${staffId}">
                        <td>${name}</td>
                        <td><input type="text" name="participant_start[${staffId}]" class="form-control date-picker participant-start" value="${participantStart}"></td>
                        <td><input type="text" name="participant_end[${staffId}]" class="form-control date-picker participant-end" value="${participantEnd}"></td>
                        <td><input type="number" name="participant_days[${staffId}]" class="form-control participant-days" value="${participantDays}" readonly></td>
                       
                        <td class="text-center">
                            <button type="button" class="btn btn-danger btn-sm remove-participant" data-staff-id="${staffId}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
                tableBody.append(row);

                // Flatpickr initialization for existing participants
                const $start = row.find('.participant-start');
                const $end = row.find('.participant-end');
                const $days = row.find('.participant-days');

                $start.flatpickr({
                    dateFormat: 'Y-m-d',
                    defaultDate: participantStart,
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
                    defaultDate: participantEnd,
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

    // Function to load existing budget data
    function loadExistingBudgetData(budgetData, budgetCodes) {
        const container = $('#budgetGroupContainer');
        container.empty();

        budgetCodes.forEach(codeId => {
            const codeData = budgetData[codeId] || [];
            if (codeData.length > 0) {
                // Get the budget code info from the select
                const $option = $(`#budget_codes option[value="${codeId}"]`);
                const label = $option.text();
                const balance = $option.data('balance') || 0;

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
                                    ${codeData.map((item, index) => createExistingBudgetRow(codeId, index, item)).join('')}
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

                // Initialize select2 for cost items
                container.find('.select-cost-item').select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    placeholder: 'Select Cost Item',
                    allowClear: true
                });

                // Calculate totals for this budget code
                updateBudgetCodeTotal(codeId);
            }
        });

        // Update grand total
        updateAllTotals();
    }

    // Function to create existing budget row with data
    function createExistingBudgetRow(codeId, index, item) {
        return `
        <tr>
            <td>
                <select name="budget[${codeId}][${index}][cost]" 
                        class="form-select select-cost-item" 
                        required 
                        data-placeholder="Select Cost Item">
                    <option></option>
                    @foreach($costItems as $costItem)
                        <option value="{{ $costItem->name }}" ${item.cost === '{{ $costItem->name }}' ? 'selected' : ''}>{{ $costItem->name }} ({{ $costItem->cost_type }})</option>
                    @endforeach
                </select>
            </td>
            <td>
                <input type="text" 
                       name="budget[${codeId}][${index}][description]" 
                       class="form-control" 
                       placeholder="Description (optional)"
                       value="${item.description || ''}">
            </td>
            <td><input type="number" name="budget[${codeId}][${index}][unit_cost]" class="form-control unit-cost" step="0.01" min="0" value="${item.unit_cost || 0}"></td>
            <td><input type="number" name="budget[${codeId}][${index}][units]" class="form-control units" min="0" value="${item.units || 0}"></td>
            <td><input type="number" name="budget[${codeId}][${index}][days]" class="form-control days" min="0" value="${item.days || 0}"></td>
            <td><input type="text" class="form-control-plaintext total fw-bold text-success text-center" readonly value="${((item.unit_cost || 0) * (item.units || 0)).toFixed(2)}"></td>
            <td><button type="button" class="btn btn-danger remove-row"><i class="fas fa-trash"></i></button></td>
        </tr>`;
    }

    // Function to update total for a specific budget code
    function updateBudgetCodeTotal(codeId) {
        let subtotal = 0;
        $(`.budget-body[data-code="${codeId}"]`).find('tr').each(function () {
            subtotal += parseFloat($(this).find('.total').val()) || 0;
        });
        $(`.subtotal[data-code="${codeId}"]`).text(subtotal.toFixed(2));
    }

    // External participants functionality - copied from create form
    function isValidActivityDates() {
        return $('#date_from').val() && $('#date_to').val();
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

    // Load existing participants data - following activities pattern exactly
    let existingParticipants = @json($internalParticipants ?? []);
    const existingBudgetItems = @json($budgetItems ?? []);
    
    console.log('Processed participants from controller:', existingParticipants);
    console.log('Raw participants from specialMemo:', @json($specialMemo->internal_participants ?? []));
    
    // Fallback: If internalParticipants is empty, try to get data from raw internal_participants
    if (existingParticipants.length === 0) {
        const rawParticipants = @json($specialMemo->internal_participants ?? []);
        console.log('Using fallback raw participants data:', rawParticipants);
        
        if (rawParticipants && rawParticipants.length > 0) {
            existingParticipants = rawParticipants;
            console.log('Switched to raw participants data');
        }
    }
    
    console.log('Final participants to load:', existingParticipants);
    console.log('Loading budget items:', existingBudgetItems);
    
    // Participant loading moved to loadExistingParticipants() function

    // Initialize total participants display
    updateTotalParticipants();

    // Remove participant event handler
    $(document).on('click', '.remove-participant', function() {
        const staffId = $(this).data('staff-id');
        const row = $(this).closest('tr');
        
        // Remove from select2
        $('#internal_participants').val(function() {
            return $(this).val().filter(id => id != staffId);
        }).trigger('change');
        
        // Remove from table
        row.remove();
        
        // Update total participants
        updateTotalParticipants();
    });

    // Date validation functions
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

    function handleParticipantsChange() {
        if (!validateDates(false)) return;
        updateParticipantsTable();
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

    // Date change handlers
    $('#date_from, #date_to').on('change', function () {
        toggleParticipantSelection();
        if (validateDates()) {
            updateParticipantsTable();
        }
    });

    // Initialize select2 for responsible person
    $('#responsible_person_id').select2({
        placeholder: 'Select Responsible Person',
        width: '100%'
    });

    // Initialize select2 for internal participants
    $('#internal_participants').select2({
        placeholder: 'Select Internal Participants',
        width: '100%'
    }).on('select2:select select2:unselect', function () {
        handleParticipantsChange();
    });

    // Load existing participants after Select2 is initialized
    loadExistingParticipants();

    // Function to load existing participants
    function loadExistingParticipants() {
        // Initialize existing participants table if data exists - following activities pattern exactly
        if (existingParticipants && existingParticipants.length > 0) {
            console.log('Processing participants:', existingParticipants);
            
            // Get all participant IDs from the stored data (don't filter by division here)
            // The dropdown options are already filtered by division in the controller
            let participantIds = [];
            if (existingParticipants[0] && existingParticipants[0].staff) {
                // Activities pattern: {staff: {staff_id: 123}, ...}
                participantIds = existingParticipants.map(p => p.staff.staff_id);
            } else if (existingParticipants[0] && existingParticipants[0].staff_id) {
                // Direct pattern: {staff_id: 123, name: "John Doe", ...}
                participantIds = existingParticipants.map(p => p.staff_id);
            }
            
            console.log('Setting participant IDs:', participantIds);
            if (participantIds.length > 0) {
                // Set the select values and trigger change event
                $('#internal_participants').val(participantIds).trigger('change');
                
                // Load existing participants with their stored data
                loadExistingParticipantsToTable(existingParticipants);
                
                console.log('Participants loaded successfully in dropdown and table');
            } else {
                console.log('No participants found in stored data');
            }
        }
    }

    // Initialize select2 for locations
    $('#location_id').select2({
        placeholder: "Select Location/Venue",
        allowClear: true,
        width: '100%'
    });

    // Initial check
    toggleParticipantSelection();

    // Budget management functions
    $('#fund_type_id').on('change', function () {
        const fundTypeId = $(this).val();
        const budgetCodesSelect = $('#budget_codes');
        budgetCodesSelect.empty().prop('disabled', true).append('<option disabled selected>Loading...</option>');

        if (fundTypeId) {
            $.get('{{ route("budget-codes.by-fund-type") }}', {
                fund_type_id: fundTypeId,
                division_id: divisionId
            }, function (data) {
                budgetCodesSelect.empty();
                if (data.length) {
                    data.forEach(code => {
                        const label = `${code.code} | ${code.funder_name || 'No Funder'} | $${parseFloat(code.budget_balance).toLocaleString()}`;
                        budgetCodesSelect.append(
                            `<option value="${code.id}" data-balance="${code.budget_balance}">${label}</option>`
                        );
                    });
                    budgetCodesSelect.prop('disabled', false);
                } else {
                    budgetCodesSelect.append('<option disabled selected>No budget codes found</option>');
                }
            });
        } else {
            budgetCodesSelect.empty().append('<option disabled selected>Select a fund type first</option>');
        }
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
        let hasExceededBudget = false;
        const fundTypeId = parseInt($('#fund_type_id').val()) || 0;
        const isChangeRequest = {{ request('change_request') ? 'true' : 'false' }};
        
        $('.budget-body').each(function () {
            const code = $(this).data('code');
            let subtotal = 0;
            let originalSubtotal = 0;
            
            // Calculate current subtotal from all rows
            $(this).find('tr').each(function () {
                subtotal += parseFloat($(this).find('.total').val()) || 0;
            });
            
            // Calculate original subtotal from existing items (for change requests)
            if (isChangeRequest && existingBudgetItems && existingBudgetItems[code]) {
                existingBudgetItems[code].forEach(function(item) {
                    const unitCost = parseFloat(item.unit_cost) || 0;
                    const units = parseFloat(item.units) || 0;
                    const days = parseFloat(item.days) || 0;
                    originalSubtotal += unitCost * units * days;
                });
            }
            
            // Get the budget balance for this code
            const balanceElement = $(`#budget_codes option[value="${code}"]`);
            const budgetBalance = parseFloat(balanceElement.data('balance')) || 0;
            
            // If editing, add the current memo's budget for this code to available balance
            let availableBalance = budgetBalance;
            @if(isset($editing) && $editing && isset($budgetCodes))
                // Get current memo budget for this code from existing items
                let currentMemoBudget = 0;
                if (existingBudgetItems && existingBudgetItems[code]) {
                    existingBudgetItems[code].forEach(function(item) {
                        const unitCost = parseFloat(item.unit_cost) || 0;
                        const units = parseFloat(item.units) || 0;
                        const days = parseFloat(item.days) || 0;
                        currentMemoBudget += unitCost * units * days;
                    });
                }
                availableBalance = budgetBalance + currentMemoBudget;
            @endif
            
            // For change requests: only check if NEW items would cause balance to go negative
            // For regular edits: check if total exceeds available balance
            let shouldCheckBudget = false;
            if (isChangeRequest) {
                // Calculate the difference (new items added)
                const newItemsTotal = subtotal - originalSubtotal;
                // Only check if new items would cause the balance to go to 0 or negative
                // The original items' budget is already allocated, so we only care about new additions
                if (newItemsTotal > 0) {
                    // Check if adding new items would exceed the available balance
                    // budgetBalance is the current balance (excluding this memo's budget)
                    // We need to check if the new items would cause the balance to go negative
                    // Formula: budgetBalance - newItemsTotal < 0
                    const balanceAfterNewItems = budgetBalance - newItemsTotal;
                    shouldCheckBudget = balanceAfterNewItems < 0;
                } else {
                    // If no new items or items were removed, no need to check
                    shouldCheckBudget = false;
                }
            } else {
                // Regular edit: check if total exceeds available balance
                shouldCheckBudget = subtotal > availableBalance;
            }
            
            // Format and display subtotal
            $(`.subtotal[data-code="${code}"]`).text(subtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            
            // Check if budget is exceeded (skip for external source)
            if (shouldCheckBudget && fundTypeId !== 3) {
                hasExceededBudget = true;
                $(`.subtotal[data-code="${code}"]`).addClass('text-danger fw-bold');
                
                // Show warning message
                const card = $(this).closest('.card');
                let warningDiv = card.find('.budget-warning');
                if (warningDiv.length === 0) {
                    const warningMessage = isChangeRequest 
                        ? `New items exceed available budget! Available: $${availableBalance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                        : `Budget exceeded! Available: $${availableBalance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                    warningDiv = $(`<div class="alert alert-danger mt-2 budget-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${warningMessage}
                    </div>`);
                    card.find('.card-body').append(warningDiv);
                }
            } else {
                $(`.subtotal[data-code="${code}"]`).removeClass('text-danger fw-bold');
                
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
            submitBtn.prop('disabled', false).removeClass('btn-danger').addClass('btn-success');
        }
    }

    // Initialize select2 for budget codes
    $('#budget_codes').select2({ maximumSelectionLength: 2, width: '100%' });

    // Load existing fund type and budget data - following activities pattern exactly
    const existingFundTypeId = @json($fundTypeId ?? null);
    const existingBudgetCodes = @json($budgetIds ?? []);
    
    console.log('Loading existing data:', {
        fundTypeId: existingFundTypeId,
        budgetCodes: existingBudgetCodes,
        budgetItems: existingBudgetItems
    });
    
    // Function to restore external participants division blocks
    function restoreExternalParticipants(externalParticipants) {
        // Group external participants by division
        const participantsByDivision = {};
        externalParticipants.forEach(participant => {
            const divisionName = participant.staff.division_name;
            if (!participantsByDivision[divisionName]) {
                participantsByDivision[divisionName] = [];
            }
            participantsByDivision[divisionName].push(participant);
        });

        // Create division blocks for each division
        Object.keys(participantsByDivision).forEach(divisionName => {
            const participants = participantsByDivision[divisionName];
            console.log(`Creating division block for ${divisionName} with ${participants.length} participants`);
            
            // Create the division block
            const divisions = Object.keys(staffData);
            let divisionOptions = '<option value="">Select Division</option>';
            divisions.forEach(div => {
                divisionOptions += `<option value="${div}" ${div === divisionName ? 'selected' : ''}>${div}</option>`;
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

            // Initialize select2
            $block.find('.division-select, .staff-names').select2({
                theme: 'bootstrap4',
                width: '100%',
                placeholder: 'Select'
            });

            // Load staff for the division
            const staff = staffData[divisionName] || [];
            const staffSelect = $block.find('.staff-names');
            staffSelect.empty().append(
                staff.map(s => `<option value="${s.staff_id}">${s.fname} ${s.lname}</option>`)
            );

            // Select the participants
            const participantIds = participants.map(p => p.staff.staff_id.toString());
            staffSelect.val(participantIds).trigger('change');
        });
    }

    // Initialize form fields with existing data - following activities pattern
    function initializeExistingData() {
        // Set activity code visibility based on fund type
        const fundTypeText = $('#fund_type_id option:selected').text();
        if (fundTypeText.toLowerCase().indexOf("intramural") > -1) {
            $('.activity_code').show();
            $('.fund_type').removeClass('col-md-4').addClass('col-md-2');
        }

        // Load budget codes if fund type is selected
        if ($('#fund_type_id').val()) {
            console.log('Fund type selected:', $('#fund_type_id').val());
            // Enable budget codes dropdown and load codes
            $('#budget_codes').prop('disabled', false);
            
            // Trigger fund type change to load budget codes
            $('#fund_type_id').trigger('change');
            
            // If there are existing budget items, ensure they're properly loaded
            if (existingBudgetItems && Object.keys(existingBudgetItems).length > 0) {
                console.log('Existing budget items found:', existingBudgetItems);
                
                // Wait for budget codes to load, then restore existing selections
                setTimeout(() => {
                    console.log('Restoring budget code selections...');
                    const budgetCodeIds = Object.keys(existingBudgetItems);
                    console.log('Budget code IDs to select:', budgetCodeIds);
                    
                    // Select the budget codes
                    budgetCodeIds.forEach(codeId => {
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
                            // Skip grand_total as it's not a budget code
                            if (codeId === 'grand_total') {
                                console.log('Skipping grand_total as it\'s not a budget code');
                                return;
                            }
                            
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

        // Restore external participants division blocks
        if (existingExternalParticipants && existingExternalParticipants.length > 0) {
            console.log('Restoring external participants...');
            restoreExternalParticipants(existingExternalParticipants);
        }

        // Update total participants display
        updateTotalParticipants();
    }

    // Call initialization after a short delay to ensure all elements are loaded
    setTimeout(initializeExistingData, 100);

    // Debug: Log the special memo data
    console.log('Special Memo Data:', {
        responsible_person_id: @json($specialMemo->responsible_person_id ?? null),
        staff_id: @json($specialMemo->staff_id ?? null),
        division_id: @json($specialMemo->division_id ?? null),
        fund_type_id: @json($specialMemo->fund_type_id ?? null),
        internal_participants: @json($specialMemo->internal_participants ?? null),
        budget: @json($specialMemo->budget_breakdown ?? null)
    });
    
    // Debug: Log the staff data for responsible person
    console.log('Staff data for responsible person:', @json($staff ?? []));
    
    // Debug: Check current selected value
    console.log('Current responsible person value:', $('#responsible_person_id').val());
    
    // Debug: Log the processed data
    console.log('Processed Data:', {
        internalParticipants: existingParticipants,
        budgetItems: existingBudgetItems,
        fundTypeId: existingFundTypeId,
        budgetCodes: existingBudgetCodes
    });
    
    // Debug: Log the raw data from controller
    console.log('Raw data from controller:', {
        internalParticipants: @json($internalParticipants ?? []),
        budgetItems: @json($budgetItems ?? []),
        fundTypeId: @json($fundTypeId ?? null),
        budgetIds: @json($budgetIds ?? [])
    });

    // Initialize flatpickr for date pickers
    flatpickr('.date-picker', {
        dateFormat: 'Y-m-d'
    });

    // Participant date change handlers - copied from create form
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
});

// Summernote is initialized once in layout (footer) with full toolbar (fontsize, fontname, etc.)

// Initialize attachment UI state
$(document).ready(function() {
    $('.attachment-block').each(function() {
        const attachmentBlock = $(this);
        const fileInput = attachmentBlock.find('input[type="file"]');
        const replaceCheckbox = attachmentBlock.find('input[name*="[replace]"]');
        const deleteCheckbox = attachmentBlock.find('input[name*="[delete]"]');
        
        // Initially hide replace and delete checkboxes
        replaceCheckbox.hide().next('label').hide();
        deleteCheckbox.hide().next('label').hide();
        
        // Show delete checkbox for existing attachments
        if (attachmentBlock.find('small:contains("Current:")').length > 0) {
            deleteCheckbox.show().next('label').show();
        }
    });
});

// File input change handler
$(document).on('change', '.attachment-input', function () {
    const fileInput = this;
    const fileName = fileInput.files[0]?.name || '';
    const ext = fileName.split('.').pop().toLowerCase();
    const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'ppt', 'pptx', 'xls', 'xlsx', 'doc', 'docx'];
    
    const attachmentBlock = $(fileInput).closest('.attachment-block');
    const replaceCheckbox = attachmentBlock.find('input[name*="[replace]"]');
    const deleteCheckbox = attachmentBlock.find('input[name*="[delete]"]');
    
    if (fileName === '') {
        // No file selected - hide replace checkbox and show delete option if it's an existing attachment
        replaceCheckbox.hide().next('label').hide();
        if (attachmentBlock.find('small:contains("Current:")').length > 0) {
            deleteCheckbox.show().next('label').show();
        } else {
            deleteCheckbox.hide().next('label').hide();
        }
        return;
    }
    
    // Validate file extension
    if (!allowedExtensions.includes(ext)) {
        alert("Only PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, DOCX files are allowed.");
        $(fileInput).val(''); // Clear invalid file
        replaceCheckbox.hide().next('label').hide();
        return;
    }
    
    // File is valid - show replace checkbox if it's an existing attachment
    if (attachmentBlock.find('small:contains("Current:")').length > 0) {
        replaceCheckbox.show().next('label').show();
    }
    deleteCheckbox.hide().next('label').hide();
});

// Delete checkbox change handler
$(document).on('change', 'input[name*="[delete]"]', function () {
    const deleteCheckbox = $(this);
    const attachmentBlock = deleteCheckbox.closest('.attachment-block');
    const fileInput = attachmentBlock.find('input[type="file"]');
    const replaceCheckbox = attachmentBlock.find('input[name*="[replace]"]');
    
    if (deleteCheckbox.is(':checked')) {
        // Disable other inputs when deleting
        attachmentBlock.find('input[name*="[type]"]').prop('disabled', true);
        fileInput.prop('disabled', true);
        replaceCheckbox.prop('disabled', true);
        
        if (!confirm('Are you sure you want to delete this attachment?')) {
            deleteCheckbox.prop('checked', false);
            attachmentBlock.find('input[name*="[type]"]').prop('disabled', false);
            fileInput.prop('disabled', false);
            replaceCheckbox.prop('disabled', false);
        }
    } else {
        // Re-enable inputs when not deleting
        attachmentBlock.find('input[name*="[type]"]').prop('disabled', false);
        fileInput.prop('disabled', false);
        replaceCheckbox.prop('disabled', false);
    }
});

// Attachment management
let attachmentIndex = {{ isset($specialMemo->attachments) ? count($specialMemo->attachments) : 0 }};

$('#addAttachment').on('click', function () {
    const newField = `
        <div class="col-md-4 attachment-block">
            <label class="form-label">Document Type*</label>
            <input type="text" name="attachments[${attachmentIndex}][type]" class="form-control">
            <input type="file" name="attachments[${attachmentIndex}][file]" 
                   class="form-control mt-1 attachment-input" 
                   accept=".pdf,.jpg,.jpeg,.png,.ppt,.pptx,.xls,.xlsx,.doc,.docx,image/*">
        </div>`;
    $('#attachmentContainer').append(newField);
    attachmentIndex++;
});

$('#removeAttachment').on('click', function () {
    if ($('.attachment-block').length > 0) {
        $('.attachment-block').last().remove();
        attachmentIndex--;
        
        // If no more attachment blocks, show the info message
        if ($('.attachment-block').length === 0) {
            $('#attachmentContainer').html(`
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No attachments currently. Click "Add New" to add attachments.
                    </div>
                </div>
            `);
        }
    }
});
</script>
@endpush