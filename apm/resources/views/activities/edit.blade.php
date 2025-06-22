@extends('layouts.app')

@section('title', 'Edit Activity')

@section('header', "Edit Activity - {$matrix->quarter} {$matrix->year}")

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
            <form action="{{ route('matrices.activities.update', [$matrix, $activity]) }}" method="POST" id="activityForm" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                @includeIf('activities.form')

                <div class="row g-4 mt-2">
                    <div class="col-md-4 fund_type">
                        <label for="fund_type" class="form-label fw-semibold">
                            <i class="fas fa-hand-holding-usd me-1 text-success"></i> Fund Type <span class="text-danger">*</span>
                        </label>
                        <select name="fund_type" id="fund_type" class="form-select border-success" required >
                            <option value="">Select Fund Type</option>
                            @foreach($fundTypes as $type)
                                <option value="{{ $type->id }}" {{ old('fund_type', $activity->fund_type_id) == $type->id ? 'selected' : '' }}>{{ ucfirst($type->name) }}</option>
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
                    </div>

                    <div class="col-md-2 activity_code" style="display: none;">
                        <label for="activity_code" class="form-label fw-semibold">
                            <i class="fas fa-hand-holding-usd me-1 text-success"></i> Activity Code <span class="text-danger"></span>
                        </label>
                        <input name="activity_code" id="activity_code" class="form-control border-success" value="{{ old('activity_code', $activity->workplan_activity_code) }}" />
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
                                <option value="{{ $index }}" {{ old('key_result_link', $activity->key_result_area) == $index ? 'selected' : '' }}>
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
                        <input type="text" id="total_participants_display" class="form-control bg-white border-success fw-bold" readonly value="{{ old('total_participants', $activity->total_participants) }}">
                        <input type="hidden" name="total_participants" id="total_participants" value="{{ old('total_participants', $activity->total_participants) }}">
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
                        @if($attachments && count($attachments) > 0)
                            @foreach($attachments as $index => $attachment)
                                <div class="col-md-4 attachment-block">
                                    <label class="form-label">Document Type*</label>
                                    <input type="text" name="attachments[{{ $index }}][type]" class="form-control" value="{{ $attachment['type'] ?? '' }}" required>
                                    <input type="file" name="attachments[{{ $index }}][file]" class="form-control mt-1 attachment-input" accept=".pdf,.jpg,.jpeg,.png,image/*">
                                    <small class="text-muted">Current: {{ $attachment['original_name'] ?? 'No file' }}</small>
                                </div>
                            @endforeach
                        @else
                            <div class="col-md-4 attachment-block">
                                <label class="form-label">Document Type*</label>
                                <input type="text" name="attachments[0][type]" class="form-control" required>
                                <input type="file" name="attachments[0][file]" class="form-control mt-1 attachment-input" accept=".pdf,.jpg,.jpeg,.png,image/*" required>
                                <small class="text-muted">Max size: 10MB. Allowed: PDF, JPG, JPEG, PNG</small>
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
                    <a href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-secondary px-4">
                        <i class="bx bx-arrow-back me-1"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-success btn-lg px-5" id="submitBtn">
                        <i class="bx bx-check-circle me-1"></i> Update Activity
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
const staffData = @json($allStaffGroupedByDivision);
const oldParticipants = @json(old('internal_participants', []));
const oldTravel = @json(old('international_travel', []));
const existingParticipants = @json($internalParticipants);
const existingBudgetItems = @json($budgetItems);

