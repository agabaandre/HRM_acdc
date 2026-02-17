@extends('layouts.app')

@section('title', 'Add FAQ Category')
@section('header', 'Add FAQ Category')

@section('header-actions')
<a href="{{ route('faq-categories.index') }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back"></i> Back to list
</a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0"><i class="bx bx-plus-circle me-2"></i>New category</h5>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('faq-categories.store') }}" method="POST">
            @csrf

            <div class="mb-4">
                <label for="name" class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-lg @error('name') is-invalid @enderror" id="name"
                    name="name" value="{{ old('name') }}" required maxlength="255" placeholder="e.g. Staff Portal">
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label for="slug" class="form-label fw-semibold">Slug</label>
                <input type="text" class="form-control @error('slug') is-invalid @enderror" id="slug"
                    name="slug" value="{{ old('slug') }}" maxlength="255" placeholder="Leave blank to auto-generate from name">
                @error('slug')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label for="description" class="form-label fw-semibold">Description</label>
                <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description') }}</textarea>
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label for="sort_order" class="form-label fw-semibold">Sort order</label>
                <input type="number" class="form-control" id="sort_order" name="sort_order" value="{{ old('sort_order', (App\Models\FaqCategory::max('sort_order') ?? 0) + 1) }}" min="0">
            </div>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Active (show on public FAQ page)</label>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4">
                <a href="{{ route('faq-categories.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg px-5">Save category</button>
            </div>
        </form>
    </div>
</div>
@endsection
