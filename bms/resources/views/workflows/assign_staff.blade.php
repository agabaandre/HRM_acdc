@extends('layouts.app')

@section('title', 'Assign Staff to Workflow')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title">Assign Staff to {{ $workflow->workflow_name }}</h4>
                        <a href="{{ route('workflows.show', $workflow->id) }}" class="btn btn-secondary">Back to
                            Workflow</a>
                    </div>
                    <div class="card-body">
                        <div id="alert-container"></div>

                        @foreach($workflowDefinitions as $definition)
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">{{ $definition->role }}</h5>
                                    <small class="text-muted">Approval Order: {{ $definition->approval_order }}</small>
                                </div>
                                <div class="card-body">
                                    <div class="existing-approvers mb-3">
                                        <h6>Current Approvers</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Staff Name</th>
                                                        <th>OIC</th>
                                                        <th>Start Date</th>
                                                        <th>End Date</th>
                                                        <th>Actions</th>
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
                                                                <button type="button" class="btn btn-sm btn-danger remove-approver"
                                                                    data-approver-id="{{ $approver->id }}"
                                                                    data-workflow-id="{{ $workflow->id }}">
                                                                    Remove
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="new-assignment">
                                        <h6>Add New Approver</h6>
                                        <form class="assignment-form" data-workflow-id="{{ $workflow->id }}"
                                            data-definition-id="{{ $definition->id }}">
                                            @csrf
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="mb-3">
                                                        <label class="form-label">Staff</label>
                                                        <select name="staff_id" class="form-select" required>
                                                            <option value="">Select Staff</option>
                                                            @foreach($availableStaff as $staff)
                                                                <option value="{{ $staff->staff_id }}">
                                                                    {{ $staff->fname }} {{ $staff->lname }} ({{ $staff->job_name }})
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="col-md-3">
                                                    <div class="mb-3">
                                                        <label class="form-label">Officer In Charge (OIC)</label>
                                                        <select name="oic_staff_id" class="form-select">
                                                            <option value="">Select OIC (Optional)</option>
                                                            @foreach($availableStaff as $staff)
                                                                <option value="{{ $staff->staff_id }}">
                                                                    {{ $staff->fname }} {{ $staff->lname }} ({{ $staff->job_name }})
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="col-md-2">
                                                    <div class="mb-3">
                                                        <label class="form-label">Start Date</label>
                                                        <input type="date" name="start_date" class="form-control" required>
                                                    </div>
                                                </div>

                                                <div class="col-md-2">
                                                    <div class="mb-3">
                                                        <label class="form-label">End Date</label>
                                                        <input type="date" name="end_date" class="form-control">
                                                    </div>
                                                </div>

                                                <div class="col-md-2">
                                                    <div class="mb-3">
                                                        <label class="form-label">&nbsp;</label>
                                                        <button type="submit" class="btn btn-primary d-block w-100">Add
                                                            Approver</button>
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
            </div>
        </div>
    </div>

    @push('scripts')
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
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
                            const response = await fetch(`${baseUrl}bms/workflows/${workflowId}/ajax-store-staff`, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json',
                                },
                                body: formData
                            });

                            const result = await response.json();

                            if (result.success) {
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
                            const response = await fetch(buildApiUrl(`workflows/${workflowId}/ajax-remove-staff/${approverId}`), {
                                method: 'DELETE',
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
