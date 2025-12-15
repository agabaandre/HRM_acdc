{{-- 
    Reusable Admin Update Creator/Responsible Person Modal Component
    
    Usage:
    @include('activities.partials.admin-update-creator-responsible', [
        'activity' => $activity,
        'matrix' => $matrix,
        'isAdmin' => $isAdmin ?? false
    ])
--}}

@if($isAdmin ?? false)
<div class="modal fade" id="adminUpdateModal" tabindex="-1" aria-labelledby="adminUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title text-white" id="adminUpdateModalLabel">
                    <i class="bx bx-user-pin me-2"></i>Admin: Update Creator & Responsible Person
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="adminUpdateForm">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bx bx-info-circle me-2"></i>
                        <strong>Warning:</strong> This action can only be performed by system administrators. Use with caution.
                    </div>
                    
                    <div class="mb-3">
                        <label for="admin_creator_id" class="form-label fw-semibold">
                            <i class="bx bx-user me-1 text-primary"></i>Creator (Staff ID) <span class="text-danger">*</span>
                        </label>
                        <select name="staff_id" id="admin_creator_id" class="form-select select2" required style="width: 100%;">
                            <option value="">Select Creator</option>
                            @foreach(\App\Models\Staff::active()->select(['id', 'fname', 'lname', 'staff_id', 'job_name'])->get() as $staff)
                                <option value="{{ $staff->staff_id }}" {{ $activity->staff_id == $staff->staff_id ? 'selected' : '' }}>
                                    {{ $staff->fname }} {{ $staff->lname }} - {{ $staff->job_name ?? 'N/A' }} (ID: {{ $staff->staff_id }})
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Current: {{ optional($activity->staff)->fname }} {{ optional($activity->staff)->lname ?? 'Not assigned' }}</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="admin_responsible_person_id" class="form-label fw-semibold">
                            <i class="bx bx-user-check me-1 text-success"></i>Responsible Person (Staff ID) <span class="text-danger">*</span>
                        </label>
                        <select name="responsible_person_id" id="admin_responsible_person_id" class="form-select select2" required style="width: 100%;">
                            <option value="">Select Responsible Person</option>
                            @foreach(\App\Models\Staff::active()->select(['id', 'fname', 'lname', 'staff_id', 'job_name'])->get() as $staff)
                                <option value="{{ $staff->staff_id }}" {{ $activity->responsible_person_id == $staff->staff_id ? 'selected' : '' }}>
                                    {{ $staff->fname }} {{ $staff->lname }} - {{ $staff->job_name ?? 'N/A' }} (ID: {{ $staff->staff_id }})
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Current: {{ optional($activity->focalPerson)->fname }} {{ optional($activity->focalPerson)->lname ?? 'Not assigned' }}</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bx bx-save me-1"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Initialize Select2 for admin modal
$(document).ready(function() {
    $('#adminUpdateModal').on('shown.bs.modal', function() {
        $('#admin_creator_id, #admin_responsible_person_id').select2({
            dropdownParent: $('#adminUpdateModal'),
            width: '100%'
        });
    });
    
    // Handle admin update form submission
    $('#adminUpdateForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const updateUrl = '{{ route("matrices.activities.admin-update", [$matrix, $activity]) }}';
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="bx bx-loader bx-spin me-1"></i> Updating...');
        
        $.ajax({
            url: updateUrl,
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Show success message using Lobibox notification
                    show_notification(
                        response.message || 'Creator and Responsible Person updated successfully.',
                        'success'
                    );
                    
                    // Close modal
                    $('#adminUpdateModal').modal('hide');
                    
                    // Reload page after a short delay
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    // Show error message using Lobibox notification
                    show_notification(
                        response.message || 'Failed to update. Please try again.',
                        'error'
                    );
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON?.message || 'An error occurred. Please try again.';
                // Show error message using Lobibox notification
                show_notification(errorMsg, 'error');
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>
@endpush
@endif

