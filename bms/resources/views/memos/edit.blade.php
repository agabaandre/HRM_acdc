@extends('layouts.app')

@section('title', 'Edit Memo')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title">Edit Memo</h4>
                        <a href="{{ route('memos.show', $memo->id) }}" class="btn btn-secondary">Back to Memo</a>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('memos.update', $memo->id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control @error('title') is-invalid @enderror" id="title"
                                    name="title" value="{{ old('title', $memo->title) }}" required>
                                @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="country" class="form-label">Country</label>
                                <input type="text" class="form-control @error('country') is-invalid @enderror" id="country"
                                    name="country" value="{{ old('country', $memo->country) }}" required>
                                @error('country')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="workflow_id" class="form-label">Workflow</label>
                                <select class="form-select @error('workflow_id') is-invalid @enderror" id="workflow_id"
                                    name="workflow_id" required>
                                    <option value="">Select a workflow</option>
                                    @foreach($workflows as $workflow)
                                        <option value="{{ $workflow->id }}" {{ (old('workflow_id', $memo->workflow_id) == $workflow->id) ? 'selected' : '' }}>
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
                                <textarea class="form-control @error('description') is-invalid @enderror" id="description"
                                    name="description" rows="5"
                                    required>{{ old('description', $memo->description) }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary">Update Memo</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection