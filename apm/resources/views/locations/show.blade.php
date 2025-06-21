@extends('layouts.app')

@section('title', 'Location: ' . $location->name)

@section('header', 'Location Details')

@section('header-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('locations.edit', $location->id) }}" class="btn btn-warning">
            <i class="bx bx-edit"></i> Edit
        </a>
        <a href="{{ route('locations.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to List
        </a>
    </div>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ $location->name }}</h5>
                <small class="text-muted">Location ID: {{ $location->id }}</small>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong><i class="bx bx-purchase-tag me-2 text-primary"></i>Name:</strong>
                            <p class="mt-1">{{ $location->name }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong><i class="bx bx-calendar me-2 text-primary"></i>Created At:</strong>
                            <p class="mt-1">{{ $location->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong><i class="bx bx-calendar-edit me-2 text-primary"></i>Last Updated:</strong>
                            <p class="mt-1">{{ $location->updated_at->format('M d, Y h:i A') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('locations.edit', $location->id) }}" class="btn btn-primary">
                        <i class="bx bx-edit"></i> Edit Location
                    </a>
                    <form action="{{ route('locations.destroy', $location->id) }}" 
                          method="POST" 
                          onsubmit="return confirm('Are you sure you want to delete this location? This action cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="bx bx-trash"></i> Delete Location
                        </button>
                    </form>
                    <a href="{{ route('locations.index') }}" class="btn btn-outline-secondary">
                        <i class="bx bx-list-ul"></i> View All Locations
                    </a>
                </div>
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
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn i {
        margin-right: 0.5rem;
    }
</style>
@endpush
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
