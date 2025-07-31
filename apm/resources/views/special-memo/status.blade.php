@extends('layouts.app')

@section('title', 'Special Memo Approval Status')

@section('content')
<div class="container-fluid px-0">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">
                        <i class="bx bx-file-doc me-2 text-success"></i>Special Memo Approval Status
                    </h4>
                    <p class="text-muted mb-0">{{ $specialMemo->activity_title }}</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('special-memo.show', $specialMemo) }}" class="btn btn-outline-secondary">
                        <i class="bx bx-arrow-back me-1"></i>Back to Details
                    </a>
                    @if($specialMemo->overall_status === 'draft' && $specialMemo->staff_id === user_session('staff_id'))
                        <form action="{{ route('special-memo.submit-for-approval', $specialMemo) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="bx bx-send me-1"></i>Submit for Approval
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <!-- Status Overview -->
            <div class="row">
                <div class="col-lg-8">
                    <!-- Current Status Card -->
                    <div class="card matrix-card mb-4">
                        <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                            <h6 class="m-0 fw-semibold text-success"><i class="bx bx-info-circle me-2"></i>Current Status</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <span class="text-muted small">Overall Status</span>
                                        @php
                                            $statusBadgeClass = [
                                                'draft' => 'bg-secondary',
                                                'pending' => 'bg-warning',
                                                'approved' => 'bg-success',
                                                'rejected' => 'bg-danger',
                                                'returned' => 'bg-info',
                                            ][$specialMemo->overall_status] ?? 'bg-secondary';
                                        @endphp
                                        <div><span class="badge {{ $statusBadgeClass }} fs-6">{{ ucfirst($specialMemo->overall_status) }}</span></div>
                                    </div>
                                    <div class="mb-3">
                                        <span class="text-muted small">Approval Level</span>
                                        <div class="fw-bold">{{ $specialMemo->approval_level ?? 'Not Started' }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <span class="text-muted small">Workflow ID</span>
                                        <div class="fw-bold">{{ $specialMemo->forward_workflow_id ?? 'Not Assigned' }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <span class="text-muted small">Submitted By</span>
                                        <div class="fw-bold">{{ optional($specialMemo->staff)->fname }} {{ optional($specialMemo->staff)->lname }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <span class="text-muted small">Division</span>
                                        <div class="fw-bold">{{ optional($specialMemo->division)->division_name ?? '-' }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <span class="text-muted small">Created</span>
                                        <div class="fw-bold">{{ $specialMemo->created_at->format('M j, Y g:i A') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Approval Actions -->
                    @if(can_take_action_generic($specialMemo))
                        <div class="card matrix-card mb-4">
                            <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                                <h6 class="m-0 fw-semibold text-success"><i class="bx bx-check-circle me-2"></i>Approval Actions</h6>
                            </div>
                            <div class="card-body p-4">
                                @include('partials.approval-actions', ['resource' => $specialMemo])
                            </div>
                        </div>
                    @endif

                    <!-- Approval Trail -->
                    <div class="card matrix-card">
                        <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                            <h6 class="m-0 fw-semibold text-success"><i class="bx bx-history me-2"></i>Approval Trail</h6>
                        </div>
                        <div class="card-body p-4">
                            @if($specialMemo->approvalTrails->count() > 0)
                                @include('partials.approval-trail', ['resource' => $specialMemo])
                            @else
                                <div class="text-center text-muted py-4">
                                    <i class="bx bx-time bx-lg mb-3"></i>
                                    <p class="mb-0">No approval actions have been taken yet.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Quick Info -->
                    <div class="card matrix-card mb-4">
                        <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                            <h6 class="m-0 fw-semibold text-success"><i class="bx bx-detail me-2"></i>Quick Info</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <span class="text-muted small">Activity Title</span>
                                <div class="fw-bold">{{ $specialMemo->activity_title ?? '-' }}</div>
                            </div>
                            <div class="mb-3">
                                <span class="text-muted small">Date Range</span>
                                <div class="fw-bold">{{ $specialMemo->formatted_dates ?: '-' }}</div>
                            </div>
                            <div class="mb-3">
                                <span class="text-muted small">Total Participants</span>
                                <div class="fw-bold">{{ $specialMemo->total_participants ?? 0 }}</div>
                            </div>
                            <div class="mb-3">
                                <span class="text-muted small">Request Type</span>
                                <div class="fw-bold">{{ optional($specialMemo->requestType)->name ?? '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Next Approver Info -->
                    @if($specialMemo->overall_status === 'pending')
                        <div class="card matrix-card">
                            <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                                <h6 class="m-0 fw-semibold text-success"><i class="bx bx-user-check me-2"></i>Next Approver</h6>
                            </div>
                            <div class="card-body p-4">
                                @php
                                    $nextApprover = $specialMemo->currentActor;
                                @endphp
                                @if($nextApprover)
                                    <div class="text-center">
                                        <div class="mb-3">
                                            <i class="bx bx-user-circle bx-lg text-primary"></i>
                                        </div>
                                        <div class="fw-bold">{{ $nextApprover->fname }} {{ $nextApprover->lname }}</div>
                                        <div class="text-muted small">Staff ID: {{ $nextApprover->staff_id }}</div>
                                    </div>
                                @else
                                    <div class="text-center text-muted">
                                        <i class="bx bx-user-x bx-lg mb-2"></i>
                                        <p class="mb-0">No next approver assigned</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.matrix-card {
    background: #fff;
    border-radius: 1.25rem;
    box-shadow: 0 4px 24px rgba(17,154,72,0.08);
    border: none;
}
.matrix-card .card-header {
    border-radius: 1.25rem 1.25rem 0 0;
    background: linear-gradient(90deg, #e9f7ef 0%, #fff 100%);
    border-bottom: 1px solid #e9f7ef;
}
.matrix-card .card-body {
    border-radius: 0 0 1.25rem 1.25rem;
}
</style>
@endpush 