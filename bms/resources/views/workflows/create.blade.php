@extends('layouts.app')

@section('title', 'Create Workflow')

@section('header', 'Create New Workflow')

@section('header-actions')
<a href="{{ route('workflows.index') }}" class="btn btn-secondary">
    <i class="bx bx-arrow-back"></i> Back to Workflows
</a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bx bx-plus-circle me-2 text-primary"></i>Workflow Details</h5>
    </div>
    <div class="card-body">
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        <form action="{{ route('workflows.store') }}" method="POST">
            @csrf

            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="workflow_name" class="form-label">Workflow Name</label>
                    <input type="text" class="form-control @error('workflow_name') is-invalid @enderror"
                        id="workflow_name" name="workflow_name" value="{{ old('workflow_name') }}" required>
                    @error('workflow_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="Description" class="form-label">Description</label>
                    <textarea class="form-control @error('Description') is-invalid @enderror" id="Description"
                        name="Description" rows="4" required>{{ old('Description') }}</textarea>
                    @error('Description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active') ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">Active Status</label>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bx bx-save me-1"></i> Save Workflow
                </button>
            </div>
        </form>
    </div>
</div>
@endsection