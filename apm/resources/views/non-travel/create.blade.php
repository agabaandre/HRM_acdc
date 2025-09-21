@extends('layouts.app')

@section('title', 'Create Non-Travel Memo')
@section('header', 'Fill Request')

@section('header-actions')
    <a href="{{ route('non-travel.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back me-1 text-success"></i> Back to List
    </a>
@endsection

@section('content')
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0 text-dark">
                <i class="fas fa-calendar-plus me-2"></i> Memo Details
            </h5>
        </div>
        <div class="card-body p-4">
            <form action="{{ route('non-travel.store') }}" method="POST" enctype="multipart/form-data" id="nonTravelForm">
                @csrf
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
                                <label for="date_required" class="form-label fw-semibold">
                                    <i class="bx bx-calendar me-1 text-success"></i> Date Required <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="date_required" id="date_required" 
                                       class="form-control datepicker @error('date_required') is-invalid @enderror" 
                                       value="{{ old('date_required') }}" required>
                                @error('date_required')
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
                                        <option value="{{ $loc->id }}" {{ in_array($loc->id, old('location_id', [])) ? 'selected' : '' }}>
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
                                        <option value="{{ $cat->id }}" {{ old('non_travel_memo_category_id') == $cat->id ? 'selected' : '' }}>
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
                        <i class="fas fa-tasks me-2"></i> Mission Details
                    </h6>
                    
                    <div class="row g-4">
                        <!-- Title -->
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="title" class="form-label fw-semibold">
                                    <i class="bx bx-heading me-1 text-success"></i> Title of Activity <span class="text-danger">*</span>
                                </label>
                                <textarea name="title" id="title" 
                                          class="form-control @error('title') is-invalid @enderror" 
                                          rows="2" required>{{ old('title') }}</textarea>
                                @error('title')
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
                                          rows="3" required>{{ old('background') }}</textarea>
                                @error('background')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                    
                     
                       
                      
                        
                        <!-- Justification -->
                        <div class="col-12">
                            <div class="form-group">
                                <label for="justification" class="form-label fw-semibold">
                                    <i class="bx bx-info me-1 text-success"></i> Justification
                                </label>
                                <textarea name="justification" id="justification" 
                                          class="form-control summernote" rows="2">{{ old('justification') }}</textarea>
                            </div>
                        </div>
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
                                <option value="{{ $type->id }}">{{ ucfirst($type->name) }}</option>
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
                        <small class="text-muted">Select up to 2 codes</small>
                    </div>

                    <div class="col-md-3 activity_code" style="display: none;">
                        <label for="activity_code" class="form-label fw-semibold">
                            <i class="fas fa-hand-holding-usd me-1 text-success"></i> World Bank Activity Code
                        </label>
                        <input name="activity_code" id="activity_code" class="form-control border-success" />
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
                                          class="form-control summernote @error('approval') is-invalid @enderror" 
                                          rows="2" required>{{ old('activity_request_remarks') }}</textarea>
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
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-danger btn-sm" id="addAttachment">Add New</button>
                        <button type="button" class="btn btn-secondary btn-sm" id="removeAttachment">Remove</button>
                    </div>
                    <div class="row g-3" id="attachmentContainer">
                        <!-- No default attachment block - user can add if needed -->
                    </div>
                </div>


              

                <div class="d-flex justify-content-end gap-3 border-top pt-4 mt-5">
                    <button type="submit" name="action" value="draft" class="btn btn-secondary btn-lg px-5">
                        <i class="bx bx-save me-1"></i> Save Non-Travel Memo
                    </button>
                    {{-- <button type="submit" name="action" value="submit" class="btn btn-success btn-lg px-5">
                        <i class="bx bx-check-circle me-1"></i> Submit for Approval
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
                        <i class="bx bx-check-circle me-2"></i> Memo Created Successfully!
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
        const divisionId = @json(user_session('division_id'));
        
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
                    `<option value="${code.id}" data-balance="${code.budget_balance}">${label}</option>`
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

        function createBudgetRow(codeId, index) {
            return `
                <tr>
                    <td>
                        <input type="text" name="budget_breakdown[${codeId}][${index}][description]" 
                               class="form-control description" required>
                    </td>
                    <td>
                        <input type="text" name="budget_breakdown[${codeId}][${index}][unit]" 
                               class="form-control unit" required>
                    </td>
                    <td>
                        <input type="number" name="budget_breakdown[${codeId}][${index}][quantity]" 
                               class="form-control quantity" min="1" value="1" required>
                    </td>
                    <td>
                        <input type="number" name="budget_breakdown[${codeId}][${index}][unit_cost]" 
                               class="form-control unit-cost" min="0" step="0.01" required>
                    </td>
                    <td class="total text-center">0.00</td>
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
            $card.find('.budget-items tr').each(function() {
                subtotal += parseFloat($(this).find('.total').text().replace(/,/g, '')) || 0;
            });
            $card.find('.subtotal').text(subtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        }

        function updateGrandTotal() {
            let grandTotal = 0;
            $('.budget-card').each(function() {
                grandTotal += parseFloat($(this).find('.subtotal').text().replace(/,/g, '')) || 0;
            });
            $('#grandBudgetTotal').text(grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            $('#grandBudgetTotalInput').val(grandTotal.toFixed(2));
        }

        // Attachments handling
        let attachmentIndex = 0;
        const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'ppt', 'pptx', 'xls', 'xlsx', 'doc', 'docx'];

        // Add new attachment block
        $('#addAttachment').on('click', function() {
            const newField = `
                <div class="col-md-4 attachment-block">
                    <label class="form-label">Document Type</label>
                    <input type="text" name="attachments[${attachmentIndex}][type]" class="form-control attachment-type">
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
            if ($('.attachment-block').length > 1) {
                $('.attachment-block').last().remove();
                attachmentIndex--;
            }
        });

        // Validate file extension on upload
        $(document).on('change', '.attachment-input', function() {
            const fileInput = this;
            const fileName = fileInput.files[0]?.name || '';
            const ext = fileName.split('.').pop().toLowerCase();
            const typeInput = $(fileInput).closest('.attachment-block').find('.attachment-type');

            if (fileName && !allowedExtensions.includes(ext)) {
                alert("Only PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, and DOCX files are allowed.");
                $(fileInput).val(''); // Clear invalid file
                typeInput.prop('required', false);
            } else if (fileName) {
                // File is valid, make type field required
                typeInput.prop('required', true);
            } else {
                // No file selected, make type field not required
                typeInput.prop('required', false);
            }
        });

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
                        
                        // For create form, redirect to the memo view
                        setTimeout(function() {
                            window.location.href = response.memo.preview_url;
                        }, 1500);
                    }
                },
                error: function(xhr) {
                    let errorMessage = 'An error occurred while creating the memo.';
                    
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
        });

        // Notification function (similar to activities form)
        function show_notification(message, type = 'info') {
            const alertClass = type === 'success' ? 'alert-success' : 
                              type === 'error' ? 'alert-danger' : 
                              type === 'warning' ? 'alert-warning' : 'alert-info';
            
            const icon = type === 'success' ? 'bx-check-circle' : 
                        type === 'error' ? 'bx-error-circle' : 
                        type === 'warning' ? 'bx-error' : 'bx-info-circle';
            
            const notification = $(`
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    <i class="bx ${icon} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `);
            
            // Insert notification at the top of the form
            $('#nonTravelForm').prepend(notification);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                notification.alert('close');
            }, 5000);
        }
    });
</script>
@endpush