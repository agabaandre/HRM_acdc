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
        <form action="{{ route('matrices.activities.store', $matrix) }}" method="POST" id="activityForm">
            @csrf

            @includeIf('activities.form')

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label for="fund_type" class="form-label fw-semibold">Fund Type <span class="text-danger">*</span></label>
                    <select name="fund_type" id="fund_type" class="form-select" required>
                        <option value="">Select Fund Type</option>
                        @foreach($fundTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="budget_codes" class="form-label fw-semibold">Budget Code(s) <span class="text-danger">*</span></label>
                    <select name="budget_codes[]" id="budget_codes" class="form-select" multiple required disabled>
                        <option value="" selected disabled>Select a fund type first</option>
                    </select>
                    <small class="text-muted">Select a fund type to see available budget codes</small>
                </div>
                <div class="col-md-4">
                    <label for="key_result_link" class="form-label fw-semibold">Link to Key Result <span class="text-danger">*</span></label>
                    <select name="key_result_link" id="key_result_link" class="form-select" required>
                        <option value="">Select Key Result</option>
                        @foreach(json_decode($matrix->key_result_area ?? '[]') as $index => $kr)
                            <option value="{{ $index }}">{{ $kr->title ?? 'Untitled' }}</option>
                        @endforeach
                    </select>
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
                            <tr data-participant-id="{{ $member->id }}" class="d-none">
                                <td>{{ $member->name }}</td>
                                <td>
                                    <input type="number" name="participant_days[{{ $member->id }}]" class="form-control participant-days" value="0" min="0">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div id="budgetGroupContainer"></div>

            <div class="d-flex justify-content-end mt-4">
                <div class="border p-4 rounded-3 shadow-sm bg-light">
                    <h5 class="mb-0">Total Budget: <span class="text-success fw-bold">$<span id="grandBudgetTotal">0.00</span></span></h5>
                    <input type="hidden" name="budget[grand_total]" id="grandBudgetTotalInput" value="0">
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4">
                <a href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-secondary px-4">
                    <i class="bx bx-x me-1"></i> Cancel
                </a>
                <div class="d-grid gap-3 d-md-flex justify-content-md-end">
                    <button type="submit" name="save_as_draft" value="1" class="btn btn-outline-primary btn-lg">
                        <i class="bx bx-save me-1"></i> Save as Draft
                    </button>
                    <button type="submit" name="submit" value="1" class="btn btn-primary btn-lg px-4">
                        <i class="bx bx-check-shield me-1"></i> Submit Activity
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function () {
    let startDateInput = $('#date_from');
    let endDateInput = $('#date_to');
    let internalParticipants = $('#internal_participants');
    let participantsTable = $('#participantsTable tbody');
    let grandTotalInput = $('#grandBudgetTotalInput');
    let grandTotalDisplay = $('#grandBudgetTotal');
    
    // Handle fund type change
    $('#fund_type').on('change', function() {
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
            success: function(response) {
                budgetCodesSelect.empty();
                
                if (response.length > 0) {
                    $.each(response, function(index, code) {
                        budgetCodesSelect.append($('<option>', {
                            value: code.id,
                            text: code.code + ' - ' + (code.description || 'No description')
                        }));
                    });
                    budgetCodesSelect.prop('disabled', false);
                } else {
                    budgetCodesSelect.append($('<option>', {
                        value: '',
                        text: 'No budget codes available for this fund type and division',
                        disabled: true,
                        selected: true
                    }));
                }
            },
            error: function(xhr) {
                console.error('Error loading budget codes:', xhr);
                budgetCodesSelect.empty().append($('<option>', {
                    value: '',
                    text: 'Error loading budget codes. Please try again.',
                    disabled: true,
                    selected: true
                }));
            }
        });
    });

    function updateParticipantDays() {
        const start = new Date(startDateInput.val());
        const end = new Date(endDateInput.val());
        const timeDiff = Math.abs(end - start);
        const dayDiff = Math.ceil(timeDiff / (1000 * 60 * 60 * 24)) + 1;

        participantsTable.empty();
        internalParticipants.find('option:selected').each(function () {
            const name = $(this).text();
            const id = $(this).val();
            participantsTable.append(`
                <tr>
                    <td>${name}</td>
                    <td><input type="number" name="participant_days[${id}]" class="form-control" value="${dayDiff}" min="1"></td>
                </tr>
            `);
        });
    }

    internalParticipants.on('change', updateParticipantDays);
    startDateInput.on('change', updateParticipantDays);
    endDateInput.on('change', updateParticipantDays);

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
                    <input type="text" name="budget[groups][${codeId}][items][${index}][description]" class="form-control" placeholder="Item description" required>
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