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
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="{{ route('workflows.show', $workflow->id) }}" class="btn btn-sm btn-light action-btn text-info" data-bs-toggle="tooltip" title="View Details">
                                        <i class="bx bx-show fs-6"></i>
                                    </a>
                                    <a href="{{ route('workflows.edit', $workflow->id) }}" class="btn btn-sm btn-light action-btn text-primary" data-bs-toggle="tooltip" title="Edit Workflow">
                                        <i class="bx bx-edit fs-6"></i>
                                    </a>
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