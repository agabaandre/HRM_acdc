@extends('layouts.app')

@section('title', 'Directorate Details')

@section('header', 'Directorate Details')

@section('header-actions')
<a href="{{ route('directorates.index') }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back"></i> Back to List
</a>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bx bx-info-circle me-2"></i>Directorate Information</h5>
            </div>
            <div class="card-body p-4">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">ID</h6>
                        <h4 class="mb-0">{{ $directorate->id }}</h4>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <h6 class="text-muted mb-1">Status</h6>
                        @if($directorate->is_active)
                            <span class="badge bg-success fs-6 px-3 py-2">Active</span>
                        @else
                            <span class="badge bg-danger fs-6 px-3 py-2">Inactive</span>
                        @endif
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="text-muted mb-1">Directorate Name</h6>
                    <h5>{{ $directorate->name }}</h5>
                </div>
            </div>
        </div>

        @if(isset($divisions) && $divisions->count() > 0)
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bx bx-building me-2 text-primary"></i>Related Divisions</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Division Name</th>
                                <th>Code</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($divisions as $division)
                            <tr>
                                <td><strong>{{ $division->division_name }}</strong></td>
                                <td>{{ $division->code }}</td>
                                <td>
                                    @if($division->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-danger">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('divisions.show', $division) }}" class="btn btn-sm btn-info">
                                        <i class="bx bx-show"></i> View
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
    
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bx bx-time me-2 text-primary"></i>Timestamps</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Created At</span>
                        <span>{{ $directorate->created_at->format('Y-m-d H:i') }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Last Updated</span>
                        <span>{{ $directorate->updated_at->format('Y-m-d H:i') }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
