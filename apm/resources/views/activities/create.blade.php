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
                        <small class="text-muted">Hold Ctrl/Command to select multiple</small>
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

                <hr class="my-4">

                <h6 class="fw-bold text-success mb-3"><i class="fas fa-users-cog me-2"></i> Internal Participants - Days</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle" id="participantsTable">
                        <thead class="table-light">
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
                                        <input type="number" name="participant_days[{{ $member->id }}]"
                                               class="form-control participant-days border-success" value="0" min="0">
                                    </td>
                                </tr>
                            @endforeach
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


    <style>
        .select2-container .select2-selection--single {
            height: 38px !important;
        }

        .select2-container--default .select2-selection--single {
            border: 1px solid #dee2e6 !important;
            border-radius: 0.375rem !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px !important;
            padding-left: 12px !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: var(--bs-primary) !important;
        }
    </style>

    @push('scripts')

        {{-- Add select2 --}}
        <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

        <script>

            $(document).ready(function () {
                let startDateInput = $('#date_from');
                let endDateInput = $('#date_to');
                let internalParticipants = $('#internal_participants');
                let participantsTable = $('#participantsTable tbody');
                let grandTotalInput = $('#grandBudgetTotalInput');
                let grandTotalDisplay = $('#grandBudgetTotal');

                // Initialize Select2 for fund type and budget codes
                $('#fund_type').select2({
                    placeholder: 'Select Fund Type',
                    allowClear: true
                });

                $('.item-description').select2({
                    placeholder: "Select an item",
                    width: '100%'
                });

                $('#key_result_link').select2({
                    placeholder: 'Select Key Result',
                    allowClear: true
                });



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
                                    text: 'No budget codes available for this fund type and division',
                                    disabled: true,
                                    selected: true
                                }));
                            }
                        },
                        error: function (xhr) {
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