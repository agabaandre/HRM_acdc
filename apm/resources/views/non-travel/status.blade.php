@extends('layouts.app')

@section('title', 'Non-Travel Memo Status')

@section('header', 'Non-Travel Memo Approval Status')

@section('header-actions')
    <a href="{{ route('non-travel.show', $nonTravel) }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back me-1"></i> Back to Memo
    </a>
    @if($nonTravel->overall_status === 'approved')
        <a href="{{ route('non-travel.print', $nonTravel) }}" target="_blank" class="btn btn-success">
            <i class="bx bx-printer me-1"></i> Print PDF
        </a>
    @endif
    @if($nonTravel->overall_status === 'draft')
        <a href="{{ route('non-travel.edit', $nonTravel) }}" class="btn btn-warning">
            <i class="bx bx-edit me-1"></i> Edit Memo
        </a>
    @endif
@endsection

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <!-- Header Card -->
            <div class="card shadow-sm border-0 mb-4 rounded-3">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 text-success">
                        <i class="fas fa-info-circle me-2"></i>Approval Status & Workflow
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label fw-semibold text-muted">
                                    <i class="bx bx-hash me-1 text-success"></i>Memo ID
                                </label>
                                <div class="fw-bold text-dark">#{{ $nonTravel->id }}</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label fw-semibold text-muted">
                                    <i class="bx bx-edit me-1 text-success"></i>Title
                                </label>
                                <div class="fw-bold text-dark">{{ $nonTravel->activity_title ?? 'Not specified' }}</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label fw-semibold text-muted">
                                    <i class="bx bx-user me-1 text-success"></i>Requestor
                                </label>
                                <div class="fw-bold text-dark">{{ $nonTravel->staff ? ($nonTravel->staff->fname . ' ' . $nonTravel->staff->lname) : 'Not assigned' }}</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label fw-semibold text-muted">
                                    <i class="bx bx-buildings me-1 text-success"></i>Division
                                </label>
                                <div class="fw-bold text-dark">{{ $nonTravel->division ? $nonTravel->division->division_name : 'Not assigned' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label fw-semibold text-muted">
                                    <i class="bx bx-check-circle me-1 text-success"></i>Status
                                </label>
                                <div>
                                    <span class="badge bg-{{ $nonTravel->overall_status === 'approved' ? 'success' : ($nonTravel->overall_status === 'pending' ? 'warning' : ($nonTravel->overall_status === 'rejected' ? 'danger' : 'secondary')) }} fs-6 px-3 py-2">
                                        {{ ucfirst($nonTravel->overall_status ?? 'draft') }}
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label fw-semibold text-muted">
                                    <i class="bx bx-layer me-1 text-success"></i>Approval Level
                                </label>
                                <div>
                                    <span class="badge bg-primary fs-6 px-3 py-2">{{ $nonTravel->approval_level ?? 0 }}</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label fw-semibold text-muted">
                                    <i class="bx bx-git-branch me-1 text-success"></i>Current Workflow
                                </label>
                                <div class="fw-bold text-dark">{{ $nonTravel->forwardWorkflow->workflow_name ?? 'Not assigned' }}</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label fw-semibold text-muted">
                                    <i class="bx bx-calendar me-1 text-success"></i>Created
                                </label>
                                <div class="fw-bold text-dark">{{ $nonTravel->created_at ? $nonTravel->created_at->format('M d, Y H:i') : 'Not available' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Approval Workflow Overview -->
            @include('partials.approval-workflow-overview')

         
        </div>
    </div>
</div>
@endsection
