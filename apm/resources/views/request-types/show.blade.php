@extends('layouts.app')

@section('title', 'Request Type: ' . $requestType->name)

@section('header', 'Request Type Details')

@section('header-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('request-types.edit', $requestType) }}" class="btn btn-primary">
            <i class="bx bx-edit"></i> Edit
        </a>
        <a href="{{ route('request-types.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to List
        </a>
    </div>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Request Type Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h6 class="text-muted mb-1">Name</h6>
                            <p class="mb-0">{{ $requestType->name }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h6 class="text-muted mb-1">Created At</h6>
                            <p class="mb-0">{{ $requestType->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h6 class="text-muted mb-1">Last Updated</h6>
                            <p class="mb-0">{{ $requestType->updated_at->format('M d, Y h:i A') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('request-types.edit', $requestType) }}" class="btn btn-primary">
                        <i class="bx bx-edit me-1"></i> Edit Request Type
                    </a>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bx bx-trash me-1"></i> Delete Request Type
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the request type <strong>{{ $requestType->name }}</strong>?</p>
                <div class="alert alert-warning mb-0">
                    <i class="bx bx-error me-2"></i>
                    This action cannot be undone.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="{{ route('request-types.destroy', $requestType) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bx bx-trash me-1"></i> Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
