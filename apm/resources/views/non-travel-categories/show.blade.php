@extends('layouts.app')

@section('title', 'Non-Travel Memo Category: ' . $category->name)

@section('header', 'Category Details')

@section('header-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('non-travel-categories.edit', $category->id) }}" class="btn btn-warning">
            <i class="bx bx-edit"></i> Edit
        </a>
        <a href="{{ route('non-travel-categories.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to List
        </a>
    </div>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ $category->name }}</h5>
                <small class="text-muted">Category ID: {{ $category->id }}</small>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong><i class="bx bx-purchase-tag me-2 text-primary"></i>Name:</strong>
                            <p class="mt-1">{{ $category->name }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong><i class="bx bx-calendar me-2 text-primary"></i>Created At:</strong>
                            <p class="mt-1">{{ $category->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong><i class="bx bx-calendar-edit me-2 text-primary"></i>Last Updated:</strong>
                            <p class="mt-1">{{ $category->updated_at->format('M d, Y h:i A') }}</p>
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
                    <a href="{{ route('non-travel-categories.edit', $category->id) }}" class="btn btn-primary">
                        <i class="bx bx-edit"></i> Edit Category
                    </a>
                    <form action="{{ route('non-travel-categories.destroy', $category->id) }}" 
                          method="POST" 
                          onsubmit="return confirm('Are you sure you want to delete this category? This action cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="bx bx-trash"></i> Delete Category
                        </button>
                    </form>
                    <a href="{{ route('non-travel-categories.index') }}" class="btn btn-outline-secondary">
                        <i class="bx bx-list-ul"></i> View All Categories
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
@endsection