$(document).ready(function () {
    // AJAX Form Submission
    $('#activityForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = $('#submitBtn');
        const originalBtnText = submitBtn.html();
        
        // Disable submit button and show loading state
        submitBtn.prop('disabled', true)
            .html('<i class="bx bx-loader-alt bx-spin me-1"></i> Updating...');
        
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
                    show_notification(response.msg || 'Activity updated successfully!', 'success');
                    
                    // Redirect to the activity show page after a short delay
                    setTimeout(function() {
                        window.location.href = response.redirect_url || '{{ route("matrices.activities.show", [$matrix, $activity]) }}';
                    }, 1500);
                } else {
                    show_notification(response.msg || 'An error occurred while updating the activity.', 'error');
                    submitBtn.prop('disabled', false).html(originalBtnText);
                }
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while updating the activity.';
                
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

    // Restore existing participants and their international travel state
    if (existingParticipants && existingParticipants.length > 0) {
        const participantIds = existingParticipants.map(p => p.staff.staff_id);
        $('#internal_participants').val(participantIds).trigger('change');
        
        // Restore international travel checkboxes after participants are loaded
        setTimeout(() => {
            existingParticipants.forEach(participant => {
                const checkbox = $(`input[name="international_travel[${participant.staff.staff_id}]"]`);
                if (checkbox.length) {
                    checkbox.prop('checked', participant.international_travel == 1);
                }
            });
        }, 100);
    }

    // Restore old data if form was reloaded after validation errors
    if (oldParticipants && oldParticipants.length > 0) {
        $('#internal_participants').val(oldParticipants).trigger('change');
        
        // Restore international travel checkboxes after participants are loaded
        setTimeout(() => {
            oldParticipants.forEach(participantId => {
                const checkbox = $(`input[name="international_travel[${participantId}]"]`);
                if (checkbox.length && oldTravel && oldTravel[participantId]) {
                    checkbox.prop('checked', true);
                }
            });
        }, 100);
    }

    // Fund type column width logic
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
    });

    // Rest of the JavaScript from create form...
    // (Include all the functions from create.blade.php here)
    
    // Initialize existing budget items if any
    if (existingBudgetItems && Object.keys(existingBudgetItems).length > 0) {
        // Trigger fund type change to load budget codes
        $('#fund_type').trigger('change');
        
        // Restore budget items after budget codes are loaded
        setTimeout(() => {
            Object.entries(existingBudgetItems).forEach(([codeId, items]) => {
                // Select the budget code
                $(`#budget_codes option[value="${codeId}"]`).prop('selected', true);
                
                // Trigger budget codes change to create budget cards
                $('#budget_codes').trigger('change');
                
                // Restore budget items in the cards
                setTimeout(() => {
                    const tbody = $(`.budget-body[data-code="${codeId}"]`);
                    if (tbody.length && items.length > 0) {
                        tbody.empty();
                        items.forEach((item, index) => {
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
                        
                        updateAllTotals();
                    }
                }, 200);
            });
        }, 500);
    }

    // Include all the functions from create.blade.php here...
    // (Copy all the functions like appendToInternalParticipantsTable, updateAllTotals, etc.)
    
    // File validation
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
        const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

        // Check file extension
        if (!allowedExtensions.includes(ext)) {
            show_notification("Only PDF, JPG, JPEG, or PNG files are allowed.", "warning");
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

    // Attachment management
    let attachmentIndex = {{ $attachments ? count($attachments) : 1 }};

    $('#addAttachment').on('click', function () {
        const newField = `
            <div class="col-md-4 attachment-block">
                <label class="form-label">Document Type*</label>
                <input type="text" name="attachments[${attachmentIndex}][type]" class="form-control" required>
                <input type="file" name="attachments[${attachmentIndex}][file]" 
                       class="form-control mt-1 attachment-input" 
                       accept=".pdf,.jpg,.jpeg,.png,image/*" 
                       required>
                <small class="text-muted">Max size: 10MB. Allowed: PDF, JPG, JPEG, PNG</small>
            </div>`;
        $('#attachmentContainer').append(newField);
        attachmentIndex++;
    });

    $('#removeAttachment').on('click', function () {
        if ($('.attachment-block').length > 1) {
            $('.attachment-block').last().remove();
            attachmentIndex--;
        }
    });
});
</script>
@endpush