@extends('layouts.app')

@section('title', 'Workflow Details')

@section('header', 'Workflow Details')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('workflows.edit', $workflow->id) }}" class="btn btn-warning">
        <i class="bx bx-edit"></i> Edit Workflow
    </a>
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
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Basic Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-md-4 fw-bold">ID:</div>
                            <div class="col-md-8">{{ $workflow->id }}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-4 fw-bold">Name:</div>
                            <div class="col-md-8">{{ $workflow->workflow_name }}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-4 fw-bold">Status:</div>
                            <div class="col-md-8">
                                @if($workflow->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Description</h6>
                    </div>
                    <div class="card-body">
                        <p>{{ $workflow->Description }}</p>
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
                                        @if($definition->is_enabled)
                                            <span class="badge bg-success">Enabled</span>
                                        @else
                                            <span class="badge bg-danger">Disabled</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Edit Definition">
                                                <i class="bx bx-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Delete Definition">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4">
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

@push('scripts')
<script>
    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });
</script>
@endpush
@endsection
