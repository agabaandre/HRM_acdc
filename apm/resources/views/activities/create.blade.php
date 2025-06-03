@extends('layouts.app')

@section('title', 'Add Activity')
@section('header', "Add Activity - {$matrix->quarter} {$matrix->year}")

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
            <form action="{{ route('matrices.activities.store', $matrix) }}" method="POST" id="activityForm">
                @csrf

                @includeIf('activities.form')

                <div class="row g-4 mt-2">
                    <div class="col-md-4">
                        <label for="fund_type" class="form-label fw-semibold">
                            <i class="fas fa-hand-holding-usd me-1 text-success"></i> Fund Type <span class="text-danger">*</span>
                        </label>
                        <select name="fund_type" id="fund_type" class="form-select border-success" required>
                            <option value="">Select Fund Type</option>
                            @foreach($fundTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="budget_codes" class="form-label fw-semibold">
                            <i class="fas fa-wallet me-1 text-success"></i> Budget Code(s) <span class="text-danger">*</span>
                        </label>
                        <select name="budget_codes[]" id="budget_codes" class="form-select border-success" multiple required disabled>
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
                            @foreach(json_decode($matrix->key_result_area ?? '[]') as $index => $kr)
                                <option value="{{ $index }}">{{ $kr->title ?? 'Untitled' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>


                <h6 class="fw-bold text-success mb-3"><i class="fas fa-users-cog me-2"></i> Internal Participants - Days</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle" id="participantsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Participant Name</th>
                                <th>No. of Days</th>
                            </tr>
                        </thead>
                        <tbody id="participantsTableBody">
                            <tr><td colspan="2" class="text-muted text-center">No participants selected yet</td></tr>
                        </tbody>
                    </table>
                </div>

                <div id="budgetGroupContainer" class="mt-4"></div>

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
                    <button type="submit" class="btn btn-warning btn-lg px-4">
                        <i class="bx bx-save me-1"></i> Save Draft Activity
                    </button>

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
$(document).ready(function () {
    const today = new Date().setHours(0, 0, 0, 0);
    const divisionId = {{ user_session('division_id') }};

    function show_notification(message, msgtype) {
        Lobibox.notify(msgtype, {
            pauseDelayOnHover: true,
            continueDelayOnInactiveTab: false,
            position: 'top right',
            icon: 'bx bx-check-circle',
            msg: message
        });
    }

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
        if (endDate <= startDate) {
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

    function limitInternalParticipants() {
        const selected = $('#internal_participants').val() || [];
        const totalAllowed = parseInt($('#total_participants').val()) || 0;

        if (selected.length > totalAllowed) {
            const trimmed = selected.slice(0, totalAllowed);
            $('#internal_participants').val(trimmed).trigger('change.select2');
            show_notification(`You can only select up to ${totalAllowed} participants.`, 'warning');
        }
    }

    function updateParticipantsTable() {
        const selectedIds = $('#internal_participants').val();
        const participantsTableBody = $('#participantsTableBody');
        const days = getActivityDays();
        participantsTableBody.empty();

        if (!selectedIds || selectedIds.length === 0) {
            participantsTableBody.append('<tr><td colspan="2" class="text-muted text-center">No participants selected yet</td></tr>');
            return;
        }

        selectedIds.forEach(id => {
            const name = $(`#internal_participants option[value="${id}"]`).text();
            participantsTableBody.append(`
                <tr>
                    <td>${name}</td>
                    <td><input type="number" name="participant_days[${id}]" class="form-control" value="${days}" min="1"></td>
                </tr>`);
        });
    }

    function handleParticipantsChange() {
        const selected = $('#internal_participants').val() || [];
        const totalAllowed = parseInt($('#total_participants').val()) || 0;

        if (selected.length > totalAllowed) {
            const trimmed = selected.slice(0, totalAllowed);
            $('#internal_participants').val(trimmed).trigger('change.select2');
            show_notification(`You can only select up to ${totalAllowed} participants.`, 'warning');
            return;
        }

        if (!validateDates(false)) return;
        updateParticipantsTable();
    }

    function toggleParticipantSelection() {
        const hasDates = $('#date_from').val() && $('#date_to').val();
        const hasTotal = $('#total_participants').val();

        if (hasDates && hasTotal) {
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

    $('#total_participants').on('input', function () {
        toggleParticipantSelection();
        setTimeout(() => {
            limitInternalParticipants();
            updateParticipantsTable();
        }, 100);
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

            container.append(`
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
                            <button type="button" class="btn btn-primary btn-sm add-row" data-code="${codeId}"><i class="fas fa-plus"></i> Add</button>
                            <strong class="ms-3">Sub Total: $<span class="subtotal" data-code="${codeId}">0.00</span></strong>
                        </div>
                    </div>
                </div>
            `);
        });
    });

    function createBudgetRow(codeId, index) {
        return `
        <tr>
            <td>
                <select name="budget[${codeId}][${index}][cost]" class="form-select" required>
                    @foreach($costItems as $item)
                        <option value="{{ $item->name }}">{{ $item->name }} ({{ $item->cost_type }})</option>
                    @endforeach
                </select>
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
        tbody.append(createBudgetRow(codeId, index));
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
</script>

@endpush
