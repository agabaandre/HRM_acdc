@extends('layouts.app')

@section('title', 'Edit Directorate')

@section('header', 'Edit Directorate')

@section('header-actions')
<a href="{{ route('directorates.index') }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back"></i> Back to List
</a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="bx bx-edit me-2"></i>Edit Directorate</h5>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('directorates.update', $directorate) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row g-4 mb-4">
                <div class="col-md-12">
                    <div class="form-group position-relative">
                        <label for="name" class="form-label fw-semibold">
                            <i class="bx bx-text me-1 text-primary"></i>Directorate Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control form-control-lg @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name', $directorate->name) }}" 
                               placeholder="Enter directorate name"
                               required>
                        <small class="text-muted mt-1 d-block">Full name of the directorate</small>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $directorate->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="is_active">Active Status</label>
                </div>
                <small class="text-muted d-block">Inactive directorates will not be available for selection in other modules</small>
            </div>

            <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4">
                <a href="{{ route('directorates.index') }}" class="btn btn-outline-secondary px-4">
                    <i class="bx bx-arrow-back me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-warning btn-lg px-5 shadow-sm">
                    <i class="bx bx-save me-2"></i> Update Directorate
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
