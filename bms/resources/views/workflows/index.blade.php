@extends('layouts.app')

@section('title', 'Workflows')

@section('header', 'Workflows')

@section('header-actions')
<a href="{{ route('workflows.create') }}" class="btn btn-success">
    <i class="bx bx-plus"></i> Add Workflow
</a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bx bx-git-branch me-2 text-primary"></i>All Workflows</h5>
        <div>
            <form action="{{ route('workflows.index') }}" method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Search workflows..." value="{{ request('search') }}">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bx bx-search"></i>
                </button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        @if(session('success'))
            <div class="alert alert-success m-3">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger m-3">
                {{ session('error') }}
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($workflows as $workflow)
                        <tr>
                            <td>{{ $workflow->id }}</td>
                            <td>{{ $workflow->workflow_name }}</td>
                            <td>{{ Str::limit($workflow->Description, 50) }}</td>
                            <td>
                                @if($workflow->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('workflows.show', $workflow->id) }}" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    <a href="{{ route('workflows.edit', $workflow->id) }}" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Edit">
                                        <i class="bx bx-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteWorkflow{{ $workflow->id }}" data-bs-toggle="tooltip" title="Delete">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </div>

                                <!-- Delete Modal -->
                                <div class="modal fade" id="deleteWorkflow{{ $workflow->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Delete Workflow</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete <strong>{{ $workflow->workflow_name }}</strong>?</p>
                                                <p class="text-danger"><small>This action cannot be undone. If this workflow has associated activities, deletion may fail.</small></p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form action="{{ route('workflows.destroy', $workflow->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bx bx-folder-open fs-1"></i>
                                    <p class="mt-2">No workflows found</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if(isset($workflows) && method_exists($workflows, 'hasPages') && $workflows->hasPages())
        <div class="card-footer">
            {{ $workflows->links() }}
        </div>
    @endif
</div>
@endsection

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