@extends('layouts.app')

@section('title', 'Add Workflow Definition')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title">Add Workflow Definition for {{ $workflow->workflow_name }}</h4>
                        <a href="{{ route('workflows.show', $workflow->id) }}" class="btn btn-secondary">Back to
                            Workflow</a>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('workflows.store-definition', $workflow->id) }}" method="POST">
                            @csrf

                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <input type="text" class="form-control @error('role') is-invalid @enderror" id="role"
                                    name="role" value="{{ old('role') }}" required>
                                @error('role')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Enter the role responsible for this step (e.g., Division Head,
                                    Finance Officer)</div>
                            </div>

                            <div class="mb-3">
                                <label for="approval_order" class="form-label">Approval Order</label>
                                <input type="number" class="form-control @error('approval_order') is-invalid @enderror"
                                    id="approval_order" name="approval_order" value="{{ old('approval_order') }}" min="1"
                                    required>
                                @error('approval_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Enter the order number for this step in the workflow (e.g., 1 for
                                    first step)</div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_enabled" name="is_enabled" value="1"
                                    {{ old('is_enabled', '1') ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_enabled">Enabled</label>
                            </div>

                            <button type="submit" class="btn btn-primary">Add Definition</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection