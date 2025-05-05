@extends('layouts.app')

@section('title', 'Edit Division')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title">Edit Division</h4>
                        <a href="{{ route('divisions.show', $division->division_id) }}" class="btn btn-secondary">Back to
                            Division</a>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('divisions.update', $division->division_id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label for="division_name" class="form-label">Division Name</label>
                                <input type="text" class="form-control @error('division_name') is-invalid @enderror"
                                    id="division_name" name="division_name"
                                    value="{{ old('division_name', $division->division_name) }}" required>
                                @error('division_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="division_head" class="form-label">Division Head (User ID)</label>
                                <input type="number" class="form-control @error('division_head') is-invalid @enderror"
                                    id="division_head" name="division_head"
                                    value="{{ old('division_head', $division->division_head) }}" required>
                                @error('division_head')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="focal_person" class="form-label">Focal Person (User ID)</label>
                                <input type="number" class="form-control @error('focal_person') is-invalid @enderror"
                                    id="focal_person" name="focal_person"
                                    value="{{ old('focal_person', $division->focal_person) }}" required>
                                @error('focal_person')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="admin_assistant" class="form-label">Admin Assistant (User ID)</label>
                                <input type="number" class="form-control @error('admin_assistant') is-invalid @enderror"
                                    id="admin_assistant" name="admin_assistant"
                                    value="{{ old('admin_assistant', $division->admin_assistant) }}" required>
                                @error('admin_assistant')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="finance_officer" class="form-label">Finance Officer (User ID)</label>
                                <input type="number" class="form-control @error('finance_officer') is-invalid @enderror"
                                    id="finance_officer" name="finance_officer"
                                    value="{{ old('finance_officer', $division->finance_officer) }}" required>
                                @error('finance_officer')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary">Update Division</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection