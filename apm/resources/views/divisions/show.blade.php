@extends('layouts.app')

@section('title', 'Division Details')

@section('header', 'Division Details')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('divisions.index') }}" class="btn btn-secondary">
        <i class="bx bx-arrow-back"></i> Back to Divisions
    </a>
</div>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bx bx-building-house me-2 text-primary"></i>{{ $division->division_name }}</h5>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <!-- Basic Information Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="bx bx-info-circle me-2"></i>Basic Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="info-item">
                                    <label class="fw-bold text-muted">Division ID</label>
                                    <div class="fs-5 fw-bold text-primary">{{ $division->id }}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item">
                                    <label class="fw-bold text-muted">Division Name</label>
                                    <div class="fs-5 fw-bold">{{ $division->division_name }}</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-item">
                                    <label class="fw-bold text-muted">Short Name</label>
                                    <div>
                                        @if($division->division_short_name)
                                            <span class="badge bg-primary fs-6">{{ $division->division_short_name }}</span>
                                        @else
                                            <span class="text-muted">Not specified</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="info-item">
                                    <label class="fw-bold text-muted">Category</label>
                                    <div>
                                        @if($division->category)
                                            <span class="badge bg-secondary fs-6">{{ $division->category }}</span>
                                        @else
                                            <span class="text-muted">Not specified</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item">
                                    <label class="fw-bold text-muted">Status</label>
                                    <div>
                                        @if(isset($division->is_active) && $division->is_active)
                                            <span class="badge bg-success fs-6">Active</span>
                                        @else
                                            <span class="badge bg-secondary fs-6">Status Unknown</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Staff Assignments Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="bx bx-group me-2"></i>Staff Assignments</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 col-lg-3 mb-3">
                                <div class="staff-card">
                                    <div class="staff-header">
                                        <i class="bx bx-user-circle fs-3 text-primary"></i>
                                        <h6 class="mb-1">Division Head</h6>
                                    </div>
                                    <div class="staff-info">
                                        @if($division->divisionHead)
                                            <div class="fw-bold">{{ $division->divisionHead->fname }} {{ $division->divisionHead->lname }}</div>
                                            <small class="text-muted">{{ $division->divisionHead->position ?? 'Staff' }}</small>
                                        @else
                                            <div class="text-muted">Not assigned</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3 mb-3">
                                <div class="staff-card">
                                    <div class="staff-header">
                                        <i class="bx bx-user-voice fs-3 text-info"></i>
                                        <h6 class="mb-1">Focal Person</h6>
                                    </div>
                                    <div class="staff-info">
                                        @if($division->focalPerson)
                                            <div class="fw-bold">{{ $division->focalPerson->fname }} {{ $division->focalPerson->lname }}</div>
                                            <small class="text-muted">{{ $division->focalPerson->position ?? 'Staff' }}</small>
                                        @else
                                            <div class="text-muted">Not assigned</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3 mb-3">
                                <div class="staff-card">
                                    <div class="staff-header">
                                        <i class="bx bx-support fs-3 text-success"></i>
                                        <h6 class="mb-1">Admin Assistant</h6>
                                    </div>
                                    <div class="staff-info">
                                        @if($division->adminAssistant)
                                            <div class="fw-bold">{{ $division->adminAssistant->fname }} {{ $division->adminAssistant->lname }}</div>
                                            <small class="text-muted">{{ $division->adminAssistant->position ?? 'Staff' }}</small>
                                        @else
                                            <div class="text-muted">Not assigned</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3 mb-3">
                                <div class="staff-card">
                                    <div class="staff-header">
                                        <i class="bx bx-dollar-circle fs-3 text-warning"></i>
                                        <h6 class="mb-1">Finance Officer</h6>
                                    </div>
                                    <div class="staff-info">
                                        @if($division->financeOfficer)
                                            <div class="fw-bold">{{ $division->financeOfficer->fname }} {{ $division->financeOfficer->lname }}</div>
                                            <small class="text-muted">{{ $division->financeOfficer->position ?? 'Staff' }}</small>
                                        @else
                                            <div class="text-muted">Not assigned</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Information Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="bx bx-detail me-2"></i>Additional Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-item">
                                    <label class="fw-bold text-muted">Directorate ID</label>
                                    <div>
                                        @if($division->directorate_id)
                                            <span class="badge bg-info">{{ $division->directorate_id }}</span>
                                        @else
                                            <span class="text-muted">Not specified</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item">
                                    <label class="fw-bold text-muted">Director ID</label>
                                    <div>
                                        @if($division->director_id)
                                            <span class="badge bg-info">{{ $division->director_id }}</span>
                                        @else
                                            <span class="text-muted">Not specified</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        @if($division->head_oic_id || $division->head_oic_start_date || $division->head_oic_end_date)
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6 class="text-muted mb-3">Head OIC Information</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="info-item">
                                            <label class="fw-bold text-muted">OIC ID</label>
                                            <div>
                                                @if($division->head_oic_id)
                                                    <span class="badge bg-warning">{{ $division->head_oic_id }}</span>
                                                @else
                                                    <span class="text-muted">Not specified</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="info-item">
                                            <label class="fw-bold text-muted">Start Date</label>
                                            <div>
                                                @if($division->head_oic_start_date)
                                                    <span class="text-dark">{{ \Carbon\Carbon::parse($division->head_oic_start_date)->format('M d, Y') }}</span>
                                                @else
                                                    <span class="text-muted">Not specified</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="info-item">
                                            <label class="fw-bold text-muted">End Date</label>
                                            <div>
                                                @if($division->head_oic_end_date)
                                                    <span class="text-dark">{{ \Carbon\Carbon::parse($division->head_oic_end_date)->format('M d, Y') }}</span>
                                                @else
                                                    <span class="text-muted">Not specified</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if($division->director_oic_id || $division->director_oic_start_date || $division->director_oic_end_date)
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6 class="text-muted mb-3">Director OIC Information</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="info-item">
                                            <label class="fw-bold text-muted">OIC ID</label>
                                            <div>
                                                @if($division->director_oic_id)
                                                    <span class="badge bg-warning">{{ $division->director_oic_id }}</span>
                                                @else
                                                    <span class="text-muted">Not specified</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="info-item">
                                            <label class="fw-bold text-muted">Start Date</label>
                                            <div>
                                                @if($division->director_oic_start_date)
                                                    <span class="text-dark">{{ \Carbon\Carbon::parse($division->director_oic_start_date)->format('M d, Y') }}</span>
                                                @else
                                                    <span class="text-muted">Not specified</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="info-item">
                                            <label class="fw-bold text-muted">End Date</label>
                                            <div>
                                                @if($division->director_oic_end_date)
                                                    <span class="text-dark">{{ \Carbon\Carbon::parse($division->director_oic_end_date)->format('M d, Y') }}</span>
                                                @else
                                                    <span class="text-muted">Not specified</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <div class="text-muted">
                <small><i class="bx bx-info-circle me-1"></i>Divisions are managed in the main system</small>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .info-item {
        margin-bottom: 1rem;
    }
    
    .info-item label {
        display: block;
        margin-bottom: 0.25rem;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .staff-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 1rem;
        height: 100%;
        transition: all 0.3s ease;
    }
    
    .staff-card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    
    .staff-header {
        text-align: center;
        margin-bottom: 0.75rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #e9ecef;
    }
    
    .staff-header i {
        display: block;
        margin-bottom: 0.5rem;
    }
    
    .staff-header h6 {
        margin: 0;
        color: #6c757d;
        font-weight: 600;
    }
    
    .staff-info {
        text-align: center;
    }
    
    .staff-info .fw-bold {
        color: #212529;
        margin-bottom: 0.25rem;
    }
    
    .staff-info small {
        color: #6c757d;
    }
    
    .card-header {
        border-bottom: none;
        font-weight: 600;
    }
    
    .card-header i {
        font-size: 1.1rem;
    }
    
    .badge {
        font-size: 0.75rem;
        padding: 0.375rem 0.75rem;
    }
    
    .fs-6 {
        font-size: 0.875rem !important;
    }
    
    .fs-5 {
        font-size: 1.25rem !important;
    }
    
    .fs-3 {
        font-size: 1.75rem !important;
    }
</style>
@endpush

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