@extends('layouts.app')

@section('title', 'Edit Location: ' . $location->name)

@section('header', 'Edit Location')

@section('header-actions')
    <a href="{{ route('locations.show', $location->id) }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to Details
    </a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bx bx-error-circle me-2"></i>
                <strong>Please fix the following issues:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form action="{{ route('locations.update', $location->id) }}" method="POST" id="editLocationForm">
            @csrf
            @method('PUT')
            
            <div class="mb-4">
                <label for="name" class="form-label">Location Name <span class="text-danger">*</span></label>
                <input type="text" 
                       class="form-control @error('name') is-invalid @enderror" 
                       id="name" 
                       name="name" 
                       value="{{ old('name', $location->name) }}" 
                       placeholder="Enter location name"
                       required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @else
                    <div class="form-text">Enter a unique name for this location.</div>
                @enderror
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('locations.show', $location->id) }}" class="btn btn-light">
                    <i class="bx bx-x"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bx bx-check"></i> Update Location
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize form validation
        const form = document.getElementById('editLocationForm');
        
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
</script>
@endpush
@endsection
