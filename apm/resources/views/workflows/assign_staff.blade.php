@extends('layouts.app')

@section('title', 'Assign Staff to Workflow')

@section('header', 'Assign Staff to Workflow')

@section('header-actions')
<a href="{{ route('workflows.show', $workflow->id) }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back"></i> Back to Workflow
</a>
@endsection

@section('content')
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body py-3 px-4 bg-light rounded-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
                <h4 class="mb-0 text-success fw-bold">
                    <i class="bx bx-user-plus me-2 text-success"></i>{{ $workflow->workflow_name }}
                </h4>
                <div class="text-muted small">
                    <i class="bx bx-info-circle me-1"></i>Assign staff to workflow approval steps
                </div>
            </div>
        <div class="card-body p-4">
            <div id="alert-container"></div>
            
            <!-- Information about assignment requirements -->
            <div class="alert alert-warning border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border-left: 4px solid #ffc107 !important;">
                <div class="d-flex align-items-start">
                    <i class="bx bx-info-circle me-3 fs-3 text-warning"></i>
                    <div class="flex-grow-1">
                        <h6 class="mb-2 fw-semibold text-warning">
                            <i class="bx bx-calendar-check me-1"></i>Assignment Requirements
                        </h6>
                        <p class="mb-2 small text-muted">
                            <strong>Primary Approver:</strong> Start date and end date are <span class="text-danger fw-bold">required only when OIC is selected</span>.<br>
                            <strong>OIC (Officer in Charge):</strong> Start date and end date are <span class="text-success fw-bold">optional</span> for OIC assignments.
                        </p>
                        <p class="mb-0 small text-muted">
                            <i class="bx bx-refresh me-1"></i>Existing approvers will be pre-selected in the form fields for easy editing.
                        </p>
                    </div>
                </div>  
            </div>

            <!-- Information about hidden levels -->
            <div class="alert alert-info border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-left: 4px solid #2196f3 !important;">
                <div class="d-flex align-items-start">
                    <i class="bx bx-info-circle me-3 fs-3 text-info"></i>
                    <div class="flex-grow-1">
                        <h6 class="mb-2 fw-semibold text-info">
                            <i class="bx bx-shield-check me-1"></i>Note: Some Approval Levels Are Hidden
                        </h6>
                        <p class="mb-2 small text-muted">
                            The following approval levels are automatically managed and cannot be manually assigned:
                        </p>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-secondary me-2">Level 1</span>
                                    <span class="small fw-semibold">HOD (Division Head)</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-secondary me-2">Level 2</span>
                                    <span class="small fw-semibold">Director</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-secondary me-2">Level 5</span>
                                    <span class="small fw-semibold">Finance Officer</span>
                                </div>
                            </div>
                        </div>
                        <p class="mb-0 small text-muted mt-2">
                            <i class="bx bx-info-circle me-1"></i>These roles are managed at the division level and automatically assigned based on division structure.
                        </p>
                    </div>
                </div>
            </div>

            @foreach($workflowDefinitions as $definition)
                @php
                    // Check if this is a division-managed level
                    $divisionManagedLevels = [1, 2, 5]; // HOD, Director, Finance Officer
                    $isDivisionManaged = in_array($definition->approval_order, $divisionManagedLevels);
                @endphp
                <div class="card border-0 shadow-sm mb-4 workflow-level-card {{ $isDivisionManaged ? 'border-warning division-managed-card' : '' }}">
                    <div class="card-header {{ $isDivisionManaged ? 'bg-warning bg-opacity-10 border-warning' : 'bg-white border-bottom' }}">
                        <h6 class="mb-0 {{ $isDivisionManaged ? 'text-warning' : 'text-success' }} fw-semibold">
                            <i class="bx {{ $isDivisionManaged ? 'bx-shield-x' : 'bx-check-shield' }} me-2 {{ $isDivisionManaged ? 'text-warning' : 'text-success' }}"></i>
                            {{ $definition->role }}
                            @if($isDivisionManaged)
                                <span class="badge bg-warning text-dark ms-2">Auto-Managed</span>
                            @elseif($definition->approvers->isNotEmpty())
                                <span class="badge bg-info ms-2">Existing Approver</span>
                            @endif
                        </h6>
                        <div class="text-muted small mt-1">
                            <i class="bx bx-sort me-1 {{ $isDivisionManaged ? 'text-warning' : 'text-success' }}"></i>Approval Order: {{ $definition->approval_order }}
                        </div>
                    </div>
                    <div class="card-body p-4">
                        @if($isDivisionManaged)
                            <!-- Division-managed level - show info only -->
                            <div class="alert alert-warning border-0 mb-0">
                                <div class="d-flex align-items-start">
                                    <i class="bx bx-info-circle me-3 fs-4 text-warning"></i>
                                    <div>
                                        <h6 class="mb-2 fw-semibold text-warning">Automatically Managed</h6>
                                        <p class="mb-2 small text-muted">
                                            This approval level is automatically managed by the division structure. 
                                            Staff assignments are determined by the division's organizational chart.
                                        </p>
                                        <div class="row g-2">
                                            @if($definition->approval_order == 1)
                                                <div class="col-md-6">
                                                    <span class="badge bg-secondary me-2">Division Head</span>
                                                    <span class="small">Assigned from divisions table</span>
                                                </div>
                                            @elseif($definition->approval_order == 2)
                                                <div class="col-md-6">
                                                    <span class="badge bg-secondary me-2">Director</span>
                                                    <span class="small">Assigned from divisions table</span>
                                                </div>
                                            @elseif($definition->approval_order == 5)
                                                <div class="col-md-6">
                                                    <span class="badge bg-secondary me-2">Finance Officer</span>
                                                    <span class="small">Assigned from divisions table</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <!-- Regular level - show approvers and form -->
                            <div class="existing-approvers mb-4">
                                <h6 class="fw-semibold mb-3 text-success">
                                    <i class="bx bx-user-check me-1 text-success"></i>Current Approvers
                                </h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Staff Name</th>
                                            <th>OIC</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                                    <tbody id="approvers-list-{{ $definition->id }}">
                                                        @foreach($definition->approvers as $approver)
                                                            <tr data-approver-id="{{ $approver->id }}">
                                                                <td>{{ optional($approver->staff)->fname }}
                                                                    {{ optional($approver->staff)->lname }}</td>
                                                                <td>
                                                                    @if($approver->oic_staff_id)
                                                                        {{ optional($approver->oicStaff)->fname }}
                                                                        {{ optional($approver->oicStaff)->lname }}
                                                                    @endif
                                                                </td>
                                                                <td>{{ $approver->start_date }}</td>
                                                                <td>{{ $approver->end_date }}</td>
                                                                <td>
                                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-approver"
                                                                        data-approver-id="{{ $approver->id }}"
                                                                        data-workflow-id="{{ $workflow->id }}">
                                                                        <i class="bx bx-trash me-1"></i>Remove
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                        @endif

                        @if(!$isDivisionManaged)
                            <div class="new-assignment">
                                <h6 class="fw-semibold mb-3 text-success">
                                    <i class="bx bx-user-plus me-1 text-success"></i>Assign/Replace Approver
                                </h6>
                                <div class="alert alert-info border-0 mb-3">
                                    <i class="bx bx-info-circle me-2"></i>
                                    <strong>Note:</strong> Assigning a new approver will replace any existing approver for this level.
                                </div>
                                <form class="assignment-form" data-workflow-id="{{ $workflow->id }}"
                                    data-definition-id="{{ $definition->id }}">
                                    @csrf
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="form-group position-relative">
                                                <label for="staff-{{ $definition->id }}" class="form-label fw-semibold">
                                                    <i class="bx bx-user me-1 text-success"></i>Staff: <span class="text-danger">*</span>
                                                </label>
                                                <select name="staff_id" id="staff-{{ $definition->id }}"
                                                    class="form-select select2 border-success" required>
                                                    <option value="">Select Staff</option>
                                                    @foreach($availableStaff as $staff)
                                                        <option value="{{ $staff->staff_id }}"
                                                            @if($definition->approvers->isNotEmpty() && $definition->approvers->first()->staff_id == $staff->staff_id) selected @endif>
                                                            {{ $staff->fname }} {{ $staff->lname }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <small class="text-muted mt-1 d-block">Primary approver (required)</small>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <div class="form-group position-relative">
                                                <label for="oic-{{ $definition->id }}" class="form-label fw-semibold">
                                                    <i class="bx bx-user-voice me-1 text-success"></i>OIC (Optional):
                                                </label>
                                                <select name="oic_staff_id" id="oic-{{ $definition->id }}"
                                                    class="form-select select2 border-success">
                                                    <option value="">Select OIC (Optional)</option>
                                                    @foreach($availableStaff as $staff)
                                                        <option value="{{ $staff->staff_id }}"
                                                            @if($definition->approvers->isNotEmpty() && $definition->approvers->first()->oic_staff_id == $staff->staff_id) selected @endif>
                                                            {{ $staff->fname }} {{ $staff->lname }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <small class="text-muted mt-1 d-block">Officer in charge (stand-in) - optional</small>
                                            </div>
                                        </div>

                                        <div class="col-md-2">
                                            <div class="form-group position-relative">
                                                <label for="start-date-{{ $definition->id }}" class="form-label fw-semibold">
                                                    <i class="bx bx-calendar-plus me-1 text-success"></i>Start Date: <span class="text-danger oic-required" style="display: none;">*</span>
                                                </label>
                                                <input type="text" name="start_date"
                                                    id="start-date-{{ $definition->id }}" class="form-control datepicker border-success"
                                                    placeholder="Select start date"
                                                    value="{{ $definition->approvers->isNotEmpty() ? $definition->approvers->first()->start_date : '' }}">
                                                <small class="text-muted mt-1 d-block">Primary approver start date (required when OIC is selected)</small>
                                            </div>
                                        </div>

                                        <div class="col-md-2">
                                            <div class="form-group position-relative">
                                                <label for="end-date-{{ $definition->id }}" class="form-label fw-semibold">
                                                    <i class="bx bx-calendar-minus me-1 text-success"></i>End Date: <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" name="end_date"
                                                    id="end-date-{{ $definition->id }}" class="form-control datepicker border-success"
                                                    placeholder="Select end date"
                                                    value="{{ $definition->approvers->isNotEmpty() ? $definition->approvers->first()->end_date : '' }}">
                                                <small class="text-muted mt-1 d-block">Primary approver end date (required when OIC is selected)</small>
                                            </div>
                                        </div>

                                        <div class="col-md-2 d-flex align-items-end">
                                            <div class="mb-3">
                                                <button type="submit" class="btn btn-success">
                                                    <i class="bx bx-plus-circle me-1"></i>{{ $definition->approvers->isNotEmpty() ? 'Replace Approver' : 'Assign Approver' }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @push('css')
        <!-- Select2 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <!-- Select2 Bootstrap 4 Theme -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">
        
        <style>
            /* Prevent nav menu flickering */
            .navbar {
                position: fixed !important;
                top: 0;
                width: 100%;
                z-index: 1030;
            }
            
            /* Ensure content doesn't jump */
            body {
                padding-top: 70px;
            }
            
            /* Custom styling for datepicker to match theme */
            .flatpickr-calendar {
                border: 1px solid #28a745;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);
            }
            .flatpickr-day.selected {
                background: #28a745;
                border-color: #28a745;
            }
            .flatpickr-day.selected:hover {
                background: #218838;
                border-color: #218838;
            }
            .flatpickr-current-month {
                color: #28a745;
            }
            .flatpickr-monthDropdown-months {
                color: #28a745;
            }
            
            /* Additional form styling */
            .form-control:focus {
                border-color: #28a745;
                box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
            }
            .form-select:focus {
                border-color: #28a745;
                box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
            }
            .btn-success:hover {
                background-color: #218838;
                border-color: #218838;
            }
            .card {
                transition: all 0.3s ease;
            }
            .card:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            }
            
            /* Workflow level styling */
            .workflow-level-card {
                min-height: 200px;
            }
            
            .division-managed-card {
                background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            }
        </style>
    @endpush
    
    @push('scripts')
        <!-- Select2 JS -->
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        
        <script>
            function buildApiUrl(path) {
                return `${window.location.origin}/${path}`;
            }
        </script>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
                // Initialize Select2
                $('.select2').select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    placeholder: 'Select an option',
                    allowClear: true,
                    dropdownParent: $('body')
                });
                
                // Custom styling for Select2 to better match our UI
                $('.select2-container--bootstrap4 .select2-selection--single').css('height', 'calc(1.5em + 1rem + 2px)');
                $('.select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered').css('line-height', 'calc(1.5em + 1rem)');

                // Initialize datepickers
                $('.datepicker').flatpickr({
                    dateFormat: 'Y-m-d',
                    allowInput: true,
                    clickOpens: true,
                    placeholder: 'Select date'
                });

                // Get session from laravel
                const baseUrl = @json(session('base_url', ''));

                // Function to update date field requirements based on OIC selection
                function updateDateFieldRequirements(definitionId, hasOIC) {
                    const startDateField = $(`#start-date-${definitionId}`);
                    const endDateField = $(`#end-date-${definitionId}`);
                    const startDateLabel = startDateField.closest('.form-group').find('label .oic-required');
                    const endDateLabel = endDateField.closest('.form-group').find('label .oic-required');
                    
                    if (hasOIC) {
                        // OIC is selected, make date fields required
                        startDateField.prop('required', true);
                        endDateField.prop('required', true);
                        startDateLabel.show();
                        endDateLabel.show();
                    } else {
                        // No OIC selected, make date fields optional
                        startDateField.prop('required', false);
                        endDateField.prop('required', false);
                        startDateLabel.hide();
                        endDateLabel.hide();
                    }
                }

                // Handle OIC selection to show/hide date field requirements
                $('select[name="oic_staff_id"]').on('change', function() {
                    const definitionId = $(this).closest('form').data('definition-id');
                    const hasOIC = $(this).val() !== '';
                    updateDateFieldRequirements(definitionId, hasOIC);
                });

                // Initialize date field requirements on page load
                $('select[name="oic_staff_id"]').each(function() {
                    const definitionId = $(this).closest('form').data('definition-id');
                    const hasOIC = $(this).val() !== '';
                    updateDateFieldRequirements(definitionId, hasOIC);
                });

                // Helper function to show alerts
                function showAlert(message, type = 'success') {
                    const alertContainer = document.getElementById('alert-container');
                    const alert = document.createElement('div');
                    alert.className = `alert alert-${type} alert-dismissible fade show`;
                    alert.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                    alertContainer.appendChild(alert);

                    // Auto dismiss after 5 seconds
                    setTimeout(() => alert.remove(), 5000);
                }

                // Handle new approver assignment
                document.querySelectorAll('.assignment-form').forEach(form => {
                    form.addEventListener('submit', async function (e) {
                        e.preventDefault();

                        const workflowId = this.dataset.workflowId;
                        const definitionId = this.dataset.definitionId;
                        
                        // Validate form - staff is always required, dates only when OIC is selected
                        const staffId = this.querySelector('select[name="staff_id"]').value;
                        const oicStaffId = this.querySelector('select[name="oic_staff_id"]').value;
                        const startDate = this.querySelector('input[name="start_date"]').value;
                        const endDate = this.querySelector('input[name="end_date"]').value;
                        
                        if (!staffId) {
                            showAlert('Please select a staff member', 'danger');
                            return;
                        }
                        
                        // Only require dates if OIC is selected
                        if (oicStaffId) {
                            if (!startDate) {
                                showAlert('Start date is required when OIC is selected', 'danger');
                                return;
                            }
                            
                            if (!endDate) {
                                showAlert('End date is required when OIC is selected', 'danger');
                                return;
                            }
                            
                            // Validate end date is after start date
                            if (new Date(endDate) <= new Date(startDate)) {
                                showAlert('End date must be after start date', 'danger');
                                return;
                            }
                        }
                        
                        const formData = new FormData(this);
                        formData.append('workflow_dfn_id', definitionId);

                        // Debug: Log the form data being sent
                        console.log('Form data being sent:');
                        for (let [key, value] of formData.entries()) {
                            console.log(key + ': ' + value);
                        }

                        try {
                            const response = await fetch(`{{ url('workflows/${workflowId}/store-staff') }}`, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json',
                                },
                                body: formData
                            });

                            // Check if response is ok before parsing JSON
                            if (!response.ok) {
                                console.error('Response not ok:', response.status, response.statusText);
                                const errorText = await response.text();
                                console.error('Error response body:', errorText);
                                showAlert(`HTTP Error: ${response.status} - ${response.statusText}`, 'danger');
                                return;
                            }

                            const result = await response.json();

                            if (result.success && result.approver) {
                                // Reset form
                                form.reset();
                                    
                                // Reset Select2 instances
                                $(form).find('select.select2').val('').trigger('change');
                                
                                // Reset datepicker instances
                                $(form).find('.datepicker').val('');

                                // Replace existing approvers with the new one (instead of adding)
                                const approversList = document.getElementById(`approvers-list-${definitionId}`);
                                
                                // Clear existing approvers
                                approversList.innerHTML = '';
                                
                                // Add the new approver
                                const newRow = document.createElement('tr');
                                newRow.dataset.approverId = result.approver.id;
                                newRow.innerHTML = `
                                <td>${result.approver.staff ? (result.approver.staff.fname + ' ' + result.approver.staff.lname) : 'N/A'}</td>
                                <td>${result.approver.oic_staff ? (result.approver.oic_staff.fname + ' ' + result.approver.oic_staff.lname) : ''}</td>
                                <td>${result.approver.start_date || ''}</td>
                                <td>${result.approver.end_date || ''}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-approver"
                                            data-approver-id="${result.approver.id}"
                                            data-workflow-id="${workflowId}">
                                        <i class="bx bx-trash me-1"></i>Remove
                                    </button>
                                </td>
                            `;
                                approversList.appendChild(newRow);

                                showAlert('Staff assigned successfully - replaced existing approver');
                            } else {
                                // Debug: Log the error response
                                console.error('Error response:', result);
                                if (result.errors) {
                                    console.error('Validation errors:', result.errors);
                                }
                                showAlert(result.message || 'Failed to assign staff', 'danger');
                            }
                        } catch (error) {
                            showAlert('An error occurred while assigning staff', 'danger');
                            console.error('Fetch error:', error);
                        }
                    });
                });

                // Handle approver removal using event delegation
                document.addEventListener('click', async function (e) {
                    if (e.target.matches('.remove-approver')) {
                        if (!confirm('Are you sure you want to remove this approver?')) {
                            return;
                        }

                        const button = e.target;
                        const workflowId = button.dataset.workflowId;
                        const approverId = button.dataset.approverId;

                        try {
                            const response = await fetch(`{{ url('workflows/${workflowId}/remove-staff/${approverId}') }}`, {
                                method: 'GET',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json',
                                }
                            });

                            // Check if response is ok before parsing JSON
                            if (!response.ok) {
                                console.error('Response not ok:', response.status, response.statusText);
                                const errorText = await response.text();
                                console.error('Error response body:', errorText);
                                showAlert(`HTTP Error: ${response.status} - ${response.statusText}`, 'danger');
                                return;
                            }

                            const result = await response.json();

                            if (result.success) {
                                // Remove the row
                                button.closest('tr').remove();
                                showAlert('Staff removed successfully');
                            } else {
                                showAlert(result.message, 'danger');
                            }
                        } catch (error) {
                            showAlert('An error occurred while removing staff', 'danger');
                            console.error(error);
                        }
                    }
                });
            });
        </script>
    @endpush
@endsection
