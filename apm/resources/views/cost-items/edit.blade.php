@extends('layouts.app')

@section('title', 'Edit Cost Item')

@section('header', 'Edit Cost Item')

@section('header-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('cost-items.show', $costItem->id) }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i> Back to Details
        </a>
        <form action="{{ route('cost-items.destroy', $costItem->id) }}" 
              method="POST" 
              class="d-inline"
              onsubmit="return confirm('Are you sure you want to delete this cost item? This action cannot be undone.');">
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
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bx bx-edit me-2 text-primary"></i>Edit Cost Item: {{ $costItem->name }}</h5>
    </div>
    <div class="card-body p-4">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('cost-items.update', $costItem->id) }}" method="POST" id="editCostItemForm">
            @csrf
            @method('PUT')
            
            <div class="row g-4">
                <div class="col-md-12">
                    <div class="form-group position-relative">
                        <label for="name" class="form-label fw-semibold">
                            <i class="bx bx-purchase-tag me-1 text-primary"></i>Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control form-control-lg @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name', $costItem->name) }}" 
                               placeholder="Enter cost item name"
                               required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group position-relative">
                        <label for="cost_type" class="form-label fw-semibold">
                            <i class="bx bx-category me-1 text-primary"></i>Cost Type <span class="text-danger">*</span>
                        </label>
                        <select class="form-select form-select-lg @error('cost_type') is-invalid @enderror" 
                                id="cost_type" 
                                name="cost_type" 
                                required>
                            <option value="" disabled>Select Cost Type</option>
                            <option value="Individual Cost" {{ (old('cost_type', $costItem->cost_type) == 'Individual Cost') ? 'selected' : '' }}>Individual Cost</option>
                            <option value="Other Cost" {{ (old('cost_type', $costItem->cost_type) == 'Other Cost') ? 'selected' : '' }}>Other Cost</option>
                        </select>
                        @error('cost_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <a href="{{ route('cost-items.show', $costItem->id) }}" class="btn btn-light">
                    <i class="bx bx-x me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bx bx-save me-1"></i> Update Cost Item
                </button>
            </div>
        </form>
    </div>
</div>

@push('styles')
<style>
    .form-control-lg, .form-select-lg {
        border-radius: 0.5rem;
        padding: 0.75rem 1rem;
    }
    .form-label {
        font-weight: 500;
        margin-bottom: 0.5rem;
    }
    .invalid-feedback {
        font-size: 0.875em;
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize form validation
        const form = document.getElementById('editCostItemForm');
        
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
