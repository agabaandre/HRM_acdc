@extends('layouts.app')

@section('title', 'Request Types')

@section('header', 'Request Types')

@section('header-actions')
    <a href="{{ route('request-types.create') }}" class="btn btn-primary">
        <i class="bx bx-plus"></i> Add Request Type
    </a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0">Request Types</h5>
    </div>
    
    <div class="card-body">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bx bx-check-circle me-2"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Request Type</th>
                        <th>Workflow</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requestTypes as $requestType)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <strong>{{ $requestType->request_type }}</strong>
                                @if($requestType->description)
                                    <div class="text-muted small">{{ Str::limit($requestType->description, 50) }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $requestType->workflow->name ?? 'N/A' }}
                            </td>
                            <td>
                                @if($requestType->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('request-types.show', $requestType) }}" 
                                       class="btn btn-sm btn-outline-info"
                                       data-bs-toggle="tooltip" 
                                       title="View">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    <a href="{{ route('request-types.edit', $requestType) }}" 
                                       class="btn btn-sm btn-outline-primary"
                                       data-bs-toggle="tooltip" 
                                       title="Edit">
                                        <i class="bx bx-edit"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal{{ $requestType->id }}"
                                            data-bs-toggle="tooltip" 
                                            title="Delete">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </div>

                                <!-- Delete Modal -->
                                <div class="modal fade" id="deleteModal{{ $requestType->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Confirm Deletion</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete the request type <strong>{{ $requestType->request_type }}</strong>?</p>
                                                <p class="text-danger mb-0"><small>This action cannot be undone.</small></p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form action="{{ route('request-types.destroy', $requestType) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="bx bx-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="mb-3">
                                        <i class="bx bx-package text-muted" style="font-size: 3rem;"></i>
                                    </div>
                                    <h5 class="text-muted mb-3">No request types found</h5>
                                    <p class="text-muted mb-4">Get started by adding your first request type</p>
                                    <a href="{{ route('request-types.create') }}" class="btn btn-primary">
                                        <i class="bx bx-plus"></i> Add Request Type
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    @if($requestTypes->hasPages())
        <div class="card-footer">
            {{ $requestTypes->links() }}
        </div>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
@endpush
@endsection
