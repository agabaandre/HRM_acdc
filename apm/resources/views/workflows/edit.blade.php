@extends('layouts.app')

@section('title', 'Edit Workflow')

@section('header', 'Edit Workflow')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('workflows.show', $workflow->id) }}" class="btn btn-info">
        <i class="bx bx-show"></i> View Workflow
    </a>
    <a href="{{ route('workflows.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-list-ul"></i> All Workflows
    </a>
</div>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bx bx-edit me-2 text-primary"></i>Workflow Details</h5>
    </div>
    <div class="card-body">
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        
        <form action="{{ route('workflows.update', $workflow->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bx bx-edit me-2"></i>Edit Workflow Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="workflow_name" class="form-label">
                                        <i class="bx bx-text me-1"></i>Workflow Name
                                    </label>
                                    <input type="text" class="form-control @error('workflow_name') is-invalid @enderror"
                                        id="workflow_name" name="workflow_name"
                                        value="{{ old('workflow_name', $workflow->workflow_name) }}" 
                                        placeholder="Enter workflow name" required>
                                    @error('workflow_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="Description" class="form-label">
                                        <i class="bx bx-detail me-1"></i>Description
                                    </label>
                                    <textarea class="form-control @error('Description') is-invalid @enderror" 
                                        id="Description" name="Description" rows="4"
                                        placeholder="Enter workflow description" required>{{ old('Description', $workflow->Description) }}</textarea>
                                    @error('Description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                            value="1" {{ old('is_active', $workflow->is_active) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">
                                            <i class="bx bx-power-off me-1"></i>Active Status
                                        </label>
                                    </div>
                                    <small class="text-muted">Enable or disable this workflow</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bx bx-info-circle me-2"></i>Workflow Info</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-5 fw-bold text-muted">ID:</div>
                                <div class="col-7">
                                    <span class="badge bg-primary">{{ $workflow->id }}</span>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold text-muted">Created:</div>
                                <div class="col-7">
                                    <small>N/A</small>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold text-muted">Updated:</div>
                                <div class="col-7">
                                    <small>N/A</small>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5 fw-bold text-muted">Status:</div>
                                <div class="col-7">
                                    @if($workflow->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-danger">Inactive</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                <a href="{{ route('workflows.show', $workflow->id) }}" class="btn btn-secondary me-2">
                    <i class="bx bx-x me-1"></i>Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bx bx-save me-1"></i>Update Workflow
                </button>
            </div>
        </form>
    </div>
</div>
@endsection