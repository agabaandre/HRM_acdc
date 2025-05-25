@extends('layouts.app')

@section('title', 'Edit Division')

@section('header', 'Edit Division')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('divisions.show', $division->id) }}" class="btn btn-secondary">
        <i class="bx bx-arrow-back"></i> View Division
    </a>
    <a href="{{ route('divisions.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-list-ul"></i> All Divisions
    </a>
</div>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bx bx-edit me-2 text-primary"></i>Division Details</h5>
    </div>
    <div class="card-body">
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        
        <form action="{{ route('divisions.update', $division->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="division_name" class="form-label">Division Name</label>
                    <input type="text" class="form-control @error('division_name') is-invalid @enderror"
                        id="division_name" name="division_name"
                        value="{{ old('division_name', $division->division_name) }}" required>
                    @error('division_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="division_head" class="form-label">Division Head (User ID)</label>
                    <input type="number" class="form-control @error('division_head') is-invalid @enderror"
                        id="division_head" name="division_head"
                        value="{{ old('division_head', $division->division_head) }}" required>
                    @error('division_head')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="focal_person" class="form-label">Focal Person (User ID)</label>
                    <input type="number" class="form-control @error('focal_person') is-invalid @enderror"
                        id="focal_person" name="focal_person"
                        value="{{ old('focal_person', $division->focal_person) }}" required>
                    @error('focal_person')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="admin_assistant" class="form-label">Admin Assistant (User ID)</label>
                    <input type="number" class="form-control @error('admin_assistant') is-invalid @enderror"
                        id="admin_assistant" name="admin_assistant"
                        value="{{ old('admin_assistant', $division->admin_assistant) }}" required>
                    @error('admin_assistant')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="finance_officer" class="form-label">Finance Officer (User ID)</label>
                    <input type="number" class="form-control @error('finance_officer') is-invalid @enderror"
                        id="finance_officer" name="finance_officer"
                        value="{{ old('finance_officer', $division->finance_officer) }}" required>
                    @error('finance_officer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ $division->is_active ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">Active Status</label>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bx bx-save me-1"></i> Update Division
                </button>
            </div>
        </form>
    </div>
</div>
@endsection