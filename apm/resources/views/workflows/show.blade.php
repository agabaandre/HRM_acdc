@extends('layouts.app')

@section('title', 'Workflow Details')

@section('header', 'Workflow Details')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('workflows.index') }}" class="btn btn-secondary">
        <i class="bx bx-arrow-back"></i> Back to Workflows
    </a>
</div>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bx bx-git-branch me-2 text-primary"></i>{{ $workflow->workflow_name }}</h5>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="bx bx-info-circle me-2"></i>Workflow Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-3 fw-bold text-muted">ID:</div>
                            <div class="col-md-9">
                                <span class="badge bg-primary">{{ $workflow->id }}</span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3 fw-bold text-muted">Name:</div>
                            <div class="col-md-9">{{ $workflow->workflow_name }}</div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3 fw-bold text-muted">Description:</div>
                            <div class="col-md-9">{{ $workflow->Description }}</div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3 fw-bold text-muted">Status:</div>
                            <div class="col-md-9">
                                @if($workflow->is_active)
                                    <span class="badge bg-success"><i class="bx bx-check-circle me-1"></i>Active</span>
                                @else
                                    <span class="badge bg-danger"><i class="bx bx-x-circle me-1"></i>Inactive</span>
                                @endif
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3 fw-bold text-muted">Created:</div>
                            <div class="col-md-9">
                                <i class="bx bx-calendar me-1"></i>N/A
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3 fw-bold text-muted">Updated:</div>
                            <div class="col-md-9">
                                <i class="bx bx-calendar me-1"></i>N/A
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="bx bx-stats me-2"></i>Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Definitions:</span>
                                    <span class="badge bg-info">{{ $workflowDefinitions->count() }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Enabled:</span>
                                    <span class="badge bg-success">{{ $workflowDefinitions->where('is_enabled', 1)->count() }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Disabled:</span>
                                    <span class="badge bg-warning">{{ $workflowDefinitions->where('is_enabled', 0)->count() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bx bx-list-check me-2 text-primary"></i>Workflow Definitions</h5>
                <div>
                    <a href="{{ route('workflows.approvers', $workflow->id) }}"
                        class="btn btn-success btn-sm me-2">
                        <i class="bx bx-user-check me-1"></i> Approvers
                    </a>
                    <a href="{{ route('workflows.assign-staff', $workflow->id) }}"
                        class="btn btn-info btn-sm me-2">
                        <i class="bx bx-user-plus me-1"></i> Assign Staff
                    </a>
                    <a href="{{ route('workflows.add-definition', $workflow->id) }}"
                        class="btn btn-primary btn-sm">
                        <i class="bx bx-plus me-1"></i> Add Definition
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Role</th>
                                <th>Approval Order</th>
                                <th>Fund Type</th>
                                <th>Division Specific</th>
                                <th>Print Order</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($workflowDefinitions as $definition)
                                <tr>
                                    <td>{{ $definition->id }}</td>
                                    <td>{{ $definition->role }}</td>
                                    <td>{{ $definition->approval_order }}</td>
                                    <td>
                                        @if($definition->fund_type)
                                            <span class="badge bg-info">{{ ucfirst($definition->fund_type) }}</span>
                                        @else
                                            <span class="badge bg-secondary">Not Set</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($definition->is_division_specific)
                                            <span class="badge bg-warning">Yes</span>
                                        @else
                                            <span class="badge bg-secondary">No</span>
                                        @endif
                                    </td>
                                    <td>{{ $definition->print_order ?? 'N/A' }}</td>
                                    <td>
                                        @if($definition->is_enabled)
                                            <span class="badge bg-success">Enabled</span>
                                        @else
                                            <span class="badge bg-danger">Disabled</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('workflows.edit-definition', [$workflow->id, $definition->id]) }}" 
                                               class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Edit Definition">
                                                <i class="bx bx-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger delete-definition-btn" 
                                                    data-bs-toggle="tooltip" title="Delete Definition"
                                                    data-definition-id="{{ $definition->id }}"
                                                    data-definition-role="{{ $definition->role }}">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bx bx-list-x fs-1"></i>
                                            <p class="mt-2">No workflow definitions found</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Definition Confirmation Modal -->
<div class="modal fade" id="deleteDefinitionModal" tabindex="-1" aria-labelledby="deleteDefinitionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteDefinitionModalLabel">
                    <i class="bx bx-trash me-2"></i>Delete Workflow Definition
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bx bx-error-circle me-2"></i>
                    <strong>Warning!</strong> This action cannot be undone.
                </div>
                <p>Are you sure you want to delete the workflow definition <strong id="definition-role-name"></strong>?</p>
                <p class="text-muted">This will also delete all associated approver assignments for this definition.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i>Cancel
                </button>
                <form id="delete-definition-form" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bx bx-trash me-1"></i>Delete Definition
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        console.log('Workflow show page loaded');
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Handle workflow definition delete buttons
        $(document).on('click', '.delete-definition-btn', function(e) {
            e.preventDefault();
            console.log('Delete definition button clicked');
            
            var definitionId = $(this).data('definition-id');
            var definitionRole = $(this).data('definition-role');
            
            console.log('Definition ID:', definitionId);
            console.log('Definition Role:', definitionRole);
            
            // Update modal content
            $('#definition-role-name').text('"' + definitionRole + '"');
            $('#delete-definition-form').attr('action', '{{ route("workflows.delete-definition", [$workflow->id, ":id"]) }}'.replace(':id', definitionId));
            
            // Show modal
            $('#deleteDefinitionModal').modal('show');
        });
    });
</script>
@endpush
@endsection
