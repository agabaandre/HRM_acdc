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

                    <!-- Approval Levels Overview -->
                    @if(!empty($approvalLevels))
                        <div class="card matrix-card mb-4">
                            <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                                <h6 class="m-0 fw-semibold text-primary"><i class="bx bx-layer-group me-2"></i>Approval Levels Overview</h6>
                            </div>
                            <div class="card-body p-4">
                                <div class="row">
                                    @foreach($approvalLevels as $level)
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card border-0 h-100 {{ $level['is_current'] ? 'border-primary border-2' : '' }}" 
                                                 style="background: {{ $level['is_completed'] ? '#d1fae5' : ($level['is_pending'] ? '#fef3c7' : '#f3f4f6') }};">
                                                <div class="card-body p-3">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <span class="badge bg-{{ $level['is_completed'] ? 'success' : ($level['is_pending'] ? 'warning' : 'secondary') }} fs-6">
                                                            Level {{ $level['order'] }}
                                                        </span>
                                                        @if($level['is_current'])
                                                            <span class="badge bg-primary">Current</span>
                                                        @elseif($level['is_completed'])
                                                            <span class="badge bg-success">✓ Completed</span>
                                                        @elseif($level['is_pending'])
                                                            <span class="badge bg-warning">⏳ Pending</span>
                                                        @else
                                                            <span class="badge bg-secondary">Waiting</span>
                                                        @endif
                                                    </div>
                                                    
                                                    <h6 class="fw-bold mb-2">{{ $level['role'] ?? 'Role Not Specified' }}</h6>
                                                    
                                                    @if($level['approver'])
                                                        <div class="mb-2">
                                                            <small class="text-muted">Supervisor/Approver:</small><br>
                                                            <strong>{{ $level['approver']->fname . ' ' . $level['approver']->lname }}</strong>
                                                            @if($level['approver']->job_name)
                                                                <br><small class="text-muted">{{ $level['approver']->job_name }}</small>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <div class="mb-2">
                                                            <small class="text-muted">Supervisor/Approver:</small><br>
                                                            <span class="text-muted">Not assigned</span>
                                                        </div>
                                                    @endif
                                                    
                                                    @if($level['is_division_specific'])
                                                        <div class="mb-2">
                                                            <span class="badge bg-info">Division Specific</span>
                                                            @if($level['division_reference'])
                                                                <br><small class="text-muted">{{ ucfirst(str_replace('_', ' ', $level['division_reference'])) }}</small>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    
                                                    @if($level['category'])
                                                        <div class="mb-2">
                                                            <small class="text-muted">Category:</small><br>
                                                            <span class="fw-medium">{{ $level['category'] }}</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                
                                <!-- Current Level Summary -->
                                @if($specialMemo->overall_status !== 'approved' && $specialMemo->overall_status !== 'rejected')
                                    <div class="mt-4 p-3 bg-light rounded">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong class="text-muted">Current Approval Level:</strong> 
                                                <span class="badge bg-primary fs-6">{{ $specialMemo->approval_level ?? 0 }}</span>
                                            </div>
                                            <div class="col-md-6">
                                                <strong class="text-muted">Total Levels:</strong> 
                                                <span class="badge bg-secondary fs-6">{{ count($approvalLevels) }}</span>
                                            </div>
                                        </div>
                                        @if($specialMemo->workflow_definition)
                                            <div class="mt-2">
                                                <strong class="text-muted">Current Role:</strong> 
                                                <span class="fw-bold">{{ $specialMemo->workflow_definition->role ?? 'Not specified' }}</span>
                                            </div>
                                        @endif
                                        @if($specialMemo->current_actor)
                                            <div class="mt-2">
                                                <strong class="text-muted">Current Supervisor:</strong> 
                                                <span class="fw-bold text-primary">{{ $specialMemo->current_actor->fname . ' ' . $specialMemo->current_actor->lname }}</span>
                                                @if($specialMemo->current_actor->job_name)
                                                    <br><small class="text-muted">{{ $specialMemo->current_actor->job_name }}</small>
                                                @endif
                                                @if($specialMemo->current_actor->division_name)
                                                    <br><small class="text-muted">{{ $specialMemo->current_actor->division_name }}</small>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Approval Actions -->
                    @if(can_take_action_generic($specialMemo))
                        <div class="card matrix-card mb-4">
                            <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                                <h6 class="m-0 fw-semibold text-success"><i class="bx bx-check-circle me-2"></i>Approval Actions - Level {{ $specialMemo->approval_level ?? 0 }}</h6>
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