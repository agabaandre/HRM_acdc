@extends('layouts.app')

@section('title', 'Division Details')

@section('header', 'Division Details')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('divisions.edit', $division->id) }}" class="btn btn-warning">
        <i class="bx bx-edit"></i> Edit Division
    </a>
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

        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Basic Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-md-4 fw-bold">ID:</div>
                            <div class="col-md-8">{{ $division->id }}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-4 fw-bold">Name:</div>
                            <div class="col-md-8">{{ $division->division_name }}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-4 fw-bold">Status:</div>
                            <div class="col-md-8">
                                @if($division->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Key Personnel</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-sm-12 fw-bold">Division Head:</div>
                            <div class="col-sm-12">
                                <div class="d-flex align-items-center">
                                    <i class="bx bx-user-circle fs-5 me-2 text-primary"></i>
                                    <span>{{ $division->divisionHead ? $division->divisionHead->fname . ' ' . $division->divisionHead->lname : 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-12 fw-bold">Focal Person:</div>
                            <div class="col-sm-12">
                                <div class="d-flex align-items-center">
                                    <i class="bx bx-user-voice fs-5 me-2 text-info"></i>
                                    <span>{{ $division->focalPerson ? $division->focalPerson->fname . ' ' . $division->focalPerson->lname : 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-12 fw-bold">Admin Assistant:</div>
                            <div class="col-sm-12">
                                <div class="d-flex align-items-center">
                                    <i class="bx bx-support fs-5 me-2 text-success"></i>
                                    <span>{{ $division->adminAssistant ? $division->adminAssistant->fname . ' ' . $division->adminAssistant->lname : 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-12 fw-bold">Finance Officer:</div>
                            <div class="col-sm-12">
                                <div class="d-flex align-items-center">
                                    <i class="bx bx-dollar-circle fs-5 me-2 text-warning"></i>
                                    <span>{{ $division->financeOfficer ? $division->financeOfficer->fname . ' ' . $division->financeOfficer->lname : 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteDivision">
                <i class="bx bx-trash me-1"></i> Delete Division
            </button>
        </div>

        <!-- Delete Modal -->
        <div class="modal fade" id="deleteDivision" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Division</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete <strong>{{ $division->division_name }}</strong>?</p>
                        <p class="text-danger"><small>This action cannot be undone. If this division has associated records, deletion may fail.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form action="{{ route('divisions.destroy', $division->id) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
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