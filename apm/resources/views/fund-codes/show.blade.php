@extends('layouts.app')

@section('title', 'Fund Code Details')

@section('header', 'Fund Code Details')

@section('header-actions')
<div class="d-flex gap-2">

    <a href="{{ route('fund-codes.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to List
    </a>
    <a href="{{ route('fund-codes.edit', $fundCode) }}" class="btn btn-warning">
        <i class="bx bx-edit"></i> Edit
    </a>
    <a href="{{ route('fund-codes.transactions', $fundCode) }}" class="btn btn-primary">
        <i class="bx bx-history"></i> View Transactions
    </a>
</div>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bx bx-info-circle me-2"></i>Fund Code Information</h5>
            </div>
            <div class="card-body p-4">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Fund Code</h6>
                        <h4 class="mb-0">{{ $fundCode->code }}</h4>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <h6 class="text-muted mb-1">Status</h6>
                        @if($fundCode->is_active)
                            <span class="badge bg-success fs-6 px-3 py-2">Active</span>
                        @else
                            <span class="badge bg-danger fs-6 px-3 py-2">Inactive</span>
                        @endif
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="text-muted mb-1">Name</h6>
                    <h5>{{ $fundCode->name }}</h5>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Fund Type</h6>
                        <h5>{{ $fundCode->fundType->name ?? 'N/A' }}</h5>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Division</h6>
                        <h5>{{ $fundCode->division->division_name ?? 'N/A' }}</h5>
                    </div>
                </div>

                @if($fundCode->description)
                <div class="mb-0">
                    <h6 class="text-muted mb-2">Description</h6>
                    <div class="p-3 bg-light rounded">
                        {{ $fundCode->description }}
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bx bx-time me-2 text-primary"></i>Timestamps</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Created At</span>
                        <span>{{ $fundCode->created_at->format('Y-m-d H:i') }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Last Updated</span>
                        <span>{{ $fundCode->updated_at->format('Y-m-d H:i') }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bx bx-cog me-2 text-primary"></i>Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('fund-codes.edit', $fundCode) }}" class="btn btn-warning">
                        <i class="bx bx-edit me-2"></i> Edit Fund Code
                    </a>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bx bx-trash me-2"></i> Delete Fund Code
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
                <h5 class="modal-title">Delete Fund Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the fund code <strong>{{ $fundCode->code }}</strong>?</p>
                <p class="text-danger"><small>This action cannot be undone. Please ensure this fund code is not in use before deleting.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="{{ route('fund-codes.destroy', $fundCode) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
