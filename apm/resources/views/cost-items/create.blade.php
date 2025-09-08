@extends('layouts.app')

@section('title', 'Create Cost Item')
@section('header', 'Create Cost Item')

@section('header-actions')
<a href="{{ route('cost-items.index') }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back me-1 text-success"></i> Back to List
</a>
@endsection

@section('content')
<div class="card shadow-sm border-0 mb-5">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 text-dark">
            <i class="bx bx-dollar me-2"></i> Cost Item Details
        </h5>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('cost-items.store') }}" method="POST" id="costItemForm">
            @csrf
            <input type="hidden" name="_token" value="{{ csrf_token() }}">

            <!-- Section 1: Basic Information -->
            <div class="mb-5">
                <h6 class="fw-bold text-success mb-4 border-bottom pb-2">
                    <i class="fas fa-info-circle me-2"></i> Basic Information
                </h6>
                
                <div class="row g-4">
                    <!-- Cost Item Name -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name" class="form-label fw-semibold">
                                <i class="bx bx-purchase-tag me-1 text-success"></i> Cost Item Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control border-success @error('name') is-invalid @enderror" 
                                   id="name" 
                                   name="name" 
                                   value="{{ old('name') }}" 
                                   placeholder="Enter cost item name"
                                   required>
                            <small class="text-muted mt-1 d-block">Name of the cost item</small>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Cost Type -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="cost_type" class="form-label fw-semibold">
                                <i class="bx bx-category me-1 text-success"></i> Cost Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-select border-success @error('cost_type') is-invalid @enderror" 
                                    id="cost_type" 
                                    name="cost_type" 
                                    required>
                                <option value="" disabled {{ old('cost_type') ? '' : 'selected' }}>Select Cost Type</option>
                                <option value="Individual Cost" {{ old('cost_type') == 'Individual Cost' ? 'selected' : '' }}>Individual Cost</option>
                                <option value="Other Cost" {{ old('cost_type') == 'Other Cost' ? 'selected' : '' }}>Other Cost</option>
                            </select>
                            <small class="text-muted mt-1 d-block">Type of cost item</small>
                            @error('cost_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex justify-content-end gap-3 mt-5 pt-4 border-top">
                <a href="{{ route('cost-items.index') }}" class="btn btn-light px-4">
                    <i class="bx bx-x me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-success px-4">
                    <i class="bx bx-save me-1"></i> Save Cost Item
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
        const form = document.getElementById('costItemForm');
        
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
