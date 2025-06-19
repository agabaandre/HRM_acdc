@extends('layouts.app')

@section('title', 'Edit Non-Travel Memo Category: ' . $category->name)

@section('header', 'Edit Category')

@section('header-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('non-travel-categories.show', $category->id) }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Details
        </a>
        <form action="{{ route('non-travel-categories.destroy', $category->id) }}" 
              method="POST" 
              class="d-inline"
              onsubmit="return confirm('Are you sure you want to delete this category? This action cannot be undone.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">
                <i class="bx bx-trash"></i> Delete
            </button>
        </form>
    </div>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        <form action="{{ route('non-travel-categories.update', $category->id) }}" method="POST" id="editCategoryForm">
            @csrf
            @method('PUT')
            
            <div class="mb-3">
                <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                <input type="text" 
                       class="form-control @error('name') is-invalid @enderror" 
                       id="name" 
                       name="name" 
                       value="{{ old('name', $category->name) }}" 
                       required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex justify-content-end">
                <a href="{{ route('non-travel-categories.show', $category->id) }}" class="btn btn-light me-2">
                    <i class="bx bx-x"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bx bx-check"></i> Update Category
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize form validation
        const form = document.getElementById('editCategoryForm');
        
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
</script>
@endpush
@endsection
