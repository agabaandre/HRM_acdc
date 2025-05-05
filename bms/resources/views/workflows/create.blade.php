@extends('layouts.app')

@section('title', 'Create Workflow')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title">Create New Workflow</h4>
                        <a href="{{ route('workflows.index') }}" class="btn btn-secondary">Back to Workflows</a>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('workflows.store') }}" method="POST">
                            @csrf

                            <div class="mb-3">
                                <label for="workflow_name" class="form-label">Workflow Name</label>
                                <input type="text" class="form-control @error('workflow_name') is-invalid @enderror"
                                    id="workflow_name" name="workflow_name" value="{{ old('workflow_name') }}" required>
                                @error('workflow_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="Description" class="form-label">Description</label>
                                <textarea class="form-control @error('Description') is-invalid @enderror" id="Description"
                                    name="Description" rows="3" required>{{ old('Description') }}</textarea>
                                @error('Description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" {{ old('is_active') ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>

                            <button type="submit" class="btn btn-primary">Create Workflow</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection