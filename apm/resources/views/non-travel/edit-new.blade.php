@extends('layouts.app')

@section('title', 'Edit Non-Travel Memo')
@section('header', 'Edit Request')

@section('header-actions')
    <a href="{{ route('non-travel.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back me-1 text-success"></i> Back to List
    </a>
@endsection

@section('content')
    @php
        // Decode JSON fields if they are strings
        $attachments = is_string($nonTravel->attachment) 
            ? json_decode($nonTravel->attachment, true) 
            : $nonTravel->attachment;
        
        // Ensure variables are arrays
        $attachments = is_array($attachments) ? $attachments : [];
    @endphp
    
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0 text-dark">
                <i class="fas fa-calendar-plus me-2"></i> Memo Details
            </h5>
        </div>
        <div class="card-body p-4">
            <form action="{{ route('non-travel.update', $nonTravel) }}" method="POST" enctype="multipart/form-data" id="nonTravelForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="_token" value="{{ csrf_token() }}">

                <!-- Section 1: Basic Information -->
                <div class="mb-5">
                    <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                        <i class="fas fa-info-circle me-2"></i> Basic Information
                    </h6>
                    
                    <div class="row g-4">
                        <!-- Request By -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="request_by" class="form-label fw-semibold">
                                    <i class="bx bx-user me-1 text-success"></i> Request by
                                </label>
                                <input type="text" id="request_by" class="form-control" value="{{ user_session('name') }}" disabled>
                            </div>
                        </div>
                        
                        <!-- Date Required -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="memo_date" class="form-label fw-semibold">
                                    <i class="bx bx-calendar me-1 text-success"></i> Date Required <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="memo_date" id="memo_date" 
                                       class="form-control datepicker @error('memo_date') is-invalid @enderror" 
                                       value="{{ old('memo_date', $nonTravel->memo_date) }}" required>
                                @error('memo_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <!-- Location -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="location_id" class="form-label fw-semibold">
                                    <i class="bx bx-map-pin me-1 text-success"></i> Location <span class="text-danger">*</span>
                                </label>
                                <select name="location_id[]" id="location_id" 
                                        class="form-select border-success select2 @error('location_id') is-invalid @enderror" 
                                        multiple required>
                                    @foreach($locations as $loc)
                                        <option value="{{ $loc->id }}" {{ in_array($loc->id, old('location_id', $nonTravel->location_id ? json_decode($nonTravel->location_id, true) : [])) ? 'selected' : '' }}>
                                            {{ $loc->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('location_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <!-- Category -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="non_travel_memo_category_id" class="form-label fw-semibold">
                                    <i class="bx bx-category me-1 text-success"></i> Category <span class="text-danger">*</span>
                                </label>
                                <select name="non_travel_memo_category_id" id="non_travel_memo_category_id" 
                                        class="form-select border-success @error('non_travel_memo_category_id') is-invalid @enderror" 
                                        required>
                                    <option value="">Select Category</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" {{ old('non_travel_memo_category_id', $nonTravel->non_travel_memo_category_id) == $cat->id ? 'selected' : '' }}>
                                            {{ $cat->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('non_travel_memo_category_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Mission Details -->
                <div class="mb-5">
                    <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                        <i class="fas fa-tasks me-2"></i> Details
                    </h6>
                    
                    <div class="row g-4">
                        <!-- Title -->
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="activity_title" class="form-label fw-semibold">
                                    <i class="bx bx-heading me-1 text-success"></i> Title of Activity <span class="text-danger">*</span>
                                </label>
                                <textarea name="activity_title" id="activity_title" 
                                          class="form-control  @error('activity_title') is-invalid @enderror" 
                                          rows="2" required>{{ old('activity_title', $nonTravel->activity_title) }}</textarea>
                                @error('activity_title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                           {{-- Background --}}
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="background" class="form-label fw-semibold">
                                    <i class="bx bx-info-circle me-1 text-success"></i> Background/Context <span class="text-danger">*</span>
                                </label>
                                <textarea name="background" id="background" 
                                          class="form-control summernote @error('background') is-invalid @enderror" 
                                          rows="3" required>{{ old('background', $nonTravel->background) }}</textarea>
                                @error('background')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <!-- Description -->
                          <div class="col-md-12">
                            <div class="form-group">
                                    <label for="justification" class="form-label fw-semibold">
                                    <i class="bx bx-comment-detail me-1 text-success"></i> Justification <span class="text-danger">*</span>
                                </label>
                                <textarea name="justification" id="justification" 
                                          class="form-control summernote @error('justification') is-invalid @enderror" 
                                          rows="5" required>{{ old('justification', $nonTravel->justification) }}</textarea>
                                @error('justification')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                     
         

            
                <!-- Budget Section -->
                <div id="budgetGroupContainer" class="mt-4">
                    <h5 class="fw-bold text-success mb-3">
                        <i class="fas fa-money-bill-wave me-2"></i> Budget Details
                    </h5>

                <div class="row g-4 mt-2">
                    <div class="col-md-4 fund_type">
                        <label for="fund_type" class="form-label fw-semibold">
                            <i class="fas fa-hand-holding-usd me-1 text-success"></i> Fund Type <span class="text-danger">*</span>
                        </label>
                        <select name="fund_type" id="fund_type" class="form-select border-success" required>
                            <option value="">Select Fund Type</option>
                            @foreach($fundTypes as $type)
                                <option value="{{ $type->id }}" {{ old('fund_type', $nonTravel->fund_type_id) == $type->id ? 'selected' : '' }}>
                                    {{ ucfirst($type->name) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="budget_codes" class="form-label fw-semibold">
                            <i class="fas fa-wallet me-1 text-success"></i> Budget Code(s) <span class="text-danger">*</span>
                        </label>
                        <select name="budget_codes[]" id="budget_codes" class="form-select  select2 border-success" multiple disabled>
                            <option value="" selected disabled>Select a fund type first</option>
                        </select>
                        <script>
                            // Store existing budget data for initialization
                            window.existingBudgetCodes = @json($selectedBudgetCodes ?? []);
                            window.existingBudgetBreakdown = @json($budgetBreakdown ?? []);
                        </script>
                        <small class="text-muted">Select up to 2 codes</small>
                    </div>

                    <div class="col-md-3 activity_code" style="display: none;">
                        <label for="activity_code" class="form-label fw-semibold">
                            <i class="fas fa-hand-holding-usd me-1 text-success"></i> World Bank Activity Code <span class="text-danger" style="display: none;">*</span>
                        </label>
                        <input name="activity_code" id="activity_code" class="form-control border-success" value="{{ old('activity_code', $nonTravel->workplan_activity_code) }}" />
                        <small class="text-muted">Applicable to only World Bank Budget Codes</small>
                    </div>
                </div>
                    <div class="alert alert-info">
                        Select budget codes above to add budget items
                    </div>
                    <div id="budgetCards"></div>
                </div>


                <div class="d-flex justify-content-end mt-4">
                    <div class="border p-4 rounded-3 shadow-sm bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-coins me-2 text-success"></i>
                            Total Budget: <span class="text-success fw-bold">$<span id="grandBudgetTotal">0.00</span></span>
                        </h5>
                        <input type="hidden" name="budget_breakdown[grand_total]" id="grandBudgetTotalInput" value="0">
                    </div>
                </div>
               <!-- RA -->
                          <div class="col-md-12">
                            <div class="form-group">
                                <label for="activity_request_remarks" class="form-label fw-semibold">
                                    <i class="bx bx-message-detail me-1 text-success"></i> Request for Approval <span class="text-danger">*</span>
                                </label>
                                    <textarea name="activity_request_remarks" id="activity_request_remarks" 
                                          class="form-control summernote @error('activity_request_remarks') is-invalid @enderror" 
                                          rows="2" required>{{ old('activity_request_remarks', $nonTravel->activity_request_remarks) }}</textarea>
                                @error('activity_request_remarks')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
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
                                            <th>Document Name</th>
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
                                                    <a href="{{ url('storage/'.$attachment['path']) }}" target="_blank" class="btn btn-sm btn-info">
                                                        <i class="bx bx-show"></i> View
                                                    </a>
                                                    <a href="{{ (url('storage/'.$attachment['path'])) }}" download="{{ $attachment['original_name'] }}" class="btn btn-sm btn-success">
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
                    </div>
                    <div class="row g-3" id="attachmentContainer">
                        @if($attachments && count($attachments) > 0)
                            @foreach($attachments as $index => $attachment)
                                <div class="col-md-4 attachment-block">
                                    <label class="form-label">Document Type*</label>
                                    <input type="text" name="attachments[{{ $index }}][type]" class="form-control" value="{{ $attachment['type'] ?? '' }}">
                                    <input type="file" name="attachments[{{ $index }}][file]" class="form-control mt-1 attachment-input" accept=".pdf,.jpg,.jpeg,.png,.ppt,.pptx,.xls,.xlsx,.doc,.docx,image/*">
                                    <small class="text-muted">Current: {{ $attachment['original_name'] ?? 'No file' }}</small>
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


              

                <div class="d-flex justify-content-end gap-3 border-top pt-4 mt-5">
                    <button type="submit" name="action" value="draft" class="btn btn-success btn-lg px-5">
                        <i class="bx bx-save me-1"></i> Update Non-Travel Memo
                    </button>
                    {{-- <button type="submit" name="action" value="submit" class="btn btn-success btn-lg px-5">
                        <i class="bx bx-check-circle me-1"></i> Update & Submit
                    </button> --}}
                </div>
            </form>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">
                        <i class="bx bx-check-circle me-2"></i> Memo Updated Successfully!
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success">
                        <i class="bx bx-info-circle me-2"></i>
                        <span id="successMessage"></span>
                    </div>
                    
                    <div class="card border-success">
                        <div class="card-header bg-success bg-opacity-10 text-success">
                            <h6 class="mb-0"><i class="bx bx-file-alt me-2"></i> Memo Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Title:</strong> <span id="memoTitle"></span></p>
                                    <p><strong>Category:</strong> <span id="memoCategory"></span></p>
                                    <p><strong>Status:</strong> <span id="memoStatus"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Date Required:</strong> <span id="memoDate"></span></p>
                                    <p><strong>Total Budget:</strong> $<span id="memoBudget"></span></p>
                                    <p><strong>Memo ID:</strong> #<span id="memoId"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i> Close
                    </button>
                    <a href="#" id="previewMemoBtn" class="btn btn-primary" target="_blank">
                        <i class="bx bx-show me-1"></i> Preview Memo
                    </a>
                    <a href="{{ route('non-travel.index') }}" class="btn btn-success">
                        <i class="bx bx-list-ul me-1"></i> Go to List
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function() {
        
        // Initialize select2
        $('.select2').select2({
            theme: 'bootstrap4',
            width: '100%'
        });

     



        // Populate existing budget data
        const existingBudget = @json($nonTravel->budget_breakdown ? json_decode($nonTravel->budget_breakdown, true) : []);
        const existingBudgetCodes = @json($nonTravel->budget_id ? json_decode($nonTravel->budget_id, true) : []);
        
        if (existingBudgetCodes.length > 0 && existingBudget.length > 0) {
            // Enable budget codes select
            $('#budget_codes').prop('disabled', false);
            
            // Populate budget codes
            existingBudgetCodes.forEach(codeId => {
                const option = $(`#budget_codes option[value="${codeId}"]`);
                if (option.length) {
                    option.prop('selected', true);
                }
            });
            
            // Trigger change event to load budget codes
            $('#fund_type').trigger('change');
            
            // Wait for budget codes to load, then populate budget items
            setTimeout(() => {
                populateExistingBudgetItems(existingBudget);
            }, 500);
        }

        // Fund type change handler
        $('#fund_type').change(function(event) {
            let selectedText = $('#fund_type option:selected').text();

            if (selectedText.toLowerCase().indexOf("intramural") > -1) {
                $('.fund_type').removeClass('col-md-4').addClass('col-md-2');
                $('.activity_code').show();
            } else {
                $('#activity_code').val("");
                $('.activity_code').hide();
                $('.fund_type').removeClass('col-md-2').addClass('col-md-4');
            }
        });

        // Get budget codes based on fund type and division
        const divisionId = {{ $nonTravel->division_id }};
        
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

        // Budget codes change handler
        $('#budget_codes').on('change', function() {
            const selected = $(this).find('option:selected');
            const container = $('#budgetCards');
            container.empty();

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

            if (selected.length === 0) {
                return;
            }

            selected.each(function() {
                const codeId = $(this).val();
                const label = $(this).text();
                const balance = $(this).data('balance');

                const cardHtml = `
                    <div class="card mt-4 budget-card" data-code="${codeId}">
                        <div class="card-header bg-light">
                            <h6 class="fw-semibold">
                                Budget for: ${label}
                                <span class="float-end text-muted">Balance: $<span class="text-danger">${parseFloat(balance).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered align-middle">
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th>Unit</th>
                                        <th>Qty</th>
                                        <th>Unit Cost</th>
                                        <th>Total</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="budget-items">
                                    ${createBudgetRow(codeId, 0)}
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end fw-bold">Subtotal:</td>
                                        <td class="subtotal fw-bold text-success">0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                            <div class="text-end mt-2">
                                <button type="button" class="btn btn-primary btn-sm add-budget-row" data-code="${codeId}">
                                    <i class="fas fa-plus"></i> Add Row
                                </button>
                            </div>
                        </div>
                    </div>
                `;

                container.append(cardHtml);
            });

            updateGrandTotal();
        });

        function createBudgetRow(codeId, index, existingItem = null) {
            const description = existingItem ? existingItem.description || '' : '';
            const unit = existingItem ? existingItem.unit || '' : '';
            const quantity = existingItem ? existingItem.quantity || 1 : 1;
            const unitCost = existingItem ? existingItem.unit_cost || 0 : 0;
            const total = existingItem ? (quantity * unitCost).toFixed(2) : '0.00';
            
            return `
                <tr>
                    <td>
                        <input type="text" name="budget_breakdown[${codeId}][${index}][description]" 
                               class="form-control description" value="${description}" required>
                    </td>
                    <td>
                        <input type="text" name="budget_breakdown[${codeId}][${index}][unit]" 
                               class="form-control unit" value="${unit}" required>
                    </td>
                    <td>
                        <input type="number" name="budget_breakdown[${codeId}][${index}][quantity]" 
                               class="form-control quantity" min="1" value="${quantity}" required>
                    </td>
                    <td>
                        <input type="number" name="budget_breakdown[${codeId}][${index}][unit_cost]" 
                               class="form-control unit-cost" min="0" step="0.01" value="${unitCost}" required>
                    </td>
                    <td class="total text-center">${total}</td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm remove-budget-row">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }

        // Add budget row
        $(document).on('click', '.add-budget-row', function() {
            const codeId = $(this).data('code');
            const $tbody = $(this).closest('.card-body').find('.budget-items');
            const index = $tbody.find('tr').length;
            
            $tbody.append(createBudgetRow(codeId, index));
            updateGrandTotal();
        });

        // Remove budget row
        $(document).on('click', '.remove-budget-row', function() {
            $(this).closest('tr').remove();
            updateGrandTotal();
        });

        // Calculate row total when values change
        $(document).on('input', '.quantity, .unit-cost', function() {
            const $row = $(this).closest('tr');
            const quantity = parseFloat($row.find('.quantity').val()) || 0;
            const unitCost = parseFloat($row.find('.unit-cost').val()) || 0;
            const total = (quantity * unitCost);
            $row.find('.total').text(total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            updateSubtotal($row.closest('.budget-card'));
            updateGrandTotal();
        });

        function updateSubtotal($card) {
            let subtotal = 0;
            let originalSubtotal = 0;
            const codeId = $card.data('code');
            const isChangeRequest = {{ request('change_request') ? 'true' : 'false' }};
            const fundTypeId = parseInt($('#fund_type').val()) || 0;
            
            // Calculate current subtotal from all rows
            $card.find('.budget-items tr').each(function() {
                subtotal += parseFloat($(this).find('.total').text().replace(/,/g, '')) || 0;
            });
            
            // Calculate original subtotal from existing items (for change requests)
            if (isChangeRequest && existingBudget && existingBudget[codeId]) {
                existingBudget[codeId].forEach(function(item) {
                    const unitCost = parseFloat(item.unit_cost) || 0;
                    const qty = parseFloat(item.qty) || parseFloat(item.units) || 0;
                    originalSubtotal += unitCost * qty;
                });
            }
            
            // Get the budget balance for this code
            const balanceElement = $(`#budget_codes option[value="${codeId}"]`);
            const budgetBalance = parseFloat(balanceElement.data('balance')) || 0;
            
            // If editing, add the current memo's budget for this code to available balance
            let availableBalance = budgetBalance;
            let currentMemoBudget = 0;
            if (existingBudget && existingBudget[codeId]) {
                existingBudget[codeId].forEach(function(item) {
                    const unitCost = parseFloat(item.unit_cost) || 0;
                    const qty = parseFloat(item.qty) || parseFloat(item.units) || 0;
                    currentMemoBudget += unitCost * qty;
                });
                availableBalance = budgetBalance + currentMemoBudget;
            }
            
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
            $card.find('.subtotal').text(subtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            
            // Check if budget is exceeded (skip for external source)
            if (shouldCheckBudget && fundTypeId !== 3) {
                $card.find('.subtotal').removeClass('text-success').addClass('text-danger fw-bold');
                
                // Show warning message
                let warningDiv = $card.find('.budget-warning');
                if (warningDiv.length === 0) {
                    const warningMessage = isChangeRequest 
                        ? `New items exceed available budget! Available: $${availableBalance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                        : `Budget exceeded! Available: $${availableBalance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                    warningDiv = $(`<div class="alert alert-danger mt-2 budget-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${warningMessage}
                    </div>`);
                    $card.find('.card-body').append(warningDiv);
                }
            } else {
                $card.find('.subtotal').removeClass('text-danger fw-bold').addClass('text-success');
                
                // Remove warning if exists
                $card.find('.budget-warning').remove();
            }
        }

        function updateGrandTotal() {
            let grandTotal = 0;
            let hasExceededBudget = false;
            const fundTypeId = parseInt($('#fund_type').val()) || 0;
            
            $('.budget-card').each(function() {
                const subtotal = parseFloat($(this).find('.subtotal').text().replace(/,/g, '')) || 0;
                grandTotal += subtotal;
                
                // Check if any card has exceeded budget
                if ($(this).find('.subtotal').hasClass('text-danger')) {
                    hasExceededBudget = true;
                }
            });
            
            $('#grandBudgetTotal').text(grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            $('#grandBudgetTotalInput').val(grandTotal.toFixed(2));
            
            // Update submit button state
            const submitBtn = $('button[type="submit"]');
            if (hasExceededBudget && fundTypeId !== 3) {
                submitBtn.prop('disabled', true).addClass('btn-danger').removeClass('btn-success')
                    .html('<i class="bx bx-x-circle me-1"></i> Budget Exceeded - Cannot Save');
            } else {
                submitBtn.prop('disabled', false).removeClass('btn-danger').addClass('btn-success');
            }
        }

        // Attachments handling
        let attachmentIndex = {{ ($attachments && is_array($attachments)) ? count($attachments) : 0 }};
        const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'ppt', 'pptx', 'xls', 'xlsx', 'doc', 'docx'];

        // Add new attachment block
        $('#addAttachment').on('click', function() {
            // Remove the info alert if it exists
            $('.alert-info').remove();
            
            const newField = `
                <div class="col-md-4 attachment-block">
                    <label class="form-label">Document Type*</label>
                    <input type="text" name="attachments[${attachmentIndex}][type]" class="form-control">
                    <input type="file" name="attachments[${attachmentIndex}][file]" 
                           class="form-control mt-1 attachment-input" 
                           accept=".pdf,.jpg,.jpeg,.png,.ppt,.pptx,.xls,.xlsx,.doc,.docx,image/*">
                    <small class="text-muted">Max size: 10MB. Allowed: PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, DOCX</small>
                </div>`;
            $('#attachmentContainer').append(newField);
            attachmentIndex++;
        });

        // Remove attachment block
        $('#removeAttachment').on('click', function() {
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

        // Validate file extension on upload and handle required attribute
        $(document).on('change', '.attachment-input', function() {
            const fileInput = this;
            const fileName = fileInput.files[0]?.name || '';
            const ext = fileName.split('.').pop().toLowerCase();
            
            // Find the corresponding type input
            const typeInput = $(fileInput).closest('.attachment-block').find('input[name*="[type]"]');

            if (!allowedExtensions.includes(ext)) {
                alert("Only PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, and DOCX files are allowed.");
                $(fileInput).val(''); // Clear invalid file
                typeInput.prop('required', false);
            } else if (fileName) {
                // File selected, make type required
                typeInput.prop('required', true);
            } else {
                // No file selected, make type not required
                typeInput.prop('required', false);
            }
        });

        // Initialize existing budget data - moved to global scope
        window.initializeExistingBudgetData = function() {
            console.log('Initializing existing budget data...');
            console.log('Existing budget codes:', window.existingBudgetCodes);
            console.log('Existing budget breakdown:', window.existingBudgetBreakdown);
            console.log('Type of existingBudgetBreakdown:', typeof window.existingBudgetBreakdown);
            console.log('Keys in existingBudgetBreakdown:', window.existingBudgetBreakdown ? Object.keys(window.existingBudgetBreakdown) : 'undefined');
            
            if (window.existingBudgetCodes && window.existingBudgetCodes.length > 0) {
                // First, we need to get the fund type for the existing budget codes
                // and set it automatically so the budget codes dropdown can be populated
                const firstBudgetCode = window.existingBudgetCodes[0];
                console.log('Getting fund type for budget code:', firstBudgetCode);
                
                // Get fund type from the budget code
                $.get('{{ route("budget-codes.get-fund-type") }}', {
                    budget_code_id: firstBudgetCode
                }, function(fundTypeId) {
                    console.log('Retrieved fund type ID:', fundTypeId);
                    
                    if (fundTypeId) {
                        // Set the fund type
                        $('#fund_type').val(fundTypeId).trigger('change');
                        console.log('Set fund type to:', fundTypeId);
                        
                        // Wait for budget codes to load, then select existing codes
                        setTimeout(() => {
                            const budgetSelect = $('#budget_codes');
                            console.log('Budget select found:', budgetSelect.length);
                            console.log('Budget select options count:', budgetSelect.find('option').length);
                            
                            // Select existing budget codes
                            window.existingBudgetCodes.forEach(codeId => {
                                const option = budgetSelect.find(`option[value="${codeId}"]`);
                                if (option.length) {
                                    option.prop('selected', true);
                                    console.log(`Selected budget code: ${codeId}`);
                                } else {
                                    console.log(`Budget code option not found: ${codeId}`);
                                }
                            });
                            
                            // Trigger change to create budget cards
                            console.log('Triggering change event on budget select...');
                            budgetSelect.trigger('change');
                            
                            // Load existing budget items after cards are created
                            setTimeout(() => {
                                console.log('About to call loadExistingBudgetItems...');
                                window.loadExistingBudgetItems();
                            }, 300);
                        }, 1000); // Wait 1 second for fund type change to load budget codes
                    } else {
                        console.log('No fund type found for budget code:', firstBudgetCode);
                    }
                }).fail(function() {
                    console.log('Failed to get fund type for budget code:', firstBudgetCode);
                });
            } else {
                console.log('No existing budget codes found');
            }
        };
        
        // Load existing budget items into the cards - moved to global scope
        window.loadExistingBudgetItems = function() {
            console.log('loadExistingBudgetItems called');
            console.log('window.existingBudgetBreakdown:', window.existingBudgetBreakdown);
            console.log('Type of existingBudgetBreakdown:', typeof window.existingBudgetBreakdown);
            console.log('Keys in existingBudgetBreakdown:', window.existingBudgetBreakdown ? Object.keys(window.existingBudgetBreakdown) : 'undefined');
            
            if (window.existingBudgetBreakdown && Object.keys(window.existingBudgetBreakdown).length > 0) {
                console.log('Loading existing budget items...');
                
                try {
                    Object.entries(window.existingBudgetBreakdown).forEach(([codeId, items]) => {
                        console.log(`Processing codeId: ${codeId}, items:`, items);
                        
                        if (codeId === 'grand_total') {
                            console.log('Skipping grand_total');
                            return; // Skip grand total
                        }
                        
                        const card = $(`.budget-card[data-code="${codeId}"]`);
                        console.log(`Found card for codeId ${codeId}:`, card.length);
                        
                        if (card.length && items && items.length > 0) {
                            console.log(`Loading ${items.length} items for code ${codeId}`);
                            const tbody = card.find('.budget-items');
                            tbody.empty(); // Clear existing rows
                            
                            items.forEach((item, index) => {
                                console.log(`Creating budget row for item ${index}:`, item);
                                console.log('createBudgetRow function exists:', typeof createBudgetRow);
                                if (typeof createBudgetRow === 'function') {
                                    const row = createBudgetRow(codeId, index, item);
                                    console.log('Created row:', row);
                                    tbody.append(row);
                                } else {
                                    console.error('createBudgetRow function not found!');
                                }
                            });
                            
                            // Update totals
                            console.log('updateSubtotal function exists:', typeof updateSubtotal);
                            console.log('updateGrandTotal function exists:', typeof updateGrandTotal);
                            if (typeof updateSubtotal === 'function') {
                                updateSubtotal(card);
                            }
                            if (typeof updateGrandTotal === 'function') {
                                updateGrandTotal();
                            }
                            console.log(`Loaded ${items.length} budget items for code ${codeId}`);
                        } else {
                            console.log(`No card found or no items for codeId ${codeId}. Card length: ${card.length}, Items:`, items);
                        }
                    });
                } catch (error) {
                    console.error('Error loading existing budget items:', error);
                }
            } else {
                console.log('No existing budget breakdown data found or empty object');
            }
        };

        // AJAX form submission
        $('#nonTravelForm').on('submit', function(e) {
            e.preventDefault();
            
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]:focus');
            const originalText = submitBtn.html();
            const loadingText = '<i class="bx bx-loader-alt bx-spin me-1"></i> Processing...';
            
            // Show loading state
            submitBtn.prop('disabled', true).html(loadingText);
            
            // Create FormData object
            const formData = new FormData(this);
            
            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('input[name="_token"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        // Show success notification
                        show_notification(response.message, 'success');
                        
                        // For edit form, redirect to the memo view instead of showing modal
                        setTimeout(function() {
                            window.location.href = response.memo.preview_url;
                        }, 1500);
                    }
                },
                error: function(xhr) {
                    let errorMessage = 'An error occurred while updating the memo.';
                    
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        const errors = xhr.responseJSON.errors;
                        errorMessage = Object.values(errors).flat().join('\n');
                        show_notification(errorMessage, 'error');
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                        show_notification(errorMessage, 'error');
                    } else if (xhr.status === 422) {
                        errorMessage = 'Validation failed. Please check your input and try again.';
                        show_notification(errorMessage, 'error');
                    } else {
                        show_notification(errorMessage, 'error');
                    }
                },
                complete: function() {
                    // Reset button state
                    submitBtn.prop('disabled', false).html(originalText);
                }
            });

            // Function to populate existing budget items
            function populateExistingBudgetItems(existingBudget) {
                const container = $('#budgetCards');
                container.empty();

                Object.keys(existingBudget).forEach(codeId => {
                    if (codeId === 'grand_total') return;
                    
                    const items = existingBudget[codeId];
                    if (Array.isArray(items)) {
                        // Find the budget code option to get label and balance
                        const option = $(`#budget_codes option[value="${codeId}"]`);
                        const label = option.length ? option.text() : `Budget Code ${codeId}`;
                        const balance = option.length ? option.data('balance') : 0;

                        const cardHtml = `
                            <div class="card mt-4 budget-card" data-code="${codeId}">
                                <div class="card-header bg-light">
                                    <h6 class="fw-semibold">
                                        Budget for: ${label}
                                        <span class="float-end text-muted">Balance: $<span class="text-danger">${parseFloat(balance).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></span>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-bordered align-middle">
                                        <thead>
                                            <tr>
                                                <th>Description</th>
                                                <th>Unit</th>
                                                <th>Qty</th>
                                                <th>Unit Cost</th>
                                                <th>Total</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="budget-items">
                                            ${items.map((item, index) => createBudgetRow(codeId, index, item)).join('')}
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="4" class="text-end fw-bold">Subtotal:</td>
                                                <td class="subtotal fw-bold text-success">0.00</td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    <button type="button" class="btn btn-sm btn-success add-row" data-code="${codeId}">
                                        <i class="bx bx-plus"></i> Add Row
                                    </button>
                                </div>
                            </div>
                        `;
                        container.append(cardHtml);
                    }
                });

                // Update totals after populating
                updateGrandTotal();
            }
        });
    });
    
    // Initialize existing budget data when document is ready
    $(document).ready(function() {
        // Set fund type first if it exists
        const fundTypeId = $('#fund_type').val();
        if (fundTypeId) {
            $('#fund_type').trigger('change');
        }
        
        // Initialize budget data after a short delay to ensure everything is loaded
        setTimeout(window.initializeExistingBudgetData, 800);
    });
</script>
@endpush