@extends('layouts.app')

@section('title', 'Edit Request Type')

@section('header', 'Edit Request Type')

@section('header-actions')
<a href="{{ route('request-types.index') }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back"></i> Back to List
</a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="bx bx-edit me-2"></i>Edit Request Type</h5>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('request-types.update', $requestType) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <div class="form-group position-relative">
                    <label for="request_type" class="form-label fw-semibold">
                        <i class="bx bx-text me-1 text-primary"></i>Request Type Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           class="form-control form-control-lg @error('request_type') is-invalid @enderror" 
                           id="request_type" 
                           name="request_type" 
                           value="{{ old('request_type', $requestType->request_type) }}" 
                           placeholder="Enter request type name"
                           required>
                    <small class="text-muted mt-1 d-block">E.g., Travel Request, Meeting Request, Purchase Request</small>
                    @error('request_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-4">
                <div class="form-group position-relative">
                    <label for="description" class="form-label fw-semibold">
                        <i class="bx bx-detail me-1 text-primary"></i>Description
                    </label>
                    <textarea 
                        class="form-control @error('description') is-invalid @enderror" 
                        id="description" 
                        name="description" 
                        rows="4" 
                        placeholder="Enter detailed description">{{ old('description', $requestType->description) }}</textarea>
                    <small class="text-muted mt-1 d-block">Provide a clear description of when this request type should be used</small>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-4">
                <div class="form-group position-relative">
                    <label for="workflow_id" class="form-label fw-semibold">
                        <i class="bx bx-git-branch me-1 text-primary"></i>Associated Workflow
                    </label>
                    <select name="workflow_id" id="workflow_id" class="form-select @error('workflow_id') is-invalid @enderror">
                        <option value="">Select Workflow (Optional)</option>
                        @foreach($workflows as $workflow)
                            <option value="{{ $workflow->id }}" {{ old('workflow_id', $requestType->workflow_id) == $workflow->id ? 'selected' : '' }}>
                                {{ $workflow->name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted mt-1 d-block">Select a workflow to associate with this request type</small>
                    @error('workflow_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $requestType->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="is_active">Active Status</label>
                </div>
                <small class="text-muted d-block">Inactive request types will not be available for selection in activities or memos</small>
            </div>

            <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4">
                <a href="{{ route('request-types.index') }}" class="btn btn-outline-secondary px-4">
                    <i class="bx bx-arrow-back me-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-warning btn-lg px-5 shadow-sm">
                    <i class="bx bx-save me-2"></i> Update Request Type
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
