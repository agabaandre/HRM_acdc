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
                    @if($specialMemo->overall_status === 'approved')
                        <a href="{{ route('special-memo.print', $specialMemo) }}" target="_blank" class="btn btn-success">
                            <i class="bx bx-printer me-1"></i>Print PDF
                        </a>
                    @endif
                    @if($specialMemo->is_draft && $specialMemo->staff_id === user_session('staff_id'))
                        <form action="{{ route('special-memo.submit-for-approval', $specialMemo) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="bx bx-send me-1"></i>Submit for Approval
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <!-- Current Supervisor Information -->
            @if($specialMemo->overall_status !== 'approved' && $specialMemo->overall_status !== 'rejected' && $specialMemo->current_actor)
                <div class="card matrix-card mb-4" style="border-left: 4px solid #3b82f6;">
                    <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                        <h6 class="m-0 fw-semibold text-primary"><i class="bx bx-user-check me-2"></i>Current Supervisor Information</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong class="text-muted">Supervisor Name:</strong> 
                                    <span class="fw-bold text-primary fs-5">{{ $specialMemo->current_actor->fname . ' ' . $specialMemo->current_actor->lname }}</span>
                                </div>
                                @if($specialMemo->current_actor->job_name)
                                    <div class="mb-3">
                                        <strong class="text-muted">Job Title:</strong> 
                                        <span class="fw-bold">{{ $specialMemo->current_actor->job_name }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                @if($specialMemo->current_actor->division_name)
                                    <div class="mb-3">
                                        <strong class="text-muted">Division:</strong> 
                                        <span class="fw-bold">{{ $specialMemo->current_actor->division_name }}</span>
                                    </div>
                                @endif
                                @if($specialMemo->workflow_definition)
                                    <div class="mb-3">
                                        <strong class="text-muted">Approval Role:</strong> 
                                        <span class="badge bg-info">{{ $specialMemo->workflow_definition->role ?? 'Not specified' }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="mt-3 p-3 bg-primary bg-opacity-10 rounded">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bx bx-info-circle text-primary"></i>
                                <span class="text-primary fw-medium">This memo is currently awaiting approval from the supervisor above.</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Status Overview -->
            <div class="row">
                <div class="col-12">
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

                    <!-- Approval Workflow Overview -->
                    @include('partials.approval-workflow-overview')

                    <!-- Approval Actions -->
                    @if(can_take_action_generic($specialMemo))
                        <div class="card matrix-card mb-4" style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
                            <div class="card-header bg-transparent border-0 py-4" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 12px 12px 0 0;">
                                <h6 class="m-0 fw-semibold" style="color: #1f2937;"><i class="bx bx-check-circle me-2" style="color: #059669;"></i>Approval Actions - Level {{ $specialMemo->approval_level ?? 0 }}</h6>
                            </div>
                            <div class="card-body p-4">
                                <div class="alert alert-info mb-3">
                                    <i class="bx bx-info-circle me-2"></i>
                                    <strong>Current Level:</strong> {{ $specialMemo->approval_level ?? 0 }}
                                    @if($specialMemo->workflow_definition)
                                        - <strong>Role:</strong> {{ $specialMemo->workflow_definition->role ?? 'Not specified' }}
                                    @endif
                                    @if($specialMemo->current_actor)
                                        <br><strong>Supervisor:</strong> {{ $specialMemo->current_actor->fname . ' ' . $specialMemo->current_actor->lname }}
                                        @if($specialMemo->current_actor->job_name)
                                            ({{ $specialMemo->current_actor->job_name }})
                                        @endif
                                    @endif
                                </div>
                                
                                <form action="{{ route('special-memo.update-status', $specialMemo) }}" method="POST" id="approvalForm">
                                    @csrf
                                    <input type="hidden" name="debug_approval" value="1">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label for="comment" class="form-label">Comments (Optional)</label>
                                                <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Add any comments about your decision..."></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-grid gap-2">
                                                <button type="submit" name="action" value="approved" class="btn btn-success w-100 d-flex align-items-center justify-content-center gap-2">
                                                    <i class="bx bx-check"></i>
                                                    Approve
                                                </button>
                                                <button type="submit" name="action" value="returned" class="btn btn-warning w-100 d-flex align-items-center justify-content-center gap-2">
                                                    <i class="bx bx-undo"></i>
                                                    Return
                                                </button>
                                                <button type="submit" name="action" value="rejected" class="btn btn-danger w-100 d-flex align-items-center justify-content-center gap-2">
                                                    <i class="bx bx-x"></i>
                                                    Cancel
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
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

.approver-item {
    transition: all 0.2s ease;
    border: 1px solid rgba(0,0,0,0.1);
}

.approver-item:hover {
    background: rgba(0,0,0,0.08) !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card {
    transition: all 0.3s ease;
    min-height: 260px;
    display: flex;
    flex-direction: column;
}

.card .card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
}

.badge {
    font-weight: 500;
}

.approvers-section {
    max-height: 200px;
    overflow-y: auto;
}

.approvers-section::-webkit-scrollbar {
    width: 4px;
}

.approvers-section::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.1);
    border-radius: 2px;
}

.approvers-section::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.3);
    border-radius: 2px;
}

.approvers-section::-webkit-scrollbar-thumb:hover {
    background: rgba(0,0,0,0.5);
}
</style>
@endpush 