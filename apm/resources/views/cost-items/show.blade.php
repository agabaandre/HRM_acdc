@extends('layouts.app')

@section('title', 'View Cost Item')

@section('header', 'Cost Item Details')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('cost-items.edit', $costItem->id) }}" class="btn btn-warning">
        <i class="bx bx-edit"></i> Edit Cost Item
    </a>
    <a href="{{ route('cost-items.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to List
    </a>
</div>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ $costItem->name }}</h5>
                <small class="text-muted">Cost Item ID: {{ $costItem->id }}</small>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong><i class="bx bx-purchase-tag me-2 text-primary"></i>Name:</strong>
                            <p class="mt-1">{{ $costItem->name }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong><i class="bx bx-category me-2 text-primary"></i>Cost Type:</strong>
                            <p class="mt-1">
                                <span class="badge {{ $costItem->cost_type == 'Individual Cost' ? 'bg-primary' : 'bg-secondary' }}">
                                    {{ $costItem->cost_type }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong><i class="bx bx-calendar me-2 text-primary"></i>Created At:</strong>
                            <p class="mt-1">{{ $costItem->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong><i class="bx bx-calendar-edit me-2 text-primary"></i>Last Updated:</strong>
                            <p class="mt-1">{{ $costItem->updated_at->format('M d, Y h:i A') }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-light">
                <form action="{{ route('cost-items.destroy', $costItem->id) }}" 
                      method="POST" 
                      class="d-inline"
                      onsubmit="return confirm('Are you sure you want to delete this cost item? This action cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bx bx-trash"></i> Delete Cost Item
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .card {
        border: none;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    .card-header {
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }
    .card-footer {
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        background-color: #f8f9fa;
    }
    .badge {
        font-size: 0.85em;
        padding: 0.4em 0.8em;
    }
</style>
@endpush
@endsection
