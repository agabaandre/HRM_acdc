@extends('layouts.app')

@section('title', 'Create Memo')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">Create New Memo</h4>
                    <a href="{{ route('memos.index') }}" class="btn btn-secondary">Back to Memos</a>
                </div>
                <div class="card-body">
                    <form action="{{ route('memos.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title') }}" required>
                            @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="country" class="form-label">Country</label>
                            <input type="text" class="form-control @error('country') is-invalid @enderror" id="country" name="country" value="{{ old('country') }}" required>
                            @error('country')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="workflow_id" class="form-label">Workflow</label>
                            <select class="form-select @error('workflow_id') is-invalid @enderror" id="workflow_id" name="workflow_id" required>
                                <option value="">Select a workflow</option>
                                @foreach($workflows as $workflow)
                                <option value="{{ $workflow->id }}" {{ old('workflow_id') == $workflow->id ? 'selected' : '' }}>
                                    {{ $workflow->workflow_name }}
                                </option>
                                @endforeach
                            </select>
                            @error('workflow_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="5" required>{{ old('description') }}</textarea>
                            @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="document_id" class="form-label">Document ID</label>
                            <input type="number" class="form-control @error('document_id') is-invalid @enderror" id="document_id" name="document_id" value="{{ old('document_id', 1) }}">
                            @error('document_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Optional. Leave as default (1) if not needed.</div>
                        </div>

                        <button type="submit" class="btn btn-primary">Create Memo</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
