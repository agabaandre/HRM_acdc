@extends('layouts.app')

@section('title', 'Workflow Approvers')

@section('header', 'Workflow Approvers')

@section('header-actions')
<div class="d-flex gap-2">
    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#bulkAssignmentModal">
        <i class="bx bx-user-plus"></i> Bulk Assignment
    </button>
    <a href="{{ route('workflows.assign-staff', $workflow->id) }}" class="btn btn-info">
        <i class="bx bx-user-plus"></i> Assign Staff
    </a>
    <a href="{{ route('workflows.show', $workflow->id) }}" class="btn btn-secondary">
        <i class="bx bx-arrow-back"></i> Back to Workflow
    </a>
</div>
@endsection

@section('content')
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bx bx-user-check me-2 text-primary"></i>{{ $workflow->workflow_name }} - Approvers Overview</h5>
        <div class="text-muted small mt-1">
            <i class="bx bx-info-circle me-1"></i>Complete overview of all approvers assigned to this workflow
        </div>
    </div>
    <div class="card-body p-4">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif



        <!-- Workflow Summary -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="card-title text-primary">Total Definitions</h6>
                        <h3 class="text-primary">{{ $workflowDefinitions->count() }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="card-title text-success">Total Approvers</h6>
                        <h3 class="text-success">{{ $workflowDefinitions->sum(function($def) { return $def->approvers->count(); }) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="card-title text-info">Active Approvers</h6>
                        <h3 class="text-info">{{ $workflowDefinitions->sum(function($def) { 
                            return $def->approvers->filter(function($app) { 
                                return !$app->end_date || $app->end_date >= now()->toDateString(); 
                            })->count(); 
                        }) }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="searchApprover" class="form-label fw-semibold">
                            <i class="bx bx-search me-1 text-primary"></i>Search Approvers:
                        </label>
                        <input type="text" id="searchApprover" class="form-control" placeholder="Search by name, division, or role...">
                    </div>
                    <div class="col-md-3">
                        <label for="filterStatus" class="form-label fw-semibold">
                            <i class="bx bx-filter me-1 text-primary"></i>Status:
                        </label>
                        <select id="filterStatus" class="form-select">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filterDefinition" class="form-label fw-semibold">
                            <i class="bx bx-check-shield me-1 text-primary"></i>Workflow Definition:
                        </label>
                        <select id="filterDefinition" class="form-select">
                            <option value="">All Definitions</option>
                            @foreach($workflowDefinitions as $definition)
                                <option value="{{ $definition->id }}">{{ $definition->role }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" id="clearFilters" class="btn btn-outline-secondary w-100">
                            <i class="bx bx-refresh me-1"></i>Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approvers by Definition -->
        @forelse($workflowDefinitions as $definition)
            <div class="card border shadow-sm mb-4" data-definition-id="{{ $definition->id }}">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0"><i class="bx bx-check-shield me-2 text-primary"></i>{{ $definition->role }}</h6>
                        <div class="text-muted small mt-1">
                            <i class="bx bx-sort me-1"></i>Approval Order: {{ $definition->approval_order }}
                            @if($definition->is_enabled)
                                <span class="badge bg-success ms-2">Enabled</span>
                            @else
                                <span class="badge bg-danger ms-2">Disabled</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <span class="badge bg-primary">{{ $definition->approvers->count() }} Approver(s)</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    @if($definition->approvers->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Staff Name</th>
                                        <th>Division</th>
                                        <th>OIC (Stand-in)</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($definition->approvers as $approver)
                                        <tr data-staff-id="{{ $approver->staff_id }}" 
                                            data-oic-staff-id="{{ $approver->oic_staff_id }}"
                                            data-start-date="{{ $approver->start_date }}"
                                            data-end-date="{{ $approver->end_date }}">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm me-2">
                                                        <div class="avatar-title bg-primary rounded-circle">
                                                            {{ substr($approver->staff->fname ?? '', 0, 1) }}{{ substr($approver->staff->lname ?? '', 0, 1) }}
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold">{{ $approver->staff->fname ?? 'N/A' }} {{ $approver->staff->lname ?? 'N/A' }}</div>
                                                        <small class="text-muted">Staff ID: {{ $approver->staff_id }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">{{ $approver->staff->division_name ?? 'N/A' }}</span>
                                            </td>
                                            <td>
                                                @if($approver->oic_staff_id && $approver->oicStaff)
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm me-2">
                                                            <div class="avatar-title bg-warning rounded-circle">
                                                                {{ substr($approver->oicStaff->fname ?? '', 0, 1) }}{{ substr($approver->oicStaff->lname ?? '', 0, 1) }}
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div class="fw-semibold">{{ $approver->oicStaff->fname }} {{ $approver->oicStaff->lname }}</div>
                                                            <small class="text-muted">OIC</small>
                                                        </div>
                                                    </div>
                                                @else
                                                    <span class="text-muted">No OIC assigned</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-info">{{ \Carbon\Carbon::parse($approver->start_date)->format('M j, Y') }}</span>
                                            </td>
                                            <td>
                                                @if($approver->end_date)
                                                    <span class="badge bg-warning">{{ \Carbon\Carbon::parse($approver->end_date)->format('M j, Y') }}</span>
                                                @else
                                                    <span class="badge bg-success">No End Date</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if(!$approver->end_date || $approver->end_date >= now()->toDateString())
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-danger">Expired</span>
                                                @endif
                                            </td>

                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <div class="text-muted">
                                <i class="bx bx-user-x fs-1"></i>
                                <p class="mt-2 mb-0">No approvers assigned to this role</p>
                                <small>Click "Assign Staff" to add approvers</small>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center py-5">
                <div class="text-muted">
                    <i class="bx bx-list-x fs-1"></i>
                    <h5 class="mt-3">No Workflow Definitions Found</h5>
                    <p class="mb-3">This workflow doesn't have any approval steps defined yet.</p>
                    <a href="{{ route('workflows.add-definition', $workflow->id) }}" class="btn btn-primary">
                        <i class="bx bx-plus me-1"></i> Add Definition
                    </a>
                </div>
            </div>
        @endforelse
    </div>
</div>



<!-- Bulk Assignment Modal -->
<div class="modal fade" id="bulkAssignmentModal" tabindex="-1" aria-labelledby="bulkAssignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkAssignmentModalLabel">
                    <i class="bx bx-user-plus me-2 text-primary"></i>Bulk Approver Assignment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="bulkAssignmentForm">
                <div class="modal-body">
                    <input type="hidden" id="bulk_workflow_id" name="workflow_id">
                    
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                        <strong>Quick Assignment:</strong> Select a workflow definition and assign multiple staff members at once.
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="bulk_workflow_dfn_id" class="form-label fw-semibold">
                                <i class="bx bx-check-shield me-1 text-primary"></i>Workflow Definition:
                            </label>
                            <select name="workflow_dfn_id" id="bulk_workflow_dfn_id" class="form-select" required>
                                <option value="">Select Workflow Definition</option>
                                @foreach($workflowDefinitions as $definition)
                                    <option value="{{ $definition->id }}">
                                        {{ $definition->role }} (Order: {{ $definition->approval_order }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="bulk_start_date" class="form-label fw-semibold">
                                <i class="bx bx-calendar-plus me-1 text-primary"></i>Start Date:
                            </label>
                            <input type="date" name="start_date" id="bulk_start_date" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bx bx-user me-1 text-primary"></i>Select Primary Approvers:
                            </label>
                            <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                @foreach($availableStaff as $staff)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="staff_ids[]" 
                                               value="{{ $staff->staff_id }}" id="staff_{{ $staff->staff_id }}">
                                        <label class="form-check-label" for="staff_{{ $staff->staff_id }}">
                                            {{ $staff->fname }} {{ $staff->lname }} 
                                            <small class="text-muted">({{ $staff->division_name }})</small>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bx bx-user-voice me-1 text-primary"></i>Select OIC (Optional):
                            </label>
                            <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="oic_staff_id" 
                                           value="" id="oic_none" checked>
                                    <label class="form-check-label" for="oic_none">
                                        <em>No OIC assigned</em>
                                    </label>
                                </div>
                                @foreach($availableStaff as $staff)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="oic_staff_id" 
                                               value="{{ $staff->staff_id }}" id="oic_{{ $staff->staff_id }}">
                                        <label class="form-check-label" for="oic_{{ $staff->staff_id }}">
                                            {{ $staff->fname }} {{ $staff->lname }} 
                                            <small class="text-muted">({{ $staff->division_name }})</small>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-plus-circle me-1"></i>Assign Selected Staff
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>



@push('scripts')
<script>
    function buildApiUrl(path) {
        return `${window.location.origin}/${path}`;
    }
    
    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Get session from laravel
        const baseUrl = @json(session('base_url', ''));

        // Initialize bulk assignment modal
        $('#bulkAssignmentModal').on('show.bs.modal', function() {
            $('#bulk_workflow_id').val('{{ $workflow->id }}');
            $('#bulk_start_date').val(new Date().toISOString().split('T')[0]);
        });

        // Handle bulk assignment form submission
        $('#bulkAssignmentForm').on('submit', function(e) {
            e.preventDefault();
            
            const form = $(this);
            const workflowId = $('#bulk_workflow_id').val();
            const formData = new FormData(this);
            
            // Validate that at least one staff is selected
            const selectedStaff = $('input[name="staff_ids[]"]:checked');
            if (selectedStaff.length === 0) {
                showAlert('Please select at least one staff member', 'warning');
                return;
            }
            
            $.ajax({
                url: `${baseUrl}bms/workflows/${workflowId}/approvers/bulk-assign`,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        // Close modal
                        const bulkModal = bootstrap.Modal.getInstance(document.getElementById('bulkAssignmentModal'));
                        if (bulkModal) {
                            bulkModal.hide();
                        }
                        
                        // Reset form
                        form[0].reset();
                        
                        // Show success message
                        showAlert(response.message, 'success');
                        
                        // Reload page to reflect changes
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showAlert(response.message, 'warning');
                    }
                },
                error: function(xhr) {
                    const response = xhr.responseJSON;
                    showAlert(response?.message || 'An error occurred while assigning approvers', 'danger');
                }
            });
        });

        // Helper function to show alerts
        function showAlert(message, type = 'success') {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            // Remove existing alerts
            $('.alert').remove();
            
            // Add new alert at the top of the card body
            $('.card-body').prepend(alertHtml);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                $('.alert').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }

        // Search and filter functionality
        function filterApprovers() {
            const searchTerm = $('#searchApprover').val().toLowerCase();
            const statusFilter = $('#filterStatus').val();
            const definitionFilter = $('#filterDefinition').val();

            $('.card.border.shadow-sm.mb-4').each(function() {
                const definitionCard = $(this);
                const definitionId = definitionCard.data('definition-id');
                let showDefinition = true;

                // Filter by definition
                if (definitionFilter && definitionId != definitionFilter) {
                    showDefinition = false;
                }

                if (showDefinition) {
                    let hasVisibleApprovers = false;
                    definitionCard.find('tbody tr').each(function() {
                        const row = $(this);
                        const staffName = row.find('.fw-semibold').text().toLowerCase();
                        const division = row.find('.badge.bg-light').text().toLowerCase();
                        const status = row.find('td:nth-child(6) .badge').text().toLowerCase();
                        
                        let showRow = true;

                        // Search filter
                        if (searchTerm && !staffName.includes(searchTerm) && !division.includes(searchTerm)) {
                            showRow = false;
                        }

                        // Status filter
                        if (statusFilter) {
                            if (statusFilter === 'active' && !status.includes('active')) {
                                showRow = false;
                            } else if (statusFilter === 'expired' && !status.includes('expired')) {
                                showRow = false;
                            }
                        }

                        if (showRow) {
                            row.show();
                            hasVisibleApprovers = true;
                        } else {
                            row.hide();
                        }
                    });

                    // Show/hide definition card based on visible approvers
                    if (hasVisibleApprovers || (!searchTerm && !statusFilter)) {
                        definitionCard.show();
                    } else {
                        definitionCard.hide();
                    }
                } else {
                    definitionCard.hide();
                }
            });
        }

        // Bind search and filter events
        $('#searchApprover, #filterStatus, #filterDefinition').on('input change', filterApprovers);

        // Clear filters
        $('#clearFilters').on('click', function() {
            $('#searchApprover').val('');
            $('#filterStatus').val('');
            $('#filterDefinition').val('');
            filterApprovers();
        });

</script>

<style>
.avatar-sm {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
    color: white;
}

.avatar-title {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}
</style>
@endpush
@endsection 