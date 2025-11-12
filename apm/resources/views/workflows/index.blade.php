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
                <input type="text" name="search" class="form-control me-2" placeholder="Search workflows by name or description..." value="{{ request('search') }}">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bx bx-search me-1"></i>Search
                </button>
                @if(request('search'))
                    <a href="{{ route('workflows.index') }}" class="btn btn-outline-secondary ms-2">
                        <i class="bx bx-x me-1"></i>Clear
                    </a>
                @endif
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
                        <th width="60">#</th>
                        <th style="max-width: 200px;">Name</th>
                        <th style="max-width: 400px;">Description</th>
                        <th width="100">Status</th>
                        <th width="150" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($workflows as $workflow)
                        <tr>
                            <td class="fw-bold text-muted">{{ $workflow->id }}</td>
                            <td style="max-width: 200px; word-wrap: break-word; word-break: break-word; white-space: normal; overflow-wrap: break-word;">
                                <div class="fw-semibold text-dark" style="word-wrap: break-word; word-break: break-word; white-space: normal; overflow-wrap: break-word;">{{ $workflow->workflow_name }}</div>
                            </td>
                            <td style="max-width: 400px; word-wrap: break-word; word-break: break-word; white-space: normal; overflow-wrap: break-word;">
                                <div class="text-muted" style="word-wrap: break-word; word-break: break-word; white-space: normal; overflow-wrap: break-word;">{{ $workflow->Description }}</div>
                            </td>
                            <td>
                                @if($workflow->is_active)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bx bx-check-circle me-1"></i>Active
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                        <i class="bx bx-x-circle me-1"></i>Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end">
                                    <a href="{{ route('workflows.show', $workflow->id) }}" class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="View Workflow Details">
                                        <i class="bx bx-show me-1"></i>View
                                    </a>
                                    <a href="{{ route('workflows.edit', $workflow->id) }}" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit Workflow">
                                        <i class="bx bx-edit me-1"></i>Edit
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bx bx-folder-open fs-1 text-muted mb-3"></i>
                                    <h6 class="text-muted mb-2">No workflows found</h6>
                                    <p class="text-muted mb-0">Start by creating your first workflow</p>
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

@push('styles')
<style>
    .action-btn {
        transition: all 0.2s ease-in-out;
        min-width: 80px;
    }
    
    .action-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .table tbody tr:hover {
        background-color: rgba(0,123,255,0.05);
    }
    
    .badge {
        font-size: 0.75rem;
        padding: 0.5em 0.75em;
    }
    
    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }
    
    .btn-pressed {
        transform: scale(0.95);
    }
</style>
@endpush

@push('scripts')
<script>
    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // Add click animation to buttons
        $('.action-btn').on('click', function() {
            $(this).addClass('btn-pressed');
            setTimeout(() => {
                $(this).removeClass('btn-pressed');
            }, 150);
        });
    });
</script>
@endpush