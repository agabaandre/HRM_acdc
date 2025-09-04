@extends('layouts.app')

@section('title', 'Funder Details')

@section('header', 'Funder Details')

@section('header-actions')
<div class="btn-group" role="group">
    <a href="{{ route('funders.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to List
    </a>
    <a href="{{ route('funders.edit', $funder) }}" class="btn btn-warning">
        <i class="bx bx-edit"></i> Edit
    </a>
</div>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bx bx-building me-2"></i>{{ $funder->name }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="info-item">
                            <label class="fw-semibold text-muted">Contact Person</label>
                            <p class="mb-0">{{ $funder->contact_person ?? 'Not specified' }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <label class="fw-semibold text-muted">Email Address</label>
                            <p class="mb-0">
                                @if($funder->email)
                                    <a href="mailto:{{ $funder->email }}" class="text-decoration-none">
                                        <i class="bx bx-envelope me-1"></i>{{ $funder->email }}
                                    </a>
                                @else
                                    Not specified
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <label class="fw-semibold text-muted">Phone Number</label>
                            <p class="mb-0">
                                @if($funder->phone)
                                    <a href="tel:{{ $funder->phone }}" class="text-decoration-none">
                                        <i class="bx bx-phone me-1"></i>{{ $funder->phone }}
                                    </a>
                                @else
                                    Not specified
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <label class="fw-semibold text-muted">Website</label>
                            <p class="mb-0">
                                @if($funder->website)
                                    <a href="{{ $funder->website }}" target="_blank" class="text-decoration-none">
                                        <i class="bx bx-link-external me-1"></i>Visit Website
                                    </a>
                                @else
                                    Not specified
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="info-item">
                            <label class="fw-semibold text-muted">Address</label>
                            <p class="mb-0">{{ $funder->address ?? 'Not specified' }}</p>
                        </div>
                    </div>
                    @if($funder->description)
                        <div class="col-12">
                            <div class="info-item">
                                <label class="fw-semibold text-muted">Description</label>
                                <p class="mb-0">{{ $funder->description }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bx bx-info-circle me-2"></i>Status & Information</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="fw-semibold text-muted">Status</label>
                    <div>
                        @if($funder->is_active)
                            <span class="badge bg-success fs-6">
                                <i class="bx bx-check-circle me-1"></i>Active
                            </span>
                        @else
                            <span class="badge bg-danger fs-6">
                                <i class="bx bx-x-circle me-1"></i>Inactive
                            </span>
                        @endif
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="fw-semibold text-muted">Created</label>
                    <p class="mb-0">{{ $funder->created_at ? $funder->created_at->format('M j, Y g:i A') : 'N/A' }}</p>
                </div>
                
                <div class="mb-3">
                    <label class="fw-semibold text-muted">Last Updated</label>
                    <p class="mb-0">{{ $funder->updated_at ? $funder->updated_at->format('M j, Y g:i A') : 'N/A' }}</p>
                </div>
                
                <div class="mb-3">
                    <label class="fw-semibold text-muted">Associated Fund Codes</label>
                    <div>
                        <span class="badge bg-primary fs-6">{{ $funder->fundCodes()->count() }}</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bx bx-cog me-2"></i>Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('funders.edit', $funder) }}" class="btn btn-warning">
                        <i class="bx bx-edit me-1"></i> Edit Funder
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@if($funder->fundCodes()->count() > 0)
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="bx bx-list-ul me-2"></i>Associated Fund Codes</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Year</th>
                            <th>Activity</th>
                            <th>Fund Type</th>
                            <th>Division</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($funder->fundCodes as $fundCode)
                            <tr>
                                <td><strong>{{ $fundCode->code }}</strong></td>
                                <td>{{ $fundCode->year }}</td>
                                <td>{{ $fundCode->activity ?? 'N/A' }}</td>
                                <td>{{ $fundCode->fundType->name ?? 'N/A' }}</td>
                                <td>{{ $fundCode->division->division_name ?? 'N/A' }}</td>
                                <td>
                                    @if($fundCode->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-danger">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('fund-codes.show', $fundCode) }}" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View">
                                        <i class="bx bx-show"></i>
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

@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });
</script>
@endpush
