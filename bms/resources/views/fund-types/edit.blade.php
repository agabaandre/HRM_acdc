@extends('layouts.app')

@section('title', 'Edit Fund Type')

@section('header', 'Edit Fund Type')

@section('header-actions')
<a href="{{ route('fund-types.index') }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back"></i> Back to List
</a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="bx bx-edit me-2"></i>Edit Fund Type</h5>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('fund-types.update', $fundType) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label for="name" class="form-label fw-semibold">Fund Type Name <span class="text-danger">*</span></label>
                <input type="text" 
                       class="form-control form-control-lg @error('name') is-invalid @enderror" 
                       id="name" 
                       name="name" 
                       value="{{ old('name', $fundType->name) }}" 
                       placeholder="Enter fund type name"
                       required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Example: Grant, Operational Fund, etc.</small>
            </div>

            <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4">
                <a href="{{ route('fund-types.index') }}" class="btn btn-outline-secondary px-4">
                    <i class="bx bx-arrow-back me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-warning btn-lg px-5 shadow-sm">
                    <i class="bx bx-save me-2"></i> Update Fund Type
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
