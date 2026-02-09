@extends('layouts.app')

@section('title', 'Create Partner')

@section('header', 'Create Partner')

@section('header-actions')
    <a href="{{ route('partners.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to List
    </a>
@endsection

@section('content')
    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0"><i class="bx bx-plus-circle me-2"></i>New Partner</h5>
        </div>
        <div class="card-body p-4">
            <form action="{{ route('partners.store') }}" method="POST">
                @csrf

                <div class="mb-4">
                    <label for="name" class="form-label fw-semibold">Partner Name <span
                            class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-lg @error('name') is-invalid @enderror" id="name"
                        name="name" value="{{ old('name') }}" placeholder="Enter partner name" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4">
                    <a href="{{ route('partners.index') }}" class="btn btn-outline-secondary px-4">
                        <i class="bx bx-arrow-back me-1"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm">
                        <i class="bx bx-save me-2"></i> Save Partner
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
