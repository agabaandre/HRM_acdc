@extends('layouts.app')

@section('title', 'Single Memo Status')

@section('header', 'Single Memo Approval Status')

@section('header-actions')
    <a href="{{ route('activities.single-memos.show', $singleMemo) }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back me-1"></i> Back to Memo
    </a>
    @if($singleMemo->overall_status === 'approved')
        <a href="{{ route('activities.single-memos.print', $singleMemo) }}" target="_blank" class="btn btn-success">
            <i class="bx bx-printer me-1"></i> Print PDF
        </a>
    @endif
    @if(can_edit_memo($singleMemo))
        <a href="{{ route('activities.single-memos.edit', [$singleMemo->matrix, $singleMemo]) }}" class="btn btn-warning">
            <i class="bx bx-edit me-1"></i> Edit Memo
        </a>
    @endif
    @if($singleMemo->responsible_person_id == user_session('staff_id') && in_array($singleMemo->overall_status, ['draft', 'returned']))
        <form action="{{ route('activities.single-memos.destroy', $singleMemo) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this single memo? This action cannot be undone.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">
                <i class="bx bx-trash me-1"></i> Delete Memo
            </button>
        </form>
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
                                <div class="fw-bold text-dark">#{{ $singleMemo->id }}</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label fw-semibold text-muted">
                                    <i class="bx bx-edit me-1 text-success"></i>Title
                                </label>
                                <div class="fw-bold text-dark">{{ $singleMemo->activity_title ?? 'Not specified' }}</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label fw-semibold text-muted">
                                    <i class="bx bx-user me-1 text-success"></i>Requestor
                                </label>
                                <div class="fw-bold text-dark">{{ $singleMemo->staff ? ($singleMemo->staff->fname . ' ' . $singleMemo->staff->lname) : 'Not assigned' }}</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label fw-semibold text-muted">
                                    <i class="bx bx-buildings me-1 text-success"></i>Division
                                </label>
                                <div class="fw-bold text-dark">{{ $singleMemo->division ? $singleMemo->division->division_name : 'Not assigned' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label fw-semibold text-muted">
                                    <i class="bx bx-check-circle me-1 text-success"></i>Status
                                </label>
                                <div>
                                    <span class="badge bg-{{ $singleMemo->overall_status === 'approved' ? 'success' : ($singleMemo->overall_status === 'pending' ? 'warning' : ($singleMemo->overall_status === 'rejected' ? 'danger' : 'secondary')) }} fs-6 px-3 py-2">
                                        {{ ucfirst($singleMemo->overall_status ?? 'draft') }}
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label fw-semibold text-muted">
                                    <i class="bx bx-layer me-1 text-success"></i>Approval Level
                                </label>
                                <div>
                                    <span class="badge bg-primary fs-6 px-3 py-2">{{ $singleMemo->approval_level ?? 0 }}</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label fw-semibold text-muted">
                                    <i class="bx bx-git-branch me-1 text-success"></i>Current Workflow
                                </label>
                                <div class="fw-bold text-dark">{{ $singleMemo->forwardWorkflow->workflow_name ?? 'Not assigned' }}</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label fw-semibold text-muted">
                                    <i class="bx bx-calendar me-1 text-success"></i>Created
                                </label>
                                <div class="fw-bold text-dark">{{ $singleMemo->created_at ? $singleMemo->created_at->format('M d, Y H:i') : 'Not available' }}</div>
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

