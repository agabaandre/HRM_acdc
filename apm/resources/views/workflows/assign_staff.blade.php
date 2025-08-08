@extends('layouts.app')

@section('title', 'Assign Staff to Workflow')

@section('header', 'Assign Staff to Workflow')

@section('header-actions')
<a href="{{ route('workflows.show', $workflow->id) }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back"></i> Back to Workflow
</a>
@endsection

@section('content')
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="bx bx-user-plus me-2 text-primary"></i>{{ $workflow->workflow_name }}</h5>
            <div class="text-muted small mt-1">
                <i class="bx bx-info-circle me-1"></i>Assign staff to workflow approval steps
            </div>
        </div>
        <div class="card-body p-4">
            <div id="alert-container"></div>

            @foreach($workflowDefinitions as $definition)
                <div class="card border shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="bx bx-check-shield me-2 text-primary"></i>{{ $definition->role }}</h6>
                        <div class="text-muted small mt-1">
                            <i class="bx bx-sort me-1"></i>Approval Order: {{ $definition->approval_order }}
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="existing-approvers mb-4">
                            <h6 class="fw-semibold mb-3"><i class="bx bx-user-check me-1 text-primary"></i>Current Approvers</h6>
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

                        <div class="new-assignment">
                            <h6 class="fw-semibold mb-3"><i class="bx bx-user-plus me-1 text-primary"></i>Add New Approver</h6>
                                        <form class="assignment-form" data-workflow-id="{{ $workflow->id }}"
                                            data-definition-id="{{ $definition->id }}">
                                            @csrf
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="form-group position-relative">
                                            <label for="staff-{{ $definition->id }}" class="form-label fw-semibold"><i class="bx bx-user me-1 text-primary"></i>Staff:</label>
                                            <select name="staff_id" id="staff-{{ $definition->id }}"
                                                class="form-select form-select-lg select2" required>
                                                <option value="">Select Staff</option>
                                                @foreach($availableStaff as $staff)
                                                    <option value="{{ $staff->staff_id }}">
                                                        {{ $staff->fname }} {{ $staff->lname }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted mt-1 d-block">Primary approver</small>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group position-relative">
                                            <label for="oic-{{ $definition->id }}" class="form-label fw-semibold"><i class="bx bx-user-voice me-1 text-primary"></i>OIC (Optional):</label>
                                            <select name="oic_staff_id" id="oic-{{ $definition->id }}"
                                                class="form-select form-select-lg select2">
                                                <option value="">Select OIC (Optional)</option>
                                                @foreach($availableStaff as $staff)
                                                    <option value="{{ $staff->staff_id }}">
                                                        {{ $staff->fname }} {{ $staff->lname }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted mt-1 d-block">Officer in charge (stand-in)</small>
                                        </div>
                                    </div>

                                    <div class="col-md-2">
                                        <div class="form-group position-relative">
                                            <label for="start-date-{{ $definition->id }}" class="form-label fw-semibold"><i class="bx bx-calendar-plus me-1 text-primary"></i>Start Date:</label>
                                            <input type="date" name="start_date"
                                                id="start-date-{{ $definition->id }}" class="form-control form-control-lg"
                                                required>
                                            <small class="text-muted mt-1 d-block">Assignment start date</small>
                                        </div>
                                    </div>

                                    <div class="col-md-2">
                                        <div class="form-group position-relative">
                                            <label for="end-date-{{ $definition->id }}" class="form-label fw-semibold"><i class="bx bx-calendar-minus me-1 text-primary"></i>End Date:</label>
                                            <input type="date" name="end_date"
                                                id="end-date-{{ $definition->id }}" class="form-control form-control-lg">
                                            <small class="text-muted mt-1 d-block">Assignment end date (optional)</small>
                                        </div>
                                    </div>

                                    <div class="col-md-2 d-flex align-items-end">
                                        <div class="mb-3">
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="bx bx-plus-circle me-1"></i>Add Approver
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @push('scripts')
        <!-- Select2 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <!-- Select2 Bootstrap 4 Theme -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">
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

                // Get session from laravel
                const baseUrl = @json(session('base_url', ''));

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
                        const formData = new FormData(this);
                        formData.append('workflow_dfn_id', definitionId);

                        try {
                            const response = await fetch(`{{ url('workflows/${workflowId}/store-staff') }}`, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json',
                                },
                                body: formData
                            });

                            const result = await response.json();

                            if (result.success) {
                                // Reset form
                                form.reset();
                                    
                                // Reset Select2 instances
                                $(form).find('select.select2').val('').trigger('change');

                                // Add new row to the approvers list
                                const approversList = document.getElementById(`approvers-list-${definitionId}`);
                                const newRow = document.createElement('tr');
                                newRow.dataset.approverId = result.approver.id;
                                newRow.innerHTML = `
                                <td>${result.approver.staff.fname} ${result.approver.staff.lname}</td>
                                <td>${result.approver.oic_staff ? result.approver.oic_staff.fname + ' ' + result.approver.oic_staff.lname : ''}</td>
                                <td>${result.approver.start_date}</td>
                                <td>${result.approver.end_date || ''}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-danger remove-approver"
                                            data-approver-id="${result.approver.id}"
                                            data-workflow-id="${workflowId}">
                                        Remove
                                    </button>
                                </td>
                            `;
                                approversList.appendChild(newRow);

                                // Reset form
                                this.reset();
                                showAlert('Staff assigned successfully');
                            } else {
                                showAlert(result.message, 'danger');
                            }
                        } catch (error) {
                            showAlert('An error occurred while assigning staff', 'danger');
                            console.error(error);
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
